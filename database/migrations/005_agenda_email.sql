CREATE TABLE IF NOT EXISTS learning_email_templates (
    template_key VARCHAR(60) PRIMARY KEY,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS learning_agenda_email_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    interest_id BIGINT UNSIGNED NOT NULL,
    source_hash CHAR(64) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    provider_id VARCHAR(255) NULL,
    last_error VARCHAR(255) NULL,
    KEY agenda_email_ready (status,available_at,id),
    KEY agenda_email_interest (interest_id,id),
    KEY agenda_email_source (source_hash,created_at),
    FOREIGN KEY (interest_id) REFERENCES agenda_interests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO learning_email_templates (template_key,subject,body) VALUES
('agenda_request','Your requested agenda: {{training_title}}','Hello,

Thank you for your interest in {{training_title}}.

Here is the agenda you requested:
{{agenda_url}}

Best regards,
The Haleef Learning team');

UPDATE learning_faqs SET answer='Use **Request the agenda** near the bottom of the course page. Enter your email to request the PDF link. If the form is not shown, contact us and tell us which programme and dates you are interested in.'
WHERE seed_key='prospectus' AND answer='If a PDF is available for this edition, use **Download the agenda** on this page and enter your email to access it. If that section is not shown, contact us and tell us which programme and dates you are interested in.';
