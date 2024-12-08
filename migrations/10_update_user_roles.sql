-- Drop role column if it exists
ALTER TABLE auth.users 
DROP COLUMN IF EXISTS role;

-- Update existing admin users
UPDATE auth.users 
SET is_admin = true 
WHERE is_admin = false AND id IN (
    SELECT id FROM auth.users WHERE is_admin = true
);

-- Create activity_logs table if it doesn't exist
CREATE TABLE IF NOT EXISTS public.activity_logs (
    id UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    user_email VARCHAR(255) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Enable Row Level Security for activity_logs
ALTER TABLE public.activity_logs ENABLE ROW LEVEL SECURITY;

-- Policies for activity_logs
CREATE POLICY "Users can view their own logs"
    ON public.activity_logs FOR SELECT
    USING (auth.uid() = user_id);

CREATE POLICY "Admins can view all logs"
    ON public.activity_logs FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM auth.users
            WHERE auth.users.id = auth.uid()
            AND is_admin = true
        )
    );

CREATE POLICY "Users can insert their own logs"
    ON public.activity_logs FOR INSERT
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Admins can insert any logs"
    ON public.activity_logs FOR INSERT
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM auth.users
            WHERE auth.users.id = auth.uid()
            AND is_admin = true
        )
    );

-- Create index for activity_logs
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON public.activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON public.activity_logs(created_at);

-- Grant necessary permissions
GRANT ALL ON public.activity_logs TO service_role;
GRANT SELECT, INSERT ON public.activity_logs TO authenticated;

-- Update subscription policies to use is_admin instead of role
DROP POLICY IF EXISTS "Admins can view all subscriptions" ON public.subscriptions;
DROP POLICY IF EXISTS "Admins can insert subscriptions" ON public.subscriptions;
DROP POLICY IF EXISTS "Admins can update subscriptions" ON public.subscriptions;

CREATE POLICY "Admins can view all subscriptions"
    ON public.subscriptions FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM auth.users
            WHERE auth.users.id = auth.uid()
            AND is_admin = true
        )
    );

CREATE POLICY "Admins can insert subscriptions"
    ON public.subscriptions FOR INSERT
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM auth.users
            WHERE auth.users.id = auth.uid()
            AND is_admin = true
        )
    );

CREATE POLICY "Admins can update subscriptions"
    ON public.subscriptions FOR UPDATE
    USING (
        EXISTS (
            SELECT 1 FROM auth.users
            WHERE auth.users.id = auth.uid()
            AND is_admin = true
        )
    );
