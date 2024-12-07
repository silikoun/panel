-- First, make sure the is_admin column exists
ALTER TABLE auth.users 
ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT false;

-- Set admin user in Supabase
update auth.users
set raw_user_meta_data = raw_user_meta_data || 
  case 
    when email = 'djadiid@gmail.com' then '{"is_admin": true}'::jsonb
    else '{"is_admin": false}'::jsonb
  end;

-- Also update the is_admin column for compatibility
UPDATE auth.users 
SET is_admin = CASE 
    WHEN email = 'djadiid@gmail.com' THEN true 
    ELSE false 
END
WHERE email IN ('djadiid@gmail.com') 
   OR is_admin = true;
