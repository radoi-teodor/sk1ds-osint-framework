import os
from dataclasses import dataclass
from pathlib import Path


@dataclass
class Settings:
    shared_secret: str = os.getenv("ENGINE_SHARED_SECRET", "dev-secret-change-me")
    host: str = os.getenv("ENGINE_HOST", "127.0.0.1")
    port: int = int(os.getenv("ENGINE_PORT", "8077"))
    transforms_dir: Path = Path(
        os.getenv(
            "ENGINE_TRANSFORMS_DIR",
            str(Path(__file__).resolve().parent.parent / "transforms"),
        )
    )


settings = Settings()
