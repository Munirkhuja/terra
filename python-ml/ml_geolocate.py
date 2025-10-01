"""
ML detection + geolocation + reverse-geocode helper.

Main entrypoint:
    process_image_bytes(image_bytes: bytes, metadata: dict) -> dict
"""

import math
import json
import logging
from io import BytesIO
from typing import Dict, Any, List, Optional, Tuple

import numpy as np
from PIL import Image, ExifTags

# optional libs
try:
    import requests
except Exception:
    requests = None

try:
    import pytesseract
except Exception:
    pytesseract = None

try:
    from pyproj import CRS
except Exception:
    CRS = None

# optional: ultralytics YOLO
try:
    from ultralytics import YOLO
    _YOLO_AVAILABLE = True
except Exception:
    _YOLO_AVAILABLE = False

logger = logging.getLogger("ml_geolocate")
logging.basicConfig(level=logging.INFO)


# -------------------------
# Utilities: EXIF parsing
# -------------------------
def extract_exif_from_pil(img: Image.Image) -> Dict[str, Any]:
    """Возвращает распарсенный exif (ключи по human-readable тегам)."""
    exif = {}
    try:
        raw = img._getexif()
        if not raw:
            return {}
        for tag, value in raw.items():
            decoded = ExifTags.TAGS.get(tag, tag)
            exif[decoded] = value
        # GPS
        if 'GPSInfo' in exif:
            gps_raw = exif['GPSInfo']
            gps = {}
            for t, val in gps_raw.items():
                sub = ExifTags.GPSTAGS.get(t, t)
                gps[sub] = val
            exif['GPS'] = gps
    except Exception:
        return {}
    return exif


def gps_to_decimal(gps: Dict) -> Optional[Tuple[float, float]]:
    """Convert EXIF GPS dict to decimal (lat, lon)."""
    if not gps:
        return None
    try:
        def _to_deg(t):
            # t is tuple of tuples (num,den)
            d = t[0][0] / t[0][1]
            m = t[1][0] / t[1][1]
            s = t[2][0] / t[2][1]
            return d + m/60.0 + s/3600.0

        lat = _to_deg(gps['GPSLatitude'])
        if gps.get('GPSLatitudeRef') in ['S', b'S']:
            lat = -lat
        lon = _to_deg(gps['GPSLongitude'])
        if gps.get('GPSLongitudeRef') in ['W', b'W']:
            lon = -lon
        return lat, lon
    except Exception:
        return None


# -------------------------
# Detection (pluggable)
# -------------------------
# If YOLOv8 is installed and you set YOLO_MODEL_PATH, we'll use it.
YOLO_MODEL_PATH = None  # set to 'yolov8n.pt' or custom path if available
_yolo_model = None
if _YOLO_AVAILABLE and YOLO_MODEL_PATH:
    try:
        _yolo_model = YOLO(YOLO_MODEL_PATH)
    except Exception:
        _yolo_model = None


def detect_buildings(image: Image.Image) -> List[Dict[str, Any]]:
    """
    Run detection. If YOLO model available, use it; otherwise use lightweight heuristic.
    Return list of dicts: {'label','bbox':[x,y,w,h], 'confidence':float, 'mask':optional}
    """
    if _yolo_model is not None:
        # Use YOLOv8 model inference (expects numpy array)
        np_img = np.array(image)
        results = _yolo_model.predict(np_img, verbose=False)
        detections = []
        try:
            boxes = results[0].boxes
            for b in boxes:
                xyxy = b.xyxy[0].tolist()  # [x1,y1,x2,y2]
                x1, y1, x2, y2 = map(int, xyxy)
                detections.append({
                    "label": _yolo_model.names[int(b.cls[0])],
                    "bbox": [x1, y1, x2 - x1, y2 - y1],
                    "confidence": float(b.conf[0]),
                    "mask": None
                })
        except Exception:
            # fallback empty
            return []
        return detections

    # Fallback fake/simple detector: center large bbox — for tests only
    w, h = image.size
    bx, by = int(w * 0.15), int(h * 0.15)
    bw, bh = int(w * 0.7), int(h * 0.7)
    return [{"label": "building", "bbox": [bx, by, bw, bh], "confidence": 0.6, "mask": None}]


