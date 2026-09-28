CREATE DATABASE IF NOT EXISTS pnp_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pnp_portal;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(190) NOT NULL,
    file_name VARCHAR(190) NOT NULL,
    from_unit VARCHAR(190) NULL,
    to_unit VARCHAR(190) NULL,
    document_type ENUM('Correspondence', 'PNP Radio Message', 'Invistigation Report') NOT NULL DEFAULT 'Correspondence',
    stl_type VARCHAR(100) NULL,
    priority VARCHAR(50) NULL,
    document_content LONGTEXT NULL,
    date_in DATE NULL,
    action_requested VARCHAR(255) NULL,
    remarks TEXT NULL,
    status ENUM('for_action', 'draft', 'within_office', 'outside_office', 'archived') NOT NULL DEFAULT 'for_action',
    created_at DATE NOT NULL,
    created_by INT UNSIGNED NULL,
    CONSTRAINT fk_documents_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

