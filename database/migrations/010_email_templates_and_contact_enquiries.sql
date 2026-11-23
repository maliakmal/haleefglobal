ALTER TABLE learning_agenda_email_jobs ADD COLUMN IF NOT EXISTS html_body MEDIUMTEXT NULL;
ALTER TABLE order_registration_email_jobs ADD COLUMN IF NOT EXISTS html_body MEDIUMTEXT NULL;

INSERT IGNORE INTO learning_email_templates (template_key,subject,body) VALUES
('registration_contact','Registration received: {{training_title}}','Hi {{first_name}},

We received your registration for **{{training_title}}**.

- Schedule: {{schedule}}
- Format: {{mode}}
- Booking: {{booking_reference}}
- Your registration: {{registration_reference}}

Your seat is pending until Haleef verifies the bank transfer.

Your invoice **{{invoice_number}}** is attached. Please transfer **{{amount}}** by {{payment_due}} and use {{payment_reference}} as the payment reference.

Questions? Reply to this email.

Haleef Learning'),
('registration_delegate','Registration received: {{training_title}}','Hi {{first_name}},

We received your registration for **{{training_title}}**.

- Schedule: {{schedule}}
- Format: {{mode}}
- Booking: {{booking_reference}}
- Your registration: {{registration_reference}}

Your seat is pending until Haleef verifies the bank transfer. Payment instructions have been sent separately to the booking contact.

Questions? Reply to this email.

Haleef Learning'),
('contact_staff','New Haleef website enquiry','## New website enquiry

- Name: {{name}}
- Email: {{email}}
- Company: {{company}}
- Type: {{type}}
- Location: {{location}}

**Brief**

{{message}}

View this enquiry in Admin: {{enquiry_url}}');

CREATE TABLE IF NOT EXISTS contact_enquiries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(254) NOT NULL,
    company VARCHAR(300) NOT NULL,
    enquiry_type VARCHAR(200) NOT NULL,
    location VARCHAR(300) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new','in_progress','closed') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY contact_status (status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_email_jobs (
    enquiry_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    recipient VARCHAR(254) NOT NULL,
    subject VARCHAR(240) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    html_body MEDIUMTEXT NOT NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    provider_id VARCHAR(255) NULL,
    last_error VARCHAR(255) NULL,
    KEY contact_email_ready (status,available_at,enquiry_id),
    FOREIGN KEY (enquiry_id) REFERENCES contact_enquiries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
