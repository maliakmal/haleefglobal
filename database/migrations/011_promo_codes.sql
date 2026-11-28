CREATE TABLE IF NOT EXISTS promo_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    percentage TINYINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    training_id BIGINT UNSIGNED NULL,
    private_only TINYINT(1) NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    max_uses INT UNSIGNED NULL,
    CONSTRAINT promo_training_fk FOREIGN KEY (training_id) REFERENCES trainings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promo_code_emails (
    promo_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (promo_id, email),
    CONSTRAINT promo_email_code_fk FOREIGN KEY (promo_id) REFERENCES promo_codes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promo_redemptions (
    order_id BIGINT UNSIGNED PRIMARY KEY,
    promo_id BIGINT UNSIGNED NOT NULL,
    contact_email VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    KEY promo_contact_idx (promo_id, contact_email),
    CONSTRAINT promo_redemption_order_fk FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT promo_redemption_code_fk FOREIGN KEY (promo_id) REFERENCES promo_codes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders ADD COLUMN IF NOT EXISTS promo_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL;
