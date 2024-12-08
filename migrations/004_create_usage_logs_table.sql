-- Create usage_logs table
CREATE TABLE IF NOT EXISTS usage_logs (
    id UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
    token_id UUID REFERENCES tokens(id),
    endpoint TEXT NOT NULL,
    method TEXT NOT NULL,
    status_code INTEGER,
    response_time INTEGER,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Add RLS policies
ALTER TABLE usage_logs ENABLE ROW LEVEL SECURITY;

-- Policy to allow users to view their own usage logs
CREATE POLICY usage_logs_select_policy ON usage_logs
    FOR SELECT
    USING (
        token_id IN (
            SELECT id FROM tokens WHERE user_id = auth.uid()
        )
    );

-- Policy to allow admins to view all usage logs
CREATE POLICY usage_logs_admin_policy ON usage_logs
    FOR ALL
    USING (
        EXISTS (
            SELECT 1 FROM users
            WHERE users.id = auth.uid()
            AND users.is_admin = true
        )
    );

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS usage_logs_token_id_idx ON usage_logs(token_id);
CREATE INDEX IF NOT EXISTS usage_logs_created_at_idx ON usage_logs(created_at DESC);

-- Add trigger to update updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_usage_logs_updated_at
    BEFORE UPDATE ON usage_logs
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
