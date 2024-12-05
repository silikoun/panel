-- Plans table
CREATE TABLE plans (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration_days INTEGER NOT NULL,
    features JSONB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default plans
INSERT INTO plans (name, price, duration_days, features) VALUES
('Free', 0.00, 30, '{"max_products": 100, "export_formats": ["csv"], "api_access": false}'),
('Premium', 29.99, 30, '{"max_products": -1, "export_formats": ["csv", "json", "xml"], "api_access": true}');
