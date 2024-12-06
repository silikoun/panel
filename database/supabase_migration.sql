-- Create user roles table
create table if not exists user_roles (
  id uuid default uuid_generate_v4() primary key,
  user_id uuid references auth.users(id) on delete cascade,
  role text not null default 'user' check (role in ('user', 'admin')),
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

-- Create RLS policies
alter table user_roles enable row level security;

create policy "Users can view their own role"
  on user_roles for select
  using ( auth.uid() = user_id );

create policy "Only admins can update roles"
  on user_roles for update
  using ( auth.uid() in (
    select user_id from user_roles where role = 'admin'
  ));

-- Create function to automatically create user role on signup
create or replace function public.handle_new_user() 
returns trigger as $$
begin
  insert into public.user_roles (user_id, role)
  values (new.id, 'user');
  return new;
end;
$$ language plpgsql security definer;

-- Create trigger for new user signup
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute procedure public.handle_new_user();
