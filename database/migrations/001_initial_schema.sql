-- AXA-Kenneth initial schema
-- All tables are scoped to the axa_kenneth_db database.

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    storage_key VARCHAR(80) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    alt_text VARCHAR(190) NULL,
    title VARCHAR(190) NULL,
    checksum CHAR(64) NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_storage_key (storage_key),
    KEY idx_media_created_at (created_at),
    CONSTRAINT fk_media_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(170) NOT NULL,
    icon VARCHAR(16) NOT NULL DEFAULT '•',
    summary VARCHAR(500) NOT NULL DEFAULT '',
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_categories_slug (slug),
    KEY idx_service_categories_published (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    product_type VARCHAR(120) NOT NULL,
    summary TEXT NULL,
    official_url VARCHAR(500) NULL,
    image_media_id BIGINT UNSIGNED NULL,
    image_url VARCHAR(1000) NULL,
    image_alt VARCHAR(190) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    verification_status ENUM('unverified', 'verified', 'rejected') NOT NULL DEFAULT 'unverified',
    verified_at DATETIME NULL,
    verified_by BIGINT UNSIGNED NULL,
    verification_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_category (category_id),
    KEY idx_products_public (status, verification_status, sort_order),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_products_image FOREIGN KEY (image_media_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_products_verified_by FOREIGN KEY (verified_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advisor_profiles (
    id TINYINT UNSIGNED NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    title VARCHAR(160) NOT NULL DEFAULT '',
    organization VARCHAR(160) NOT NULL DEFAULT '',
    tagline VARCHAR(255) NOT NULL DEFAULT '',
    bio TEXT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    phone VARCHAR(40) NOT NULL DEFAULT '',
    whatsapp VARCHAR(40) NOT NULL DEFAULT '',
    messenger_url VARCHAR(500) NOT NULL DEFAULT '',
    facebook_url VARCHAR(500) NOT NULL DEFAULT '',
    photo_media_id BIGINT UNSIGNED NULL,
    photo_url VARCHAR(1000) NULL,
    photo_alt VARCHAR(190) NOT NULL DEFAULT 'Kenneth',
    title_verified_at DATETIME NULL,
    title_verified_by BIGINT UNSIGNED NULL,
    authorization_verified_at DATETIME NULL,
    authorization_verified_by BIGINT UNSIGNED NULL,
    verification_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_advisor_photo FOREIGN KEY (photo_media_id) REFERENCES media (id) ON DELETE SET NULL,
    CONSTRAINT fk_advisor_title_verified_by FOREIGN KEY (title_verified_by) REFERENCES admins (id) ON DELETE SET NULL,
    CONSTRAINT fk_advisor_auth_verified_by FOREIGN KEY (authorization_verified_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created_at (created_at),
    KEY idx_audit_admin (admin_id),
    KEY idx_audit_action (action),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action VARCHAR(64) NOT NULL,
    identifier_hash CHAR(64) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_action_identifier (action, identifier_hash),
    KEY idx_rate_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
