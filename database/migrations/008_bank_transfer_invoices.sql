CREATE TABLE IF NOT EXISTS order_invoices (
    order_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    payment_snapshot JSON NOT NULL,
    pdf MEDIUMBLOB NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_registration_email_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    registration_id BIGINT UNSIGNED NOT NULL UNIQUE,
    recipient VARCHAR(254) NOT NULL,
    subject VARCHAR(240) NOT NULL,
    body TEXT NOT NULL,
    attach_invoice BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    provider_id VARCHAR(255) NULL,
    last_error VARCHAR(255) NULL,
    INDEX ready_jobs (status, available_at, id),
    INDEX order_email_jobs (order_id, id),
    FOREIGN KEY (order_id) REFERENCES order_invoices(order_id),
    FOREIGN KEY (registration_id) REFERENCES registrations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
