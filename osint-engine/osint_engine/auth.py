"""Shared-secret authentication for engine endpoints."""

import hmac

from fastapi import Header, HTTPException, status

from osint_engine.config import settings


async def verify_secret(x_engine_secret: str = Header(default="")) -> None:
    secret = settings.shared_secret or ""
    if not secret:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="ENGINE_SHARED_SECRET not configured",
        )
    if not x_engine_secret or not hmac.compare_digest(x_engine_secret, secret):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing X-Engine-Secret header",
        )
