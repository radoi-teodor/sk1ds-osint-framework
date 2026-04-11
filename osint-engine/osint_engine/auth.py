"""Shared-secret authentication for engine endpoints."""

import hmac

from fastapi import Header, HTTPException, status

from osint_engine.config import settings


async def verify_secret(x_engine_secret: str = Header(default="")) -> None:
    if not hmac.compare_digest(x_engine_secret or "", settings.shared_secret or ""):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing X-Engine-Secret header",
        )
