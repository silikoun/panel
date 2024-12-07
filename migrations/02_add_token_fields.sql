-- Add token fields to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS api_token VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS api_token_expires TIMESTAMP;
