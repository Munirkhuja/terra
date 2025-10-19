"""
ML Kafka Worker (consumer + processor) - Python

Files included (single-file module):
- Consumer that reads tasks from INPUT_TOPIC (JSON messages)
- Processes image: downloads from S3 (or local path), extracts EXIF, runs placeholder detection function (to be replaced with real ML call), computes geolocation from EXIF if available, saves result to Postgres and emits result to OUTPUT_TOPIC

Environment variables (required):
- KAFKA_BOOTSTRAP_SERVERS (comma-separated)
- KAFKA_CONSUMER_GROUP (default: ml-worker-group)
- KAFKA_INPUT_TOPIC (default: images.tasks)
- KAFKA_OUTPUT_TOPIC (default: images.results)
- S3_ENDPOINT_URL (optional, for MinIO/S3)
- S3_ACCESS_KEY
- S3_SECRET_KEY
- S3_BUCKET
- WORKER_ID (optional)

Notes:
- Uses kafka-python for simplicity.

Requirements (pip):
kafka-python
boto3
Pillow
psycopg2-binary
python-dotenv (optional)
"""

import os
import sys
import json
import time
import signal
import logging
from ml_geolocate import process_image_bytes
from io import BytesIO
from typing import Dict, Any, Optional

from kafka import KafkaConsumer, KafkaProducer
import boto3
from PIL import Image, ExifTags

# --- Logging ---
logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger('ml-kafka-worker')

# --- Configuration ---
KAFKA_BOOTSTRAP = os.getenv('KAFKA_BOOTSTRAP_SERVERS', os.getenv('KAFKA_BROKER', 'localhost:9092'))
KAFKA_GROUP = os.getenv('KAFKA_CONSUMER_GROUP', 'ml-worker-group')
KAFKA_INPUT_TOPIC = os.getenv('KAFKA_INPUT_TOPIC', 'images.tasks')
KAFKA_OUTPUT_TOPIC = os.getenv('KAFKA_OUTPUT_TOPIC', 'images.results')
WORKER_ID = os.getenv('WORKER_ID', 'worker-1')

S3_ENDPOINT = os.getenv('MINIO_URL')
S3_ACCESS = os.getenv('MINIO_ACCESS_KEY')
S3_SECRET = os.getenv('MINIO_SECRET_KEY')
S3_BUCKET = os.getenv('MINIO_BUCKET')

# --- Kafka clients ---
producer: Optional[KafkaProducer] = None
consumer: Optional[KafkaConsumer] = None
s3_client = None
running = True


def init_kafka():
    global producer, consumer
    try:
        logger.info('Connecting to Kafka %s', KAFKA_BOOTSTRAP)
        bootstrap_list = [s.strip() for s in KAFKA_BOOTSTRAP.split(',') if s.strip()]
        producer = KafkaProducer(
            bootstrap_servers=bootstrap_list,
            value_serializer=lambda v: json.dumps(v).encode('utf-8'),
            retries=5
        )
        consumer = KafkaConsumer(
            KAFKA_INPUT_TOPIC,
            bootstrap_servers=bootstrap_list,
            group_id=KAFKA_GROUP,
            value_deserializer=lambda m: json.loads(m.decode('utf-8')),
            auto_offset_reset='earliest',
            enable_auto_commit=True,
            consumer_timeout_ms=1000
        )
        logger.info('Kafka initialized. Listening on topic %s', KAFKA_INPUT_TOPIC)
    except Exception:
        logger.exception('Failed to initialize Kafka - check KAFKA_BOOTSTRAP_SERVERS and network')
        raise


def init_s3_client():
    if not S3_ACCESS or not S3_SECRET:
        logger.warning('S3 credentials not provided; S3 access disabled')
        return None
    session = boto3.session.Session()
    s3 = session.client('s3', endpoint_url=S3_ENDPOINT,
                        aws_access_key_id=S3_ACCESS,
                        aws_secret_access_key=S3_SECRET)
    return s3


def download_image(s3_client, s3_key: str) -> bytes:
    if s3_client is None:
        if os.path.exists(s3_key):
            with open(s3_key, 'rb') as f:
                return f.read()
        raise RuntimeError(f'No S3 client and file not found: {s3_key}')

    if s3_key.startswith('s3://'):
        _, _, path = s3_key.partition('s3://')
        parts = path.split('/', 1)
        bucket = parts[0]
        key = parts[1] if len(parts) > 1 else ''
    else:
        bucket = S3_BUCKET
        key = s3_key

    logger.info('Downloading s3://%s/%s', bucket, key)
    obj = s3_client.get_object(Bucket=bucket, Key=key)
    return obj['Body'].read()



def emit_result(result: Dict[str, Any]):
    if producer is None:
        logger.warning('Producer not initialized; cannot emit result')
        return
    producer.send(KAFKA_OUTPUT_TOPIC, value=result)
    producer.flush()
    logger.info('Emitted result for image_id=%s', result.get('image_id'))


def process_message(msg: Dict[str, Any]):
    image_id = str(msg.get('image_id', ''))
    image_url = msg.get('image_url')
    metadata = msg.get('metadata', {})

    if not image_url:
        logger.error('No image_url in task: %s', msg)
        return

    try:
        image_bytes = download_image(s3_client, image_url)
    except Exception as e:
        logger.exception('Failed download image: %s', e)
        return

    try:
        ml_results = process_image_bytes(image_bytes, metadata=metadata)
    except Exception as e:
        logger.exception('ML processing failed: %s', e)
        return

    for res in ml_results:
        out = {
            'image_id': image_id,
            'detection': res.get('detection'),
            'geolocation': res.get('geolocation'),
            'address': res.get('address'),
            'metadata': metadata,
            'worker': WORKER_ID,
            'processed_at': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
        }
        try:
            emit_result(out)
        except Exception:
            logger.exception('Failed to emit result')

    logger.info('Processed image %s -> %d detections', image_id, len(ml_results))


def stop(signum, frame):
    global running
    logger.info('Signal %s received, shutting down...', signum)
    running = False


signal.signal(signal.SIGINT, stop)
signal.signal(signal.SIGTERM, stop)


def main():
    global s3_client
    init_kafka()
    s3_client = init_s3_client()

    if consumer is None:
        logger.error('Consumer not initialized')
        sys.exit(1)

    logger.info('Worker %s started, polling...', WORKER_ID)
    while running:
        try:
            for message in consumer:
                if not running:
                    break
                try:
                    msg = message.value
                    logger.info('Received task: %s', msg.get('image_id'))
                    process_message(msg)
                except Exception:
                    logger.exception('Failed to process message')
            time.sleep(0.5)
        except Exception:
            logger.exception('Error in main loop; sleeping 5s')
            time.sleep(5)

    logger.info('Closing consumer/producer')
    try:
        if consumer:
            consumer.close()
        if producer:
            producer.flush()
            producer.close()
    except Exception:
        pass
    logger.info('Shutdown complete')


if __name__ == '__main__':
    main()
