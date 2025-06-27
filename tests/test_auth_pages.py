from app.app import create_app


def test_login_page_loads():
    app = create_app()
    client = app.test_client()
    resp = client.get("/login")
    assert resp.status_code == 200
    assert b"Log In to ImageProof" in resp.data


def test_signup_page_loads():
    app = create_app()
    client = app.test_client()
    resp = client.get("/signup")
    assert resp.status_code == 200
    assert b"Create an Account" in resp.data


def test_nav_links_exist():
    app = create_app()
    client = app.test_client()
    resp = client.get("/")
    assert b'href="/login"' in resp.data
    assert b'href="/signup"' in resp.data