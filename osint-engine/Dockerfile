FROM python:3.12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        gcc libffi-dev curl nmap \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .
RUN pip install --no-cache-dir ".[extras]"

RUN mkdir -p /app/storage/app/uploads

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

EXPOSE 8077

HEALTHCHECK --interval=5s --timeout=5s --retries=5 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:8077/health')" || exit 1

ENTRYPOINT ["/docker-entrypoint.sh"]
