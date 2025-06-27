# 🖼️ ImageProof

_ImageProof_ is a secure, auditable image submission and certification system designed for legal, research, and verification contexts. Built with **Flask**, **MariaDB**, and **Pillow**, the system captures images, generates perceptual hashes, embeds optional watermarks, and produces digitally signed PDF certificates for verified media.

# 🖼️ ImageProof

_ImageProof_ is a secure, auditable image submission and certification system designed for legal, research, and verification contexts. Built with **Flask**, **MariaDB**, and **Pillow**, the system captures images, generates perceptual hashes, embeds optional watermarks, and produces digitally signed PDF certificates for verified media.

Ideal for organizations needing chain-of-custody, tamper-evident submission workflows—such as journalism, forensics, field research, or digital art authentication.

---

## 🚀 Project Status

🟢 **Active Development**  
📈 **Current Phase: 5 — Admin Tools & User Management**  
🧪 **Internal Testing: Alpha stage**  
🛠️ **Deployment: Apache2 + mod_wsgi on Ubuntu 24.04**

---

## 🗺️ Roadmap

### ✅ Phase 0 – Scaffolding
- `pyproject.toml`, config, pre-commit, and README
- CI-ready and clean code linting

### ✅ Phase 1 – Data Layer
- SQL schema and seed data
- SQLAlchemy models with ORM relationships
- DB connection logic and scoped sessions

### ✅ Phase 2 – Core Functionality
- Image upload, hashing (SHA256, pHash)
- Basic watermarking via Pillow
- CRUD routes and API structure

### ✅ Phase 3 – Frontend & Flask Views
- Basic HTML interface with upload forms
- User session handling and CSRF protection
- Image view & download pages

### ✅ Phase 4 – Certificate & Packaging
- Generate signed PDF certificates
- Embed metadata (timestamp, hash, watermark status)
- Optional QR code for verification
- Bundle images + certs into downloadable ZIP packages

### 🔄 Phase 5 – Admin Tools & User Management *(in progress)*
- Role-based access (admin/user separation)
- Activity logging & audit trails
- Admin dashboard with user and image oversight
- Authentication features and permissions

### 🔐 Phase 6 – Security & Deployment Hard

## 📂 Project Structure

```

├── app/
│   ├── app.py               # Flask app factory
│   ├── config.py            # Env-based configuration
│   ├── models.py            # SQLAlchemy models
│   ├── image\_processing.py  # Upload, hash, watermark
│   ├── certificate.py       # PDF/ZIP generator (Phase 4)
│   └── watermark.py         # Watermark logic
├── tests/
│   ├── test\_image\_pipeline.py
│   └── test\_certificate.py  # PDF/ZIP unit tests
├── schema.sql               # Database schema
├── seed\_data.sql            # Initial test data
├── pyproject.toml           # Tooling & dependencies
├── .pre-commit-config.yaml  # Linting & security hooks
└── README.md                # This file

````

---

## ⚙️ Technologies

- **Python 3.12+**, Flask
- **MariaDB 10.11+**, SQLAlchemy ORM
- **Pillow** for image handling
- **WeasyPrint** for PDF generation
- **zlib**, `secrets`, `hashlib`, `uuid`, `zipfile`

---

## 🧪 Testing

Tests are written using `pytest`. Use the following:

```bash
pytest tests/
````

Ensure you have a test database configured. A `docker-compose` file or local `.env` loader may be added in future phases.

---

## 🛡️ Security & Privacy

* CSRF protection without external extensions
* Scoped database sessions per request
* Minimal dependency attack surface
* Complies with data minimization and encryption-at-rest best practices (deployment-specific)

---

## 🤝 Contributing

Contributions welcome. Please:

* Use `black`, `ruff`, and `mypy` per `.pre-commit-config.yaml`
* Write isolated unit tests for new functionality
* Keep total tracked files within the project’s strict 20-file cap

To propose a feature or fix, open an issue or submit a pull request.

---

## 📜 License

MIT License — see `LICENSE` file.

---

## 🧭 Maintainer

Jeff Weese
jweese74@gmail.com