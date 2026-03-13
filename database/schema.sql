CREATE DATABASE IF NOT EXISTS videoshaq CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE videoshaq;

CREATE TABLE IF NOT EXISTS area_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_key VARCHAR(30) NOT NULL UNIQUE,
    area_label VARCHAR(80) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS videos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_key VARCHAR(30) NOT NULL,
    title VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_area_created (area_key, created_at),
    CONSTRAINT fk_videos_area_key FOREIGN KEY (area_key)
        REFERENCES area_codes(area_key)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO area_codes (area_key, area_label, code_hash)
VALUES
    ('admision', 'Admision', SHA2('ADM-HAQ-2026', 256)),
    ('calidad', 'Calidad', SHA2('CAL-HAQ-2026', 256)),
    ('enfermeria', 'Enfermeria', SHA2('ENF-HAQ-2026', 256)),
    ('systems', 'Sistemas', SHA2('SIS-HAQ-2026', 256))
ON DUPLICATE KEY UPDATE
    area_label = VALUES(area_label),
    code_hash = VALUES(code_hash),
    is_active = 1;
