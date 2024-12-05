-- Subscriptions table
CREATE TABLE subscriptions (
    id SERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    plan_id INTEGER NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    start_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES auth.users(id),
    FOREIGN KEY (plan_id) REFERENCES plans(id)
);

-- User tokens table
CREATE TABLE user_tokens (
    id SERIAL PRIMARY KEY,
    user_id UUID NOT NULL,
    subscription_id INTEGER NOT NULL,
    access_token VARCHAR(255) UNIQUE NOT NULL,
    refresh_token VARCHAR(255) UNIQUE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES auth.users(id),
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
);

-- Create indexes
CREATE INDEX idx_user_tokens_access ON user_tokens(access_token);
CREATE INDEX idx_subscriptions_user ON subscriptions(user_id);
CREATE INDEX idx_subscriptions_status ON subscriptions(status);
