CREATE TABLE IF NOT EXISTS learning_faqs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seed_key VARCHAR(60) NULL UNIQUE,
    question VARCHAR(255) NOT NULL,
    answer MEDIUMTEXT NOT NULL,
    default_enabled TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS learning_edition_faqs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    edition_id BIGINT UNSIGNED NOT NULL,
    faq_id BIGINT UNSIGNED NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    question VARCHAR(255) NULL,
    answer MEDIUMTEXT NULL,
    sort_order INT UNSIGNED NULL,
    UNIQUE KEY edition_shared_faq (edition_id,faq_id),
    FOREIGN KEY (edition_id) REFERENCES training_editions(id) ON DELETE CASCADE,
    FOREIGN KEY (faq_id) REFERENCES learning_faqs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO learning_faqs (seed_key, question, answer, default_enabled, sort_order) VALUES
('dates','What are the dates, timezone and delivery format?', 'Check the booking card for this edition’s start and end times, timezone and delivery format. For an online session, convert the listed time to your local timezone before booking. For an in-person or hybrid edition, check the venue details on this page or contact Haleef if you need to confirm access arrangements.',1,10),
('registration','How do I book and confirm my place?', 'When registration is open, select **Book your seat** and complete the attendee details. If bank transfer is offered, your booking page explains how to pay and which reference to use. Your place is confirmed after Haleef verifies the payment. Keep your private booking link so you can check the status later.',1,20),
('joining','Where do I find the joining instructions?', 'For an online or hybrid edition using Zoom, check your private booking page after payment confirmation. The attendee link appears there once Haleef has added it. Keep that link private. If it has not appeared close to the start time, or if you need help finding the venue, [contact Haleef](https://www.haleefglobal.com/contact).',1,30),
('prospectus','Where can I see the full agenda?', 'If a PDF is available for this edition, use **Download the agenda** on this page and enter your email to access it. If that section is not shown, contact us and tell us which programme and dates you are interested in.',1,40),
('credit','Will I receive CPD credit or a certificate?', 'CPD and certificate arrangements vary by edition. Check the course highlights and booking details for any confirmed credit or certificate information. If neither is listed, contact Haleef before booking rather than assuming it is included.',1,50),
('invoice','Can my company pay or request an invoice?', 'Ask Haleef before paying if your company needs an invoice, a purchase order, or separate billing details. Include the edition dates and your booking reference if you already have one. Do not send bank credentials or card details by email.',1,60),
('inhouse','Can you run this programme for our team?', 'Contact Haleef with your team size, preferred dates, delivery location or timezone, and the outcomes you want to achieve. The team can confirm whether a private edition is available and outline the next steps.',1,70),
('recordings','Will a recording or course materials be available?', 'Materials and recordings depend on the edition and trainer arrangements. Check the edition highlights or ask Haleef before booking if access to a recording or specific material is important to you.',0,80);
