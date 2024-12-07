-- Add API key fields to auth.users table
ALTER TABLE auth.users 
ADD COLUMN IF NOT EXISTS api_token VARCHAR(255),
ADD COLUMN IF NOT EXISTS api_token_expires TIMESTAMP WITH TIME ZONE;

-- Create an index for faster API token lookups
CREATE INDEX IF NOT EXISTS idx_users_api_token ON auth.users(api_token);

-- Add a function to automatically update api_token_expires
CREATE OR REPLACE FUNCTION public.handle_api_token_update()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.api_token IS NOT NULL AND NEW.api_token <> OLD.api_token THEN
        NEW.api_token_expires := NOW() + INTERVAL '30 days';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Create a trigger to automatically update api_token_expires
DROP TRIGGER IF EXISTS on_api_token_update ON auth.users;
CREATE TRIGGER on_api_token_update
    BEFORE UPDATE ON auth.users
    FOR EACH ROW
    WHEN (NEW.api_token IS DISTINCT FROM OLD.api_token)
    EXECUTE FUNCTION public.handle_api_token_update();
