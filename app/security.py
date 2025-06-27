"""CSRF helper functions for ImageProof.

This module provides simple CSRF token generation and
validation without requiring external extensions. A random
token is stored in the user's session and must be included
in modifying requests (POST, PUT, PATCH, DELETE) either as a
form field or via the ``X-CSRFToken`` header.
"""
from __future__ import annotations

import secrets
from flask import abort, request, session
from functools import wraps
from typing import Any, Callable


def generate_csrf_token() -> str:
    """Return the current session's CSRF token, creating one if needed."""
    token = session.get("csrf_token")
    if token is None:
        token = secrets.token_urlsafe(16)
        session["csrf_token"] = token
    return token


def validate_csrf_token() -> None:
    """Abort the request if the CSRF token is missing or invalid."""
    if request.method in {"GET", "HEAD", "OPTIONS"}:
        return
    session_token = session.get("csrf_token")
    form_token = request.form.get("csrf_token") or request.headers.get("X-CSRFToken")
    if not session_token or session_token != form_token:
        abort(400, description="Invalid CSRF token")

def require_login(role: str | None = None) -> Callable[[Callable[..., Any]], Callable[..., Any]]:
    """Dummy login-required decorator for testing."""

    def decorator(func: Callable[..., Any]) -> Callable[..., Any]:
        @wraps(func)
        def wrapper(*args: Any, **kwargs: Any) -> Any:
            return func(*args, **kwargs)

        return wrapper

    return decorator