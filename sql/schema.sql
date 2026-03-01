CREATE TABLE IF NOT EXISTS premium_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_user_sessions (
    user_id BIGINT UNSIGNED NOT NULL,
    session_token VARCHAR(128) NOT NULL UNIQUE,
    refreshed_at BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_type ENUM('browser','windows','extension') NOT NULL,
    title VARCHAR(80) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    feature_image VARCHAR(255) NOT NULL,
    seo_description VARCHAR(160) NOT NULL,
    long_description VARCHAR(1000) NOT NULL,
    tool_html LONGTEXT NULL,
    download_url VARCHAR(2048) NULL,
    extension_url VARCHAR(2048) NULL,
    demo_tutorial_url VARCHAR(2048) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    UNIQUE KEY uq_premium_services_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_service_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,
    feature_text VARCHAR(160) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (service_id) REFERENCES premium_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_service_instructions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,
    instruction_text VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (service_id) REFERENCES premium_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    paypal_subscription_id VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(40) NOT NULL,
    last_payment_at BIGINT NULL,
    next_billing_at BIGINT NULL,
    cancelled_at BIGINT NULL,
    last_event VARCHAR(255) NULL,
    last_checked BIGINT NOT NULL DEFAULT 0,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text VARCHAR(800) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    is_favorite TINYINT(1) NOT NULL DEFAULT 0,
    created_at BIGINT NOT NULL,
    approved_at BIGINT NULL,
    UNIQUE KEY uq_review_once (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_paypal_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(255) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    processed_at BIGINT NULL,
    created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_error_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL,
    source VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    context_json LONGTEXT NULL,
    created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    region VARCHAR(120) NULL,
    ip_address VARCHAR(64) NULL,
    created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS premium_cookie_consents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    consent_version VARCHAR(32) NOT NULL,
    preferences_json TEXT NOT NULL,
    created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
