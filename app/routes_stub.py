from flask import Blueprint, render_template, request
import logging

stub_bp = Blueprint(
    "stub",
    __name__,
    template_folder="../templates",
)

logger = logging.getLogger(__name__)

@stub_bp.route("/_stub/<path:name>")
def stub(name):
    logger.warning(f"Accessed stub route: {name}")
    try:
        return render_template("stub.html", name=name, path=request.path), 501
    except Exception as e:
        logger.debug(f"Stub template not found or failed to render: {e}")
        return f"Placeholder for {name}", 501
