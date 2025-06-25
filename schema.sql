-- schema.sql: Database schema for ImageProof (Phase 1 data layer)
-- Defines `users`, `images`, and `action_log` tables with appropriate constraints.

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    hashed_password VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    phash CHAR(16) NOT NULL,
    INDEX idx_images_sha256 (sha256),
    INDEX idx_images_phash (phash),
    CONSTRAINT fk_images_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE action_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    image_id INT UNSIGNED,
    action VARCHAR(50) NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    CONSTRAINT fk_action_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_action_image FOREIGN KEY (image_id) REFERENCES images(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
