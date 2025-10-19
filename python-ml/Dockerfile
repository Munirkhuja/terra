FROM pytorch/pytorch:2.8.0-cuda12.9-cudnn9-runtime

WORKDIR /app

COPY requirements.txt .
RUN pip install --upgrade pip
RUN pip install -r requirements.txt

CMD ["python3", "main.py"]