-- Function to update user's API token in metadata
create or replace function public.update_user_token(user_id uuid, api_token text)
returns void
language plpgsql
security definer
set search_path = public
as $$
begin
  update auth.users
  set raw_user_meta_data = 
    coalesce(raw_user_meta_data, '{}'::jsonb) || 
    jsonb_build_object('api_token', api_token)
  where id = user_id;
end;
$$;
