-- Function to update user's API token
create or replace function public.update_user_token(user_id uuid, api_token text)
returns void
language plpgsql
security definer
as $$
begin
  update auth.users
  set raw_user_meta_data = 
    case 
      when raw_user_meta_data is null then 
        jsonb_build_object('api_token', api_token)
      else
        raw_user_meta_data || jsonb_build_object('api_token', api_token)
    end
  where id = user_id;
end;
$$;
