## File Count Ledger

| File                      | Description                                       |
| ------------------------- | ------------------------------------------------- |
| `pyproject.toml`          | Project metadata, dependencies, and development tool configuration. |
| `app/config.py`           | Application configuration classes (Base, Development, Production) and logging setup. |
| `.pre-commit-config.yaml` | Pre-commit hooks for code formatting (Black, isort), linting (Ruff, Mypy), and security (Bandit). |
| `README.md`               | Project overview and phase-wise file count ledger. |
| **Total (Phase 0)**       | **4/20 files used**                               |
| **Total (Phase 1)**       | **8/20 files used**                               |
| **Total (Phase 3)**       | **11/20 files used**                              |
| **Total (Phase 4)**       | **13/20 files used**                              |

# Project Structure
ImageProof/  
├── app/  
│   ├── app.py  
│   ├── config.py  
│   ├── image_processing.py  
│   ├── watermark.py  
│   ├── models.py  
│   └── certificate.py  
├── tests/  
│   ├── test_image_pipeline.py  
│   └── test_certificate.py  
├── templates/  
├── static/  
├── schema.sql  
├── seed_data.sql  
├── pyproject.toml  
├── .pre-commit-config.yaml  
└── README.md  
