-- Create extensions if not exists
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Enable Row Level Security
ALTER TABLE auth.users ENABLE ROW LEVEL SECURITY;

-- Create tokens table
CREATE TABLE IF NOT EXISTS public.tokens (
    id UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    token TEXT NOT NULL UNIQUE,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'revoked')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    last_used TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    metadata JSONB DEFAULT '{}'::jsonb
);

-- Enable RLS on tokens table
ALTER TABLE public.tokens ENABLE ROW LEVEL SECURITY;

-- Drop existing policies if they exist
DROP POLICY IF EXISTS "Users can view their own tokens" ON public.tokens;
DROP POLICY IF EXISTS "Users can insert their own tokens" ON public.tokens;
DROP POLICY IF EXISTS "Users can update their own tokens" ON public.tokens;
DROP POLICY IF EXISTS "Admins have full access to tokens" ON public.tokens;

-- Create RLS policies for tokens
CREATE POLICY "Users can view their own tokens"
    ON public.tokens
    FOR SELECT
    USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own tokens"
    ON public.tokens
    FOR INSERT
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update their own tokens"
    ON public.tokens
    FOR UPDATE
    USING (auth.uid() = user_id);

CREATE POLICY "Admins have full access to tokens"
    ON public.tokens
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM auth.users
            WHERE auth.users.id = auth.uid()
            AND auth.users.role = 'service_role'
        )
    );

-- Create usage_logs table
CREATE TABLE IF NOT EXISTS public.usage_logs (
    id UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    token_id UUID REFERENCES public.tokens(id) ON DELETE CASCADE,
    action_type TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    details JSONB DEFAULT '{}'::jsonb
);

-- Enable RLS on usage_logs table
ALTER TABLE public.usage_logs ENABLE ROW LEVEL SECURITY;

-- Drop existing policies if they exist
DROP POLICY IF EXISTS "Users can view their own logs" ON public.usage_logs;
DROP POLICY IF EXISTS "Users can insert their own logs" ON public.usage_logs;
DROP POLICY IF EXISTS "Admins have full access to logs" ON public.usage_logs;

-- Create RLS policies for usage_logs
CREATE POLICY "Users can view their own logs"
    ON public.usage_logs
    FOR SELECT
    USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own logs"
    ON public.usage_logs
    FOR INSERT
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Admins have full access to logs"
    ON public.usage_logs
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM auth.users
            WHERE auth.users.id = auth.uid()
            AND auth.users.role = 'service_role'
        )
    );
