-- Add columns for token management
ALTER TABLE users
ADD COLUMN IF NOT EXISTS refresh_token TEXT NULL,
ADD COLUMN IF NOT EXISTS token_expires TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;

-- Create table for rate limiting and token validation
CREATE TABLE IF NOT EXISTS token_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB;

-- Create table for revoked tokens
CREATE TABLE IF NOT EXISTS revoked_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash VARCHAR(64) NOT NULL,
    revocation_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255),
    INDEX idx_token (token_hash)
) ENGINE=InnoDB;
