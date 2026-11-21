ALTER TABLE media_assets
    ADD COLUMN sha256 CHAR(64) NULL AFTER size_bytes,
    ADD COLUMN archived_at DATETIME NULL AFTER is_published,
    ADD COLUMN replaced_by_id BIGINT UNSIGNED NULL AFTER archived_at,
    ADD KEY media_assets_published_idx (is_published, created_at),
    ADD CONSTRAINT assets_replaced_by_fk FOREIGN KEY (replaced_by_id) REFERENCES media_assets(id) ON DELETE SET NULL;
