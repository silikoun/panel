-- Add is_admin column to profiles table
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS is_admin boolean DEFAULT false;

-- Update policies for admin access
CREATE POLICY "Admins can view all profiles"
    ON profiles FOR SELECT
    USING (auth.uid() IN (
        SELECT id FROM profiles WHERE is_admin = true
    ));

CREATE POLICY "Admins can update all profiles"
    ON profiles FOR UPDATE
    USING (auth.uid() IN (
        SELECT id FROM profiles WHERE is_admin = true
    ));
