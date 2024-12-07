-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    refresh_token VARCHAR(255),
    token_expires TIMESTAMP
);

-- Create revoked_tokens table
CREATE TABLE IF NOT EXISTS revoked_tokens (
    id SERIAL PRIMARY KEY,
    token_hash VARCHAR(255) UNIQUE NOT NULL,
    revocation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255)
);

-- Create rate_limit table
CREATE TABLE IF NOT EXISTS rate_limit (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index for rate limiting
CREATE INDEX IF NOT EXISTS idx_rate_limit_ip_time ON rate_limit(ip_address, attempt_time);

-- Create index for token validation
CREATE INDEX IF NOT EXISTS idx_revoked_tokens_hash ON revoked_tokens(token_hash);
