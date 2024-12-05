-- Add token-related columns to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS refresh_token VARCHAR(512) NULL,
ADD COLUMN IF NOT EXISTS token_expires TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;

-- Create table for tracking token validation attempts (rate limiting)
CREATE TABLE IF NOT EXISTS token_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    token_type VARCHAR(20) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB;

-- Create table for revoked tokens
CREATE TABLE IF NOT EXISTS revoked_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash VARCHAR(256) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    revocation_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(100) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    UNIQUE INDEX idx_token_hash (token_hash),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create table for token refresh history
CREATE TABLE IF NOT EXISTS token_refresh_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    refresh_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(512),
    INDEX idx_user_refresh (user_id, refresh_time),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add cleanup procedure for expired tokens
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_expired_tokens()
BEGIN
    -- Remove expired revoked tokens
    DELETE FROM revoked_tokens WHERE expires_at < NOW();
    
    -- Remove old token attempts (older than 1 hour)
    DELETE FROM token_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR);
    
    -- Remove old refresh history (older than 30 days)
    DELETE FROM token_refresh_history WHERE refresh_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //
DELIMITER ;

-- Create event to run cleanup procedure daily
CREATE EVENT IF NOT EXISTS token_cleanup_event
ON SCHEDULE EVERY 1 DAY
DO CALL cleanup_expired_tokens();
