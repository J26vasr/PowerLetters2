FROM python:3.12-slim

WORKDIR /app

RUN pip install --no-cache-dir \
    fastapi==0.115.6 \
    uvicorn[standard]==0.32.1 \
    httpx==0.28.1 \
    pymysql==1.1.1 \
    cryptography==44.0.0 \
    pydantic==2.10.4

COPY *.py /app/

EXPOSE 8091

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8091"]
