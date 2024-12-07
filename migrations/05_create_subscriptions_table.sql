-- Create subscriptions table
CREATE TABLE IF NOT EXISTS public.subscriptions (
    id UUID DEFAULT uuid_generate_v4() PRIMARY KEY,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    status VARCHAR(50) NOT NULL,
    plan VARCHAR(50) NOT NULL,
    current_period_start TIMESTAMP WITH TIME ZONE,
    current_period_end TIMESTAMP WITH TIME ZONE,
    cancel_at TIMESTAMP WITH TIME ZONE,
    canceled_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Add indexes
CREATE INDEX IF NOT EXISTS idx_subscriptions_user_id ON public.subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_status ON public.subscriptions(status);

-- Add RLS policies
ALTER TABLE public.subscriptions ENABLE ROW LEVEL SECURITY;

-- Allow users to read their own subscriptions
CREATE POLICY "Users can view own subscriptions"
    ON public.subscriptions
    FOR SELECT
    USING (auth.uid() = user_id);

-- Allow service role to manage all subscriptions
CREATE POLICY "Service role can manage all subscriptions"
    ON public.subscriptions
    USING (auth.jwt()->role = 'service_role');

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION public.handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Trigger for updated_at
CREATE TRIGGER set_updated_at
    BEFORE UPDATE ON public.subscriptions
    FOR EACH ROW
    EXECUTE FUNCTION public.handle_updated_at();