# -------------------------
# Camera intrinsics helpers
# -------------------------
def estimate_focal_pixels(exif: Dict[str, Any], img_width: int) -> Optional[float]:
    """
    Try to estimate focal length in pixels.
    Strategy:
    - use FocalLength (in mm) from EXIF and assume sensor width (default 36mm if unknown)
    - focal_px = focal_mm * (image_width_px / sensor_width_mm)
    """
    focal_mm = None
    try:
        fl = exif.get('FocalLength')
        if fl:
            # FocalLength may be a tuple (num,den)
            if isinstance(fl, tuple):
                focal_mm = fl[0] / fl[1]
            else:
                focal_mm = float(fl)
    except Exception:
        focal_mm = None

    if focal_mm is None:
        return None
    sensor_mm = 36.0  # default assume full-frame if unknown; may be improved by camera model mapping
    focal_px = focal_mm * (img_width / sensor_mm)
    return focal_px


# -------------------------
# INS projection -> compute lat/lon from pixel bbox center + camera pose
# -------------------------
def rotation_matrix_from_yaw_pitch_roll(yaw_deg: float, pitch_deg: float, roll_deg: float) -> np.ndarray:
    """
    yaw (heading) around Z, pitch around Y, roll around X.
    Angles in degrees.
    Returns 3x3 rotation matrix (world <- camera) i.e. R * d_cam = d_world
    """
    yaw = math.radians(yaw_deg)
    pitch = math.radians(pitch_deg)
    roll = math.radians(roll_deg)

    # Rotation matrices
    Rz = np.array([
        [math.cos(yaw), -math.sin(yaw), 0],
        [math.sin(yaw), math.cos(yaw), 0],
        [0, 0, 1]
    ])
    Ry = np.array([
        [math.cos(pitch), 0, math.sin(pitch)],
        [0, 1, 0],
        [-math.sin(pitch), 0, math.cos(pitch)]
    ])
    Rx = np.array([
        [1, 0, 0],
        [0, math.cos(roll), -math.sin(roll)],
        [0, math.sin(roll), math.cos(roll)]
    ])
    # Combined rotation: R = Rz * Ry * Rx (order can be adjusted to match your coordinate convention)
    R = Rz @ Ry @ Rx
    return R


def pixel_to_direction_vector(u: float, v: float, cx: float, cy: float, fx: float, fy: float) -> np.ndarray:
    """
    Convert pixel coordinates to camera-centric direction vector.
    Input: pixel coords (u,v), principal point (cx,cy), focal lengths fx,fy in pixels.
    Returns normalized 3-vector [x, y, 1] transformed and normalized.
    """
    # normalized camera coordinates
    x = (u - cx) / fx
    y = (v - cy) / fy
    vec = np.array([x, y, 1.0])
    # normalize
    vec = vec / np.linalg.norm(vec)
    return vec


def enu_offset_to_latlon(lat0: float, lon0: float, east_m: float, north_m: float) -> Tuple[float, float]:
    """
    Small-delta approx conversion from ENU meters to lat/lon:
    lat2 = lat0 + north / R
    lon2 = lon0 + east / (R * cos(lat))
    R approx Earth radius (m)
    """
    R = 6378137.0
    lat_rad = math.radians(lat0)
    dlat = (north_m / R) * (180.0 / math.pi)
    dlon = (east_m / (R * math.cos(lat_rad))) * (180.0 / math.pi)
    return lat0 + dlat, lon0 + dlon


def project_bbox_center_to_ground_using_ins(
    bbox: List[int],
    img_w: int,
    img_h: int,
    camera_lat: float,
    camera_lon: float,
    camera_alt_m: float,
    yaw_deg: float,
    pitch_deg: float,
    roll_deg: float,
    focal_px: Optional[float] = None
) -> Optional[Dict[str, Any]]:
    """
    Given bbox (x,y,w,h) in pixels and camera pose, compute intersection point on ground (z=0).
    Returns dict with lat/lon, error estimate, confidence, method = 'ins_projection'.
    """
    # compute center pixel
    x, y, w, h = bbox
    cx_pix = x + w / 2.0
    cy_pix = y + h / 2.0

    # principal point assume center
    cx = img_w / 2.0
    cy = img_h / 2.0

    # estimate focal in pixels if not provided
    if focal_px is None:
        # assume fx = fy
        focal_px = max(img_w, img_h)  # very rough fallback -> yields close-range but low accuracy
    fx = fy = focal_px

    # direction in camera coords
    d_cam = pixel_to_direction_vector(cx_pix, cy_pix, cx, cy, fx, fy)  # normalized

    # rotate to world (ENU) frame
    R = rotation_matrix_from_yaw_pitch_roll(yaw_deg, pitch_deg, roll_deg)  # world <- camera
    d_world = R @ d_cam  # direction in world frame (assumed ENU with z-up)

    # if ray points upwards (d_world[2] > 0), cannot intersect ground below; require negative z
    if abs(d_world[2]) < 1e-6:
        return None
    # for camera at altitude H (meters above ground), solve t where origin + t * d_world has z = -H (ENU frame origin at camera)
    # ENU coordinate: z axis up; ground plane at z = -camera_alt_m (if camera_alt is height above ground)
    # origin is (0,0,0), so we need t such that z = 0_ground - camera_alt? Simpler: treat ground at z = -camera_alt_m
    t = -camera_alt_m / d_world[2]
    # only positive t meaningful
    if t <= 0:
        return None
    east = d_world[0] * t
    north = d_world[1] * t
    # convert to lat/lon using small-angle approximation
    lat, lon = enu_offset_to_latlon(camera_lat, camera_lon, east, north)
    # basic error & confidence heuristics
    error_m = max(5.0, camera_alt_m * 0.1 + (1.0 / max(1e-6, abs(d_world[2]))) * 2.0)
    confidence = 0.8
    return {"lat": lat, "lon": lon, "confidence": confidence, "error_radius_m": error_m, "method": "ins_projection"}


