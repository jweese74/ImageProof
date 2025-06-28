-- 1. Create a new database (adjust name as desired)
CREATE DATABASE IF NOT EXISTS infinite_image_tools
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- 2. Switch to the newly created database
USE infinite_image_tools;

-------------------------------------------------------------------------------
-- 3. Users Table
-------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,  -- hashed password
    user_role ENUM('guest','registered','admin') NOT NULL DEFAULT 'registered',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT users_username_uq UNIQUE (username),
    CONSTRAINT users_email_uq UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-------------------------------------------------------------------------------
-- 4. Images Table
-------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS images (
    image_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,

    -- Basic File Info
    file_name VARCHAR(255) NOT NULL,
    directory VARCHAR(500) NOT NULL,
    file_path VARCHAR(800) NULL,  -- RECOMMENDED: store full path if desired
    file_size DECIMAL(10,2) NOT NULL, -- MB or use BIGINT for bytes
    file_modification_dt DATETIME NOT NULL,
    file_access_dt DATETIME NOT NULL,
    file_inode_change_dt DATETIME NOT NULL,
    file_permissions VARCHAR(50) DEFAULT NULL,

    -- Image/EXIF-Specific Info
    file_type VARCHAR(50) DEFAULT NULL,
    file_type_extension VARCHAR(10) DEFAULT NULL,
    mime_type VARCHAR(50) DEFAULT NULL,
    image_width INT UNSIGNED DEFAULT NULL,
    image_height INT UNSIGNED DEFAULT NULL,
    bit_depth TINYINT UNSIGNED DEFAULT NULL,
    colour_type VARCHAR(50) DEFAULT NULL,
    compression VARCHAR(50) DEFAULT NULL,
    filter VARCHAR(50) DEFAULT NULL,
    interlace VARCHAR(50) DEFAULT NULL,

    white_point_x DECIMAL(5,4) DEFAULT NULL,
    white_point_y DECIMAL(5,4) DEFAULT NULL,
    red_x DECIMAL(5,4) DEFAULT NULL,
    red_y DECIMAL(5,4) DEFAULT NULL,
    green_x DECIMAL(5,4) DEFAULT NULL,
    green_y DECIMAL(5,4) DEFAULT NULL,
    blue_x DECIMAL(5,4) DEFAULT NULL,
    blue_y DECIMAL(5,4) DEFAULT NULL,

    background_colour VARCHAR(50) DEFAULT NULL,
    modify_date DATETIME DEFAULT NULL,
    by_line VARCHAR(255) DEFAULT NULL,
    copyright_notice VARCHAR(500) DEFAULT NULL,
    application_record_version VARCHAR(50) DEFAULT NULL,
    xmp_toolkit VARCHAR(100) DEFAULT NULL,
    intellectual_genre VARCHAR(500) DEFAULT NULL,
    creator VARCHAR(255) DEFAULT NULL,
    date_created DATE DEFAULT NULL, 
    description TEXT DEFAULT NULL,
    rights VARCHAR(500) DEFAULT NULL,

    title VARCHAR(500) DEFAULT NULL,
    authors_position VARCHAR(255) DEFAULT NULL,
    headline VARCHAR(500) DEFAULT NULL,
    document_id VARCHAR(255) DEFAULT NULL,
    instance_id VARCHAR(255) DEFAULT NULL,
    marked TINYINT(1) DEFAULT 0,  -- store True/False as 1/0
    web_statement VARCHAR(500) DEFAULT NULL,

    image_size VARCHAR(50) DEFAULT NULL,
    megapixels DECIMAL(6,2) DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Key
    CONSTRAINT fk_images_user_id
      FOREIGN KEY (user_id) REFERENCES users(user_id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-------------------------------------------------------------------------------
-- 5. Subjects Table
-------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
    subject_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_text VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-------------------------------------------------------------------------------
-- 6. Image_Subject (Junction Table for Many-to-Many)
-------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS image_subject (
    image_subject_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,

    CONSTRAINT fk_image_subject_image_id
      FOREIGN KEY (image_id) REFERENCES images(image_id)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    CONSTRAINT fk_image_subject_subject_id
      FOREIGN KEY (subject_id) REFERENCES subjects(subject_id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-------------------------------------------------------------------------------
-- 7. Image_Actions_Log (Optional, For Tool Logs/History)
-------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS image_actions_log (
    log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action_desc VARCHAR(255) DEFAULT NULL,  -- e.g. "Tool 1 Resize"
    message TEXT DEFAULT NULL,             -- optional details or status
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Keys
    CONSTRAINT fk_log_image_id
      FOREIGN KEY (image_id) REFERENCES images(image_id)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    CONSTRAINT fk_log_user_id
      FOREIGN KEY (user_id) REFERENCES users(user_id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- End of schema
