-- ======================================================================
--  ImageProof schema – 2025-06-28
--  All tables use CHAR(36) UUID primary keys, utf8mb4, InnoDB.
-- ======================================================================

/* ----------------------------------------------------------------------
   USERS & AUTH
------------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS users (
  user_id        CHAR(36)  PRIMARY KEY DEFAULT (UUID()),
  email          VARCHAR(190) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  display_name   VARCHAR(100),
  is_admin       TINYINT(1)    DEFAULT 0,
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  last_login     TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ----------------------------------------------------------------------
   IMAGES & THUMBNAILS
   (NB: If an earlier `images` table exists, migrate data then DROP it
        before running this CREATE, or ALTER it to match the new spec.)
------------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS images (
  image_id        CHAR(36)  PRIMARY KEY DEFAULT (UUID()),
  user_id         CHAR(36)  NOT NULL,
  original_path   VARCHAR(255) NOT NULL,
  thumbnail_path  VARCHAR(255) NOT NULL,
  filesize        INT UNSIGNED NOT NULL,
  width           INT UNSIGNED,
  height          INT UNSIGNED,
  mime_type       VARCHAR(50),
  sha256          CHAR(64),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  watermark_id    CHAR(36) NULL,
  license_id      CHAR(36) NULL,
  CONSTRAINT fk_images_user     FOREIGN KEY (user_id)
                               REFERENCES users(user_id)
                               ON DELETE CASCADE,
  CONSTRAINT fk_images_watermark FOREIGN KEY (watermark_id)
                               REFERENCES watermarks(watermark_id)
                               ON DELETE SET NULL,
  CONSTRAINT fk_images_license  FOREIGN KEY (license_id)
                               REFERENCES licenses(license_id)
                               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ----------------------------------------------------------------------
   USER-OWNED WATERMARKS
------------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS watermarks (
  watermark_id  CHAR(36)  PRIMARY KEY DEFAULT (UUID()),
  user_id       CHAR(36)  NOT NULL,
  filename      VARCHAR(255) NOT NULL,
  path          VARCHAR(255) NOT NULL,
  is_default    TINYINT(1)   DEFAULT 0,
  uploaded_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_watermarks_user FOREIGN KEY (user_id)
                               REFERENCES users(user_id)
                               ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ----------------------------------------------------------------------
   USER-OWNED LICENSE TEMPLATES
------------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS licenses (
  license_id   CHAR(36)  PRIMARY KEY DEFAULT (UUID()),
  user_id      CHAR(36)  NOT NULL,
  name         VARCHAR(120) NOT NULL,
  text_blob    TEXT        NOT NULL,
  is_default   TINYINT(1)  DEFAULT 0,
  created_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_licenses_user FOREIGN KEY (user_id)
                             REFERENCES users(user_id)
                             ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ----------------------------------------------------------------------
   OPTIONAL: PER-RUN ZIP BUNDLES
------------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS processing_runs (
  run_id      CHAR(36)  PRIMARY KEY,
  user_id     CHAR(36)  NOT NULL,
  zip_path    VARCHAR(255) NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_runs_user FOREIGN KEY (user_id)
                         REFERENCES users(user_id)
                         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ----------------------------------------------------------------------
   SEED OR TEST DATA (optional)
------------------------------------------------------------------------ */
-- INSERT INTO users (email, password_hash, display_name, is_admin)
-- VALUES ('admin@example.com', '$2y$10$......', 'Site Admin', 1);

-- ======================================================================
--  End of schema
-- ======================================================================