# -------------------------
# Visual retrieval / georeg fallback (placeholders)
# -------------------------
def visual_localization_fallback(image: Image.Image) -> Dict[str, Any]:
    import random
    return {"lat": 55.75 + random.uniform(-0.02, 0.02), "lon": 37.61 + random.uniform(-0.02, 0.02), "confidence": 0.25, "error_radius_m": 2000, "method": "visual_retrieval"}


def georeg_model_fallback(image: Image.Image) -> Dict[str, Any]:
    import random
    return {"lat": 55.75 + random.uniform(-0.5, 0.5), "lon": 37.61 + random.uniform(-0.5, 0.5), "confidence": 0.1, "error_radius_m": 20000, "method": "georeg"}


# -------------------------
# OCR
# -------------------------
def run_ocr_if_available(image: Image.Image, bbox: Optional[List[int]] = None) -> Optional[str]:
    """
    Run OCR inside bbox (if bbox provided) or whole image. Uses pytesseract if available.
    """
    if pytesseract is None:
        return None
    try:
        if bbox:
            x, y, w, h = map(int, bbox)
            crop = image.crop((x, y, x + w, y + h))
        else:
            crop = image
        txt = pytesseract.image_to_string(crop, lang='rus+eng')
        txt = txt.strip()
        return txt if txt else None
    except Exception:
        return None


# -------------------------
# Reverse geocoding (Nominatim / OSM)
# -------------------------
def reverse_geocode(lat: float, lon: float) -> Optional[Dict[str, Any]]:
    if requests is None:
        return None
    try:
        url = f"https://nominatim.openstreetmap.org/reverse"
        headers = {"User-Agent": "ml-geolocate/1.0 (+your-email@example.com)"}
        params = {"lat": lat, "lon": lon, "format": "jsonv2", "addressdetails": 1}
        r = requests.get(url, params=params, headers=headers, timeout=8)
        r.raise_for_status()
        j = r.json()
        address = j.get("display_name")
        return {"address": address, "raw": j}
    except Exception:
        return None


