ALTER TABLE trainings ADD COLUMN IF NOT EXISTS key_notes MEDIUMTEXT NULL;
ALTER TABLE training_editions ADD COLUMN IF NOT EXISTS publication_status ENUM('draft','published') NOT NULL DEFAULT 'published', ADD COLUMN IF NOT EXISTS image_path VARCHAR(500) NULL, ADD COLUMN IF NOT EXISTS features TEXT NULL, ADD COLUMN IF NOT EXISTS zoom_url VARCHAR(1000) NULL, ADD COLUMN IF NOT EXISTS agenda_asset_id BIGINT UNSIGNED NULL;
ALTER TABLE trainers ADD COLUMN IF NOT EXISTS specialties VARCHAR(500) NULL;
CREATE TABLE IF NOT EXISTS agenda_interests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(254) NOT NULL,
    first_requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY agenda_interest_unique (edition_id, asset_id, email),
    FOREIGN KEY (edition_id) REFERENCES training_editions(id),
    FOREIGN KEY (asset_id) REFERENCES media_assets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
