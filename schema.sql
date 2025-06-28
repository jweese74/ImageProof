/* ==== USERS & AUTH ==== */
CREATE TABLE users (
  user_id        CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  email          VARCHAR(190)  NOT NULL UNIQUE,
  password_hash  VARCHAR(255)  NOT NULL,
  display_name   VARCHAR(100),
  is_admin       TINYINT(1)    DEFAULT 0,
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  last_login     TIMESTAMP     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ==== IMAGES & THUMBNAILS ==== */
CREATE TABLE images (
  image_id        CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id         CHAR(36)  NOT NULL,
  original_path   VARCHAR(255) NOT NULL,
  thumbnail_path  VARCHAR(255) NOT NULL,
  filesize        INT UNSIGNED NOT NULL,
  width           INT UNSIGNED,
  height          INT UNSIGNED,
  mime_type       VARCHAR(50),
  sha256          CHAR(64),
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  watermark_id    CHAR(36)     NULL,
  license_id      CHAR(36)     NULL,
  FOREIGN KEY (user_id)      REFERENCES users(user_id)      ON DELETE CASCADE,
  FOREIGN KEY (watermark_id) REFERENCES watermarks(watermark_id) ON DELETE SET NULL,
  FOREIGN KEY (license_id)   REFERENCES licenses(license_id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ==== USER-OWNED WATERMARKS ==== */
CREATE TABLE watermarks (
  watermark_id  CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id       CHAR(36) NOT NULL,
  filename      VARCHAR(255) NOT NULL,
  path          VARCHAR(255) NOT NULL,
  is_default    TINYINT(1)   DEFAULT 0,
  uploaded_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ==== USER-OWNED LICENSE TEMPLATES ==== */
CREATE TABLE licenses (
  license_id   CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id      CHAR(36) NOT NULL,
  name         VARCHAR(120) NOT NULL,
  text_blob    TEXT        NOT NULL,
  is_default   TINYINT(1)  DEFAULT 0,
  created_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ==== OPTIONAL: PER-RUN ZIP BUNDLES ==== */
CREATE TABLE processing_runs (
  run_id      CHAR(36) PRIMARY KEY,
  user_id     CHAR(36) NOT NULL,
  zip_path    VARCHAR(255) NOT NULL,
  created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