# -------------------------
# High-level pipeline
# -------------------------
def process_image_bytes(image_bytes: bytes, metadata: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    """
    Main entry. metadata may include:
      - 'ins': {'lat':..., 'lon':..., 'alt_m':..., 'yaw':..., 'pitch':..., 'roll':..., 'focal_mm':..., 'sensor_mm':...}
      - or arbitrary fields helpful for localization
    """
    metadata = metadata or {}
    out = {"detections": [], "image_geolocation": None}
    try:
        img = Image.open(BytesIO(image_bytes)).convert("RGB")
    except Exception as e:
        logger.exception("Failed to open image: %s", e)
        return out

    img_w, img_h = img.size
    exif = extract_exif_from_pil(img)

    # 0) global image-level geolocation from EXIF if present
    image_geo_guess = None
    gps = exif.get("GPS")
    if gps:
        latlon = gps_to_decimal(gps)
        if latlon:
            image_geo_guess = {"lat": latlon[0], "lon": latlon[1], "method": "exif", "confidence": 0.95, "error_radius_m": 10}
            out["image_geolocation"] = image_geo_guess

    # 1) Detection
    detections = detect_buildings(img)

    # 2) For each detection: try geolocation sources in order
    for det in detections:
        bbox = det.get("bbox")
        det_entry = det.copy()
        # prepare geolocation result container
        geo_res = None

        # A: EXIF GPS from image: if we have image-level GPS, compute correction offset based on bbox center -> approximate by small pixel shift
        if image_geo_guess:
            # Try to correct small offset: compute pixel offset from center, and approximate meters per pixel
            cx_pix = bbox[0] + bbox[2] / 2.0
            cy_pix = bbox[1] + bbox[3] / 2.0
            img_cx = img_w / 2.0
            img_cy = img_h / 2.0
            dx_pix = cx_pix - img_cx
            dy_pix = cy_pix - img_cy
            # estimate focal px
            focal_px = None
            focal_px = estimate_focal_pixels(exif, img_w)
            if focal_px is None:
                focal_px = max(img_w, img_h)
            # very rough meters per pixel at ground: assume average distance D ~ altitude or 50m if unknown
            approx_alt = None
            if metadata and metadata.get("ins"):
                approx_alt = metadata["ins"].get("alt_m")
            if approx_alt is None:
                approx_alt = 50.0  # fallback estimate
            # angular displacement ~ dx/focal; lateral meters ≈ distance * tan(angle)
            meters_x = approx_alt * math.tan((dx_pix) / focal_px)
            meters_y = approx_alt * math.tan((dy_pix) / focal_px)
            # east = meters_x, north = -meters_y (image y down)
            lat_corr, lon_corr = enu_offset_to_latlon(image_geo_guess["lat"], image_geo_guess["lon"], meters_x, -meters_y)
            geo_res = {"lat": lat_corr, "lon": lon_corr, "confidence": 0.85, "error_radius_m": max(10, approx_alt * 0.2), "method": "exif_corrected"}

        # B: INS projection: if metadata contains camera pose/telemetry
        if geo_res is None and metadata.get("ins"):
            ins = metadata["ins"]
            try:
                cam_lat = float(ins.get("lat"))
                cam_lon = float(ins.get("lon"))
                cam_alt = float(ins.get("alt_m", 0.0))
                yaw = float(ins.get("yaw", 0.0))
                pitch = float(ins.get("pitch", 0.0))
                roll = float(ins.get("roll", 0.0))
                focal_px = None
                # estimate focal_px from metadata if given in mm and sensor_mm
                focal_mm = ins.get("focal_mm")
                sensor_mm = ins.get("sensor_mm", 36.0)
                if focal_mm:
                    try:
                        focal_mm_val = float(focal_mm)
                        focal_px = focal_mm_val * (img_w / float(sensor_mm))
                    except Exception:
                        focal_px = None
                proj = project_bbox_center_to_ground_using_ins(bbox, img_w, img_h, cam_lat, cam_lon, cam_alt, yaw, pitch, roll, focal_px)
                if proj:
                    geo_res = proj
            except Exception:
                geo_res = None

        # C: Visual localization (retrieval + SuperPoint etc.)
        if geo_res is None:
            vis = visual_localization_fallback(img)
            if vis:
                geo_res = vis

        # D: Georeg fallback
        if geo_res is None:
            geo_res = georeg_model_fallback(img)

        # OCR
        ocr_text = run_ocr_if_available(img, bbox)

        # Reverse geocode best guess if possible
        rev = None
        if geo_res and requests is not None:
            try:
                rev = reverse_geocode(geo_res["lat"], geo_res["lon"])
            except Exception:
                rev = None

        det_entry["geolocation"] = geo_res
        det_entry["ocr_text"] = ocr_text
        det_entry["address"] = rev.get("address") if rev else None
        out["detections"].append(det_entry)

    return out


# -------------------------
# Quick test helper
# -------------------------
if __name__ == "__main__":
    # quick local test: load image from disk and run pipeline
    import sys
    if len(sys.argv) < 2:
        print("Usage: python ml_geolocate.py <image_path>")
        sys.exit(1)
    path = sys.argv[1]
    with open(path, "rb") as f:
        b = f.read()
    # sample metadata with INS example
    sample_meta = {
        "ins": {
            "lat": 55.752023,
            "lon": 37.617499,
            "alt_m": 60.0,
            "yaw": 10.0,
            "pitch": -5.0,
            "roll": 0.0,
            "focal_mm": 35.0,
            "sensor_mm": 36.0
        }
    }
    res = process_image_bytes(b, metadata=sample_meta)
    print(json.dumps(res, indent=2, ensure_ascii=False))
