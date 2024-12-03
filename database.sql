-- Create API keys table
create table api_keys (
    id uuid default uuid_generate_v4() primary key,
    user_id uuid references auth.users(id),
    api_key text not null,
    created_at timestamp with time zone default timezone('utc'::text, now()) not null,
    updated_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Create user profiles table
create table profiles (
    id uuid references auth.users(id) primary key,
    email text,
    is_premium boolean default false,
    premium_until timestamp with time zone,
    created_at timestamp with time zone default timezone('utc'::text, now()) not null,
    updated_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Enable Row Level Security
alter table api_keys enable row level security;
alter table profiles enable row level security;

-- Create policies
create policy "Users can view their own api keys"
    on api_keys for select
    using (auth.uid() = user_id);

create policy "Users can insert their own api keys"
    on api_keys for insert
    with check (auth.uid() = user_id);

create policy "Users can update their own api keys"
    on api_keys for update
    using (auth.uid() = user_id);

create policy "Users can view their own profile"
    on profiles for select
    using (auth.uid() = id);

create policy "Users can update their own profile"
    on profiles for update
    using (auth.uid() = id);
