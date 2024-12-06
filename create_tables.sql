-- Create user_metadata table if it doesn't exist
CREATE TABLE IF NOT EXISTS user_metadata (
    id UUID PRIMARY KEY REFERENCES auth.users(id),
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW()),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW())
);

-- Enable Row Level Security
ALTER TABLE user_metadata ENABLE ROW LEVEL SECURITY;

-- Create policies
CREATE POLICY "Users can view their own metadata"
    ON user_metadata FOR SELECT
    USING (auth.uid() = id);

CREATE POLICY "Only admins can update metadata"
    ON user_metadata FOR UPDATE
    USING (EXISTS (
        SELECT 1 FROM user_metadata WHERE id = auth.uid() AND is_admin = true
    ));

CREATE POLICY "Only admins can insert metadata"
    ON user_metadata FOR INSERT
    WITH CHECK (EXISTS (
        SELECT 1 FROM user_metadata WHERE id = auth.uid() AND is_admin = true
    ));
