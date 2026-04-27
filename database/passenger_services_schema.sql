-- Flight Control Database Schema - Passenger Services Module
-- Check-ins, boarding passes, security, and ground operations

-- Security and Check-in
CREATE TABLE check_ins (
    id SERIAL PRIMARY KEY,
    booking_id INTEGER REFERENCES bookings(id),
    check_in_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    boarding_pass_issued BOOLEAN DEFAULT FALSE,
    security_cleared BOOLEAN DEFAULT FALSE
);

-- Ground Operations
CREATE TABLE maintenance_schedules (
    id SERIAL PRIMARY KEY,
    aircraft_id INTEGER REFERENCES aircraft(id),
    maintenance_type VARCHAR(100),
    scheduled_date DATE,
    completed BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE crew_assignments (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    user_id INTEGER REFERENCES users(id),
    role VARCHAR(50), -- pilot, co-pilot, flight attendant
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for passenger services
CREATE INDEX idx_check_ins_booking_id ON check_ins(booking_id);
CREATE INDEX idx_maintenance_schedules_aircraft_id ON maintenance_schedules(aircraft_id);
CREATE INDEX idx_maintenance_schedules_date ON maintenance_schedules(scheduled_date);
CREATE INDEX idx_crew_assignments_flight_id ON crew_assignments(flight_id);
CREATE INDEX idx_crew_assignments_user_id ON crew_assignments(user_id);
