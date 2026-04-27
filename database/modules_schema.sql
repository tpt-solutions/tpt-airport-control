-- Module Configuration Schema
-- This schema manages the enablement and configuration of optional modules

-- Module registry table
CREATE TABLE IF NOT EXISTS modules (
    module_id SERIAL PRIMARY KEY,
    module_name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    version VARCHAR(20) DEFAULT '1.0.0',
    category VARCHAR(50) NOT NULL, -- 'operations', 'passenger', 'infrastructure', 'security', 'commercial'
    is_enabled BOOLEAN DEFAULT FALSE,
    is_core BOOLEAN DEFAULT FALSE, -- Core modules cannot be disabled
    dependencies JSONB DEFAULT '[]', -- Array of module names this module depends on
    configuration JSONB DEFAULT '{}', -- Module-specific configuration
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Module permissions table (links modules to user roles)
CREATE TABLE IF NOT EXISTS module_permissions (
    permission_id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(module_id) ON DELETE CASCADE,
    role_name VARCHAR(50) NOT NULL, -- 'super_admin', 'admin', 'operator', 'passenger'
    permission_level VARCHAR(20) DEFAULT 'read', -- 'read', 'write', 'admin'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(module_id, role_name)
);

-- Module audit log
CREATE TABLE IF NOT EXISTS module_audit_log (
    audit_id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(module_id) ON DELETE CASCADE,
    action VARCHAR(50) NOT NULL, -- 'enabled', 'disabled', 'configured', 'updated'
    user_id INTEGER,
    old_value JSONB,
    new_value JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Module health status
CREATE TABLE IF NOT EXISTS module_health (
    health_id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(module_id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'unknown', -- 'healthy', 'degraded', 'unhealthy', 'unknown'
    last_check TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_time INTEGER, -- in milliseconds
    error_message TEXT,
    metrics JSONB DEFAULT '{}', -- Module-specific health metrics
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Feature flags (fine-grained control within modules)
CREATE TABLE IF NOT EXISTS feature_flags (
    flag_id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(module_id) ON DELETE CASCADE,
    flag_name VARCHAR(100) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_enabled BOOLEAN DEFAULT FALSE,
    rollout_percentage INTEGER DEFAULT 100, -- For gradual rollouts (0-100)
    conditions JSONB DEFAULT '{}', -- Conditions for enabling (user roles, airport size, etc.)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(module_id, flag_name)
);

-- Module configuration templates
CREATE TABLE IF NOT EXISTS module_config_templates (
    template_id SERIAL PRIMARY KEY,
    module_name VARCHAR(100) NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    description TEXT,
    config_schema JSONB NOT NULL, -- JSON Schema for validation
    default_config JSONB NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(module_name, template_name)
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_modules_category ON modules(category);
CREATE INDEX IF NOT EXISTS idx_modules_enabled ON modules(is_enabled);
CREATE INDEX IF NOT EXISTS idx_module_permissions_role ON module_permissions(role_name);
CREATE INDEX IF NOT EXISTS idx_module_audit_module ON module_audit_log(module_id);
CREATE INDEX IF NOT EXISTS idx_module_audit_timestamp ON module_audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_module_health_module ON module_health(module_id);
CREATE INDEX IF NOT EXISTS idx_module_health_status ON module_health(status);
CREATE INDEX IF NOT EXISTS idx_feature_flags_module ON feature_flags(module_id);
CREATE INDEX IF NOT EXISTS idx_feature_flags_enabled ON feature_flags(is_enabled);

-- Insert core modules (cannot be disabled)
INSERT INTO modules (module_name, display_name, description, category, is_enabled, is_core) VALUES
('flight_management', 'Flight Management', 'Core flight scheduling and tracking', 'operations', true, true),
('passenger_services', 'Passenger Services', 'Booking and passenger management', 'passenger', true, true),
('user_management', 'User Management', 'Authentication and authorization', 'security', true, true),
('atc_operations', 'ATC Operations', 'Air traffic control operations', 'operations', true, true)
ON CONFLICT (module_name) DO NOTHING;

-- Insert optional modules
INSERT INTO modules (module_name, display_name, description, category, is_enabled, is_core, dependencies) VALUES
('cargo_operations', 'Cargo Operations', 'Freight and cargo terminal management', 'operations', false, false, '["flight_management"]'),
('sustainability', 'Environmental & Sustainability', 'Carbon tracking and environmental monitoring', 'infrastructure', false, false, '[]'),
('commercial', 'Commercial Operations', 'Retail, advertising, and revenue management', 'commercial', false, false, '[]'),
('emergency', 'Emergency Management', 'Crisis response and emergency coordination', 'security', false, false, '["atc_operations"]'),
('special_services', 'Special Services', 'Accessibility and special assistance', 'passenger', false, false, '["passenger_services"]'),
('advanced_analytics', 'Advanced Analytics', 'AI-powered predictions and analytics', 'operations', false, false, '[]'),
('infrastructure', 'Infrastructure Management', 'Building systems and IoT monitoring', 'infrastructure', false, false, '[]'),
('drones', 'Drone/UAV Operations', 'Unmanned aerial vehicle coordination', 'operations', false, false, '["atc_operations"]'),
('customs', 'Customs & Border Protection', 'International passenger processing', 'security', false, false, '["passenger_services"]'),
('advanced_security', 'Advanced Security', 'AI-powered threat detection', 'security', false, false, '["user_management"]'),
('self_checkin', 'Self Check-in System', 'Automated passenger check-in kiosks', 'passenger', false, false, '["passenger_services"]'),
('enhanced_baggage', 'Enhanced Baggage Handling', 'Smart baggage tracking and routing', 'operations', false, false, '["passenger_services"]'),
('passenger_alerts', 'Passenger Alerts System', 'Real-time notifications and alerts', 'passenger', false, false, '["passenger_services"]')
ON CONFLICT (module_name) DO NOTHING;

-- Insert default permissions for core modules
INSERT INTO module_permissions (module_id, role_name, permission_level) VALUES
(1, 'super_admin', 'admin'), -- flight_management
(1, 'admin', 'write'),
(1, 'operator', 'write'),
(1, 'passenger', 'read'),
(2, 'super_admin', 'admin'), -- passenger_services
(2, 'admin', 'write'),
(2, 'operator', 'read'),
(2, 'passenger', 'write'),
(3, 'super_admin', 'admin'), -- user_management
(3, 'admin', 'write'),
(3, 'operator', 'read'),
(3, 'passenger', 'read'),
(4, 'super_admin', 'admin'), -- atc_operations
(4, 'admin', 'write'),
(4, 'operator', 'write'),
(4, 'passenger', 'read')
ON CONFLICT (module_id, role_name) DO NOTHING;

-- Insert configuration templates
INSERT INTO module_config_templates (module_name, template_name, description, config_schema, default_config) VALUES
('sustainability', 'basic', 'Basic environmental monitoring setup',
'{
  "type": "object",
  "properties": {
    "carbon_tracking": {"type": "boolean", "default": true},
    "noise_monitoring": {"type": "boolean", "default": false},
    "green_energy": {"type": "boolean", "default": false},
    "reporting_frequency": {"type": "string", "enum": ["daily", "weekly", "monthly"], "default": "weekly"}
  }
}',
'{
  "carbon_tracking": true,
  "noise_monitoring": false,
  "green_energy": false,
  "reporting_frequency": "weekly"
}')
ON CONFLICT (module_name, template_name) DO NOTHING;

-- Function to check module dependencies
CREATE OR REPLACE FUNCTION check_module_dependencies()
RETURNS TRIGGER AS $$
BEGIN
    -- Check if all dependencies are enabled when enabling a module
    IF NEW.is_enabled = true AND array_length(NEW.dependencies, 1) > 0 THEN
        IF NOT EXISTS (
            SELECT 1 FROM modules
            WHERE module_name = ANY(NEW.dependencies)
            AND is_enabled = true
        ) THEN
            RAISE EXCEPTION 'Cannot enable module %: dependencies not satisfied', NEW.module_name;
        END IF;
    END IF;

    -- Update timestamp
    NEW.updated_at = CURRENT_TIMESTAMP;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger for dependency checking
CREATE TRIGGER check_module_dependencies_trigger
    BEFORE UPDATE ON modules
    FOR EACH ROW
    EXECUTE FUNCTION check_module_dependencies();

-- Function to log module changes
CREATE OR REPLACE FUNCTION log_module_changes()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.is_enabled != NEW.is_enabled OR OLD.configuration != NEW.configuration THEN
        INSERT INTO module_audit_log (module_id, action, old_value, new_value)
        VALUES (
            NEW.module_id,
            CASE
                WHEN OLD.is_enabled != NEW.is_enabled THEN
                    CASE WHEN NEW.is_enabled THEN 'enabled' ELSE 'disabled' END
                ELSE 'configured'
            END,
            jsonb_build_object('enabled', OLD.is_enabled, 'config', OLD.configuration),
            jsonb_build_object('enabled', NEW.is_enabled, 'config', NEW.configuration)
        );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger for audit logging
CREATE TRIGGER log_module_changes_trigger
    AFTER UPDATE ON modules
    FOR EACH ROW
    EXECUTE FUNCTION log_module_changes();
