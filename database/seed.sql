-- Seed data for Flight Control Software

-- Insert default roles
INSERT INTO roles (name, description) VALUES
('passenger', 'Regular passenger with booking access'),
('operator', 'Airport operations staff'),
('admin', 'Airport administrator'),
('super_admin', 'System super administrator'),
-- Specialized roles for Airport Operations Simulator
('controller', 'Air Traffic Controller - manages airspace and flight clearances'),
('dispatcher', 'Flight Dispatcher - coordinates flight operations and crew'),
('cargo_manager', 'Cargo Operations Manager - oversees freight and cargo terminal'),
('security_officer', 'Security Officer - manages security and surveillance'),
('customs_officer', 'Customs & Border Protection Officer - handles international passenger processing'),
('emergency_coordinator', 'Emergency Response Coordinator - manages crisis situations'),
('infrastructure_manager', 'Infrastructure Systems Manager - monitors building systems and IoT'),
('drone_operator', 'UAV Operations Specialist - coordinates unmanned aerial vehicles'),
('commercial_manager', 'Commercial Operations Manager - oversees retail and revenue'),
('sustainability_officer', 'Environmental Officer - tracks carbon emissions and green initiatives'),
('ai_analyst', 'AI & Analytics Specialist - manages predictive models and data analysis'),
('virtual_assistant_admin', 'Virtual Assistant Administrator - configures AI assistants'),
('kiosk_operator', 'Self-Checkin Kiosk Operator - manages automated passenger services'),
('baggage_handler', 'Baggage Operations Specialist - oversees baggage routing and tracking'),
('passenger_services_rep', 'Passenger Services Representative - handles special assistance and alerts');

-- Insert modules
INSERT INTO modules (name, description, is_enabled) VALUES
('flights', 'Flight management and scheduling', true),
('passengers', 'Passenger and booking management', true),
('baggage', 'Baggage tracking system', true),
('security', 'Security and check-in operations', true),
('ground_ops', 'Ground operations and maintenance', true),
('atc', 'Air traffic control tower operations', false),
('analytics', 'Reporting and analytics', true),
('admin', 'System administration', true);

-- Insert role permissions (default permissions for each role)
-- Passenger role
INSERT INTO role_permissions (role_id, module_id, permission) VALUES
(1, 2, 'read_own'),  -- passengers module, read own data
(1, 3, 'read_own'),  -- baggage module, read own data
(1, 7, 'read');      -- analytics module, read reports

-- Operator role
INSERT INTO role_permissions (role_id, module_id, permission) VALUES
(2, 1, 'read'),      -- flights module, read
(2, 1, 'write'),     -- flights module, write
(2, 2, 'read'),      -- passengers module, read
(2, 2, 'write'),     -- passengers module, write
(2, 3, 'read'),      -- baggage module, read
(2, 3, 'write'),     -- baggage module, write
(2, 4, 'read'),      -- security module, read
(2, 4, 'write'),     -- security module, write
(2, 5, 'read'),      -- ground_ops module, read
(2, 5, 'write'),     -- ground_ops module, write
(2, 7, 'read');      -- analytics module, read

-- Admin role
INSERT INTO role_permissions (role_id, module_id, permission) VALUES
(3, 1, 'read'),      -- flights module, read
(3, 1, 'write'),     -- flights module, write
(3, 1, 'admin'),     -- flights module, admin
(3, 2, 'read'),      -- passengers module, read
(3, 2, 'write'),     -- passengers module, write
(3, 2, 'admin'),     -- passengers module, admin
(3, 3, 'read'),      -- baggage module, read
(3, 3, 'write'),     -- baggage module, write
(3, 3, 'admin'),     -- baggage module, admin
(3, 4, 'read'),      -- security module, read
(3, 4, 'write'),     -- security module, write
(3, 4, 'admin'),     -- security module, admin
(3, 5, 'read'),      -- ground_ops module, read
(3, 5, 'write'),     -- ground_ops module, write
(3, 5, 'admin'),     -- ground_ops module, admin
(3, 6, 'read'),      -- atc module, read
(3, 6, 'write'),     -- atc module, write
(3, 7, 'read'),      -- analytics module, read
(3, 7, 'write'),     -- analytics module, write
(3, 8, 'read'),      -- admin module, read
(3, 8, 'write');     -- admin module, write

-- Super Admin role (all permissions)
INSERT INTO role_permissions (role_id, module_id, permission) VALUES
(4, 1, 'read'), (4, 1, 'write'), (4, 1, 'admin'),
(4, 2, 'read'), (4, 2, 'write'), (4, 2, 'admin'),
(4, 3, 'read'), (4, 3, 'write'), (4, 3, 'admin'),
(4, 4, 'read'), (4, 4, 'write'), (4, 4, 'admin'),
(4, 5, 'read'), (4, 5, 'write'), (4, 5, 'admin'),
(4, 6, 'read'), (4, 6, 'write'), (4, 6, 'admin'),
(4, 7, 'read'), (4, 7, 'write'), (4, 7, 'admin'),
(4, 8, 'read'), (4, 8, 'write'), (4, 8, 'admin');

-- Create a default super admin user
-- Password: admin123 (hashed)
INSERT INTO users (username, email, password_hash, role_id, first_name, last_name, is_active) VALUES
('admin', 'admin@airport.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'System', 'Administrator', true);

-- Sample airlines
INSERT INTO airlines (name, code, country) VALUES
('Sample Airlines', 'SA', 'US'),
('Test Airways', 'TA', 'UK');

-- Sample aircraft
INSERT INTO aircraft (model, registration, capacity) VALUES
('Boeing 737-800', 'N123SA', 180),
('Airbus A320', 'G456TA', 150);

-- Sample flight
INSERT INTO flights (flight_number, airline_id, aircraft_id, origin, destination, scheduled_departure, scheduled_arrival, status) VALUES
('SA101', 1, 1, 'JFK', 'LAX', '2025-09-13 08:00:00', '2025-09-13 11:30:00', 'scheduled');
