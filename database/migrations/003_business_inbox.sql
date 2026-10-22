-- Public inquiry, consultation, and recruitment submissions.
-- Consent is recorded explicitly; contact details are never used for automated
-- advice, payments, policy issuance, binding quotes, or claims handling.

CREATE TABLE IF NOT EXISTS inquiries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inquiry_type ENUM('general', 'consultation', 'recruitment') NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    preferred_contact ENUM('email', 'phone', 'whatsapp') NOT NULL DEFAULT 'email',
    topic VARCHAR(120) NOT NULL DEFAULT '',
    preferred_time VARCHAR(120) NOT NULL DEFAULT '',
    location VARCHAR(120) NOT NULL DEFAULT '',
    experience VARCHAR(500) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    consent_at DATETIME NOT NULL,
    privacy_version VARCHAR(40) NOT NULL,
    source VARCHAR(80) NOT NULL DEFAULT 'direct',
    status ENUM('new', 'in_review', 'contacted', 'scheduled', 'closed', 'spam') NOT NULL DEFAULT 'new',
    internal_notes TEXT NULL,
    handled_by BIGINT UNSIGNED NULL,
    first_reviewed_at DATETIME NULL,
    first_contacted_at DATETIME NULL,
    closed_at DATETIME NULL,
    ip_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inquiries_status_created (status, created_at),
    KEY idx_inquiries_type_created (inquiry_type, created_at),
    KEY idx_inquiries_source (source),
    KEY idx_inquiries_created_at (created_at),
    KEY idx_inquiries_handled_by (handled_by),
    CONSTRAINT fk_inquiries_handled_by FOREIGN KEY (handled_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
