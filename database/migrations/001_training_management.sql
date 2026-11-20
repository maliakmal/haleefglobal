CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(190) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    website_url VARCHAR(255) NOT NULL,
    resource_domain VARCHAR(255) NULL,
    default_currency CHAR(3) NOT NULL DEFAULT 'AED',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('administrator', 'operations', 'finance') NOT NULL DEFAULT 'operations',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trainers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(190) NOT NULL,
    headline VARCHAR(255) NULL,
    biography MEDIUMTEXT NULL,
    image_path VARCHAR(500) NULL,
    linkedin_url VARCHAR(500) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trainings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(190) NOT NULL,
    title VARCHAR(255) NOT NULL,
    short_description TEXT NULL,
    description MEDIUMTEXT NULL,
    learning_outcomes MEDIUMTEXT NULL,
    audience MEDIUMTEXT NULL,
    image_path VARCHAR(500) NULL,
    cpd_available TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY trainings_brand_slug_unique (brand_id, slug),
    CONSTRAINT trainings_brand_fk FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_editions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    training_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    delivery_mode ENUM('online', 'in_person', 'hybrid') NOT NULL DEFAULT 'online',
    timezone VARCHAR(100) NOT NULL DEFAULT 'Asia/Dubai',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    venue_name VARCHAR(255) NULL,
    venue_address TEXT NULL,
    capacity INT UNSIGNED NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'AED',
    cpd_points DECIMAL(8,2) NULL,
    registration_status ENUM('closed', 'open', 'waitlist') NOT NULL DEFAULT 'closed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY editions_training_idx (training_id),
    KEY editions_dates_idx (starts_at, ends_at),
    CONSTRAINT editions_training_fk FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    CONSTRAINT editions_valid_dates CHECK (ends_at >= starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    delivery_mode ENUM('online', 'in_person', 'hybrid') NOT NULL DEFAULT 'online',
    meeting_provider VARCHAR(50) NULL,
    external_meeting_id VARCHAR(190) NULL,
    host_join_url TEXT NULL,
    attendee_join_url TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT sessions_edition_fk FOREIGN KEY (edition_id) REFERENCES training_editions(id) ON DELETE CASCADE,
    CONSTRAINT sessions_valid_dates CHECK (ends_at >= starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_trainers (
    edition_id BIGINT UNSIGNED NOT NULL,
    trainer_id BIGINT UNSIGNED NOT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (edition_id, trainer_id),
    CONSTRAINT training_trainers_edition_fk FOREIGN KEY (edition_id) REFERENCES training_editions(id) ON DELETE CASCADE,
    CONSTRAINT training_trainers_trainer_fk FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(80) NOT NULL,
    label VARCHAR(150) NOT NULL,
    adapter_class VARCHAR(255) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    config_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY payment_methods_brand_code_unique (brand_id, code),
    CONSTRAINT payment_methods_brand_fk FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id BIGINT UNSIGNED NOT NULL,
    edition_id BIGINT UNSIGNED NOT NULL,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    public_token_hash CHAR(64) NOT NULL UNIQUE,
    purchaser_name VARCHAR(190) NOT NULL,
    purchaser_email VARCHAR(190) NOT NULL,
    purchaser_phone VARCHAR(80) NULL,
    company_name VARCHAR(190) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    subtotal DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    status ENUM('pending_payment', 'payment_review', 'partially_paid', 'paid', 'partially_refunded', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    selected_payment_method VARCHAR(80) NOT NULL,
    notes TEXT NULL,
    payment_due_at DATETIME NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY orders_edition_idx (edition_id),
    KEY orders_email_idx (purchaser_email),
    KEY orders_status_idx (status),
    CONSTRAINT orders_brand_fk FOREIGN KEY (brand_id) REFERENCES brands(id),
    CONSTRAINT orders_edition_fk FOREIGN KEY (edition_id) REFERENCES training_editions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(80) NULL,
    company_name VARCHAR(190) NULL,
    job_title VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY attendees_email_idx (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    edition_id BIGINT UNSIGNED NOT NULL,
    attendee_id BIGINT UNSIGNED NOT NULL,
    attendee_email VARCHAR(190) NOT NULL,
    registration_number VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('pending_payment', 'confirmed', 'cancelled', 'attended', 'no_show') NOT NULL DEFAULT 'pending_payment',
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY registrations_edition_attendee_unique (edition_id, attendee_id),
    UNIQUE KEY registrations_edition_email_unique (edition_id, attendee_email),
    CONSTRAINT registrations_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT registrations_edition_fk FOREIGN KEY (edition_id) REFERENCES training_editions(id),
    CONSTRAINT registrations_attendee_fk FOREIGN KEY (attendee_id) REFERENCES attendees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    payment_method_code VARCHAR(80) NOT NULL,
    provider VARCHAR(80) NULL,
    provider_reference VARCHAR(255) NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    status ENUM('pending', 'submitted', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    metadata_json JSON NULL,
    recorded_by BIGINT UNSIGNED NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY payments_order_idx (order_id),
    KEY payments_reference_idx (provider_reference),
    CONSTRAINT payments_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT payments_recorded_by_fk FOREIGN KEY (recorded_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_evidence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    storage_disk VARCHAR(50) NOT NULL DEFAULT 'private_local',
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT evidence_payment_fk FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    CONSTRAINT evidence_uploaded_by_fk FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id BIGINT UNSIGNED NOT NULL,
    training_id BIGINT UNSIGNED NULL,
    edition_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    asset_type ENUM('agenda', 'brochure', 'trainer_profile', 'course_material', 'other') NOT NULL DEFAULT 'other',
    visibility ENUM('public', 'protected') NOT NULL DEFAULT 'public',
    storage_disk VARCHAR(50) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    public_url VARCHAR(1000) NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT assets_brand_fk FOREIGN KEY (brand_id) REFERENCES brands(id),
    CONSTRAINT assets_training_fk FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE SET NULL,
    CONSTRAINT assets_edition_fk FOREIGN KEY (edition_id) REFERENCES training_editions(id) ON DELETE SET NULL,
    CONSTRAINT assets_created_by_fk FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO brands (code, name, website_url, resource_domain, default_currency)
VALUES
    ('haleef-global', 'Haleef Global', 'https://www.haleefglobal.com', 'https://resources.haleefglobal.com', 'AED'),
    ('haleef-learning', 'Haleef Learning', 'https://www.haleeflearning.com', 'https://resources.haleeflearning.com', 'AED');

INSERT INTO payment_methods (brand_id, code, label, adapter_class, enabled, display_order, config_json)
SELECT id, 'bank_transfer', 'Bank transfer', 'Haleef\\Payments\\BankTransferPaymentMethod', 0, 10,
       JSON_OBJECT('account_name', '', 'bank_name', '', 'iban', '', 'swift', '', 'payment_due_days', 3, 'instructions', 'Use your order number as the payment reference. Your place is confirmed after the Haleef team verifies the transfer.')
FROM brands
WHERE code = 'haleef-learning'
  AND NOT EXISTS (
      SELECT 1 FROM payment_methods existing
      WHERE existing.brand_id = brands.id AND existing.code = 'bank_transfer'
  );

