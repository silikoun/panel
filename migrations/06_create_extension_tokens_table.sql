CREATE TABLE IF NOT EXISTS extension_tokens (
    id SERIAL PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    CONSTRAINT unique_active_token_per_user UNIQUE (user_id, is_active)
);

CREATE INDEX IF NOT EXISTS idx_token_lookup ON extension_tokens (token) WHERE is_active = TRUE;
