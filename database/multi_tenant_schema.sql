-- Multi-tenant Architecture Schema
-- Implements row-level security for customer data separation

-- Tenants table - customer organizations
CREATE TABLE IF NOT EXISTS tenants (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    subdomain VARCHAR(100) UNIQUE,
    plan_id VARCHAR(50) REFERENCES subscription_plans(id),
    status VARCHAR(20) DEFAULT 'active', -- active, suspended, canceled
    suspension_reason TEXT,
    max_users INTEGER DEFAULT 10,
    storage_limit_mb INTEGER DEFAULT 1000,
    api_calls_limit INTEGER DEFAULT 10000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP
);

-- Tenant settings table
CREATE TABLE IF NOT EXISTS tenant_settings (
    id SERIAL PRIMARY KEY,
    tenant_id VARCHAR(50) REFERENCES tenants(id) ON DELETE CASCADE,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, setting_key)
);

-- Add tenant_id column to all existing tables
DO $$
DECLARE
    table_names TEXT[] := ARRAY[
        'users', 'flights', 'bookings', 'passengers', 'scenarios',
        'subscriptions', 'subscription_usage', 'payment_history',
        'user_onboarding_progress', 'user_achievements', 'user_statistics',
        'airlines', 'aircraft', 'gates', 'runways', 'terminals'
    ];
    table_name TEXT;
BEGIN
    FOREACH table_name IN ARRAY table_names
    LOOP
        EXECUTE format('
            ALTER TABLE IF NOT EXISTS %I 
            ADD COLUMN IF NOT EXISTS tenant_id VARCHAR(50) REFERENCES tenants(id)
        ', table_name);
        
        -- Create index for tenant column
        EXECUTE format('
            CREATE INDEX IF NOT EXISTS idx_%I_tenant_id ON %I (tenant_id)
        ', table_name, table_name);
    END LOOP;
END $$;

-- Row Level Security (RLS) policies
ALTER TABLE tenants ENABLE ROW LEVEL SECURITY;
ALTER TABLE tenant_settings ENABLE ROW LEVEL SECURITY;

-- Policy for tenants - users can only see their own tenant
CREATE POLICY tenant_isolation ON tenants
    FOR ALL
    USING (id = current_setting('app.current_tenant_id', true)::varchar);

-- Policy for tenant settings
CREATE POLICY tenant_settings_isolation ON tenant_settings
    FOR ALL
    USING (tenant_id = current_setting('app.current_tenant_id', true)::varchar);

-- Function to set tenant context
CREATE OR REPLACE FUNCTION set_tenant_context(tenant_id_param VARCHAR)
RETURNS VOID AS $$
BEGIN
    PERFORM set_config('app.current_tenant_id', tenant_id_param, false);
END;
$$ LANGUAGE plpgsql;

-- Function to get current tenant context
CREATE OR REPLACE FUNCTION get_current_tenant()
RETURNS VARCHAR AS $$
BEGIN
    RETURN current_setting('app.current_tenant_id', true);
END;
$$ LANGUAGE plpgsql;

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_tenants_subdomain ON tenants(subdomain);
CREATE INDEX IF NOT EXISTS idx_tenants_status ON tenants(status);
CREATE INDEX IF NOT EXISTS idx_tenant_settings_key ON tenant_settings(setting_key);