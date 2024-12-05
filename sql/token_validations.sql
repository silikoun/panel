CREATE TABLE IF NOT EXISTS token_validations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_timestamp (ip_address, timestamp)
) ENGINE=InnoDB;

-- Add refresh_token column to users table if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS refresh_token TEXT NULL;
