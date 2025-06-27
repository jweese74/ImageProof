from flask import Blueprint

stub_bp = Blueprint(
    "stub",
    __name__,
    template_folder="../templates",
)


@stub_bp.route("/_stub/<path:name>")
def stub(name):
    return f"Placeholder for {name}", 501