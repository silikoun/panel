-- Drop the old columns if they exist
ALTER TABLE users DROP COLUMN IF EXISTS api_token;
ALTER TABLE users DROP COLUMN IF EXISTS api_token_expires;
ALTER TABLE users DROP COLUMN IF EXISTS token_expires;

-- Add the new columns
ALTER TABLE users ADD COLUMN api_token VARCHAR(255);
ALTER TABLE users ADD COLUMN api_token_expires TIMESTAMP;
ALTER TABLE users ADD COLUMN token_expires TIMESTAMP;

-- Create an index for faster token lookups
CREATE INDEX IF NOT EXISTS idx_users_api_token ON users(api_token);
CREATE INDEX IF NOT EXISTS idx_users_refresh_token ON users(refresh_token);
