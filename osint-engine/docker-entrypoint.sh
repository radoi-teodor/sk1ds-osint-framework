#!/bin/bash
set -e

echo "Starting OSINT Engine on ${ENGINE_HOST:-0.0.0.0}:${ENGINE_PORT:-8077}..."
echo "  transforms: ${ENGINE_TRANSFORMS_DIR:-./transforms}"
echo "  generators: ${ENGINE_GENERATORS_DIR:-./generators}"
echo "  uploads:    ${ENGINE_UPLOADS_DIR:-/app/storage/app/uploads}"

exec python -m osint_engine.main
