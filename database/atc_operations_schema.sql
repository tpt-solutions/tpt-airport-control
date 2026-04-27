-- Flight Control Database Schema - ATC Operations Module
-- Air traffic control operations, runways, clearances, and communications

-- ATC Tower
CREATE TABLE runways (
    id SERIAL PRIMARY KEY,
    runway_number VARCHAR(10) UNIQUE NOT NULL,
    length INTEGER,
    status VARCHAR(50) DEFAULT 'available' -- available, occupied, closed
);

CREATE TABLE clearances (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    clearance_type VARCHAR(50), -- takeoff, landing
    issued_by INTEGER REFERENCES users(id),
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT
);

CREATE TABLE communications (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    user_id INTEGER REFERENCES users(id),
    message TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE weather_data (
    id SERIAL PRIMARY KEY,
    location VARCHAR(100),
    temperature DECIMAL(5,2),
    wind_speed DECIMAL(5,2),
    visibility DECIMAL(5,2),
    conditions TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE emergencies (
    id SERIAL PRIMARY KEY,
    flight_id INTEGER REFERENCES flights(id),
    type VARCHAR(100),
    description TEXT,
    reported_by INTEGER REFERENCES users(id),
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved BOOLEAN DEFAULT FALSE
);

-- Indexes for ATC operations
CREATE INDEX idx_clearances_flight_id ON clearances(flight_id);
CREATE INDEX idx_clearances_issued_at ON clearances(issued_at);
CREATE INDEX idx_communications_flight_id ON communications(flight_id);
CREATE INDEX idx_communications_timestamp ON communications(timestamp);
CREATE INDEX idx_weather_data_location ON weather_data(location);
CREATE INDEX idx_weather_data_recorded_at ON weather_data(recorded_at);
CREATE INDEX idx_emergencies_flight_id ON emergencies(flight_id);
CREATE INDEX idx_emergencies_reported_at ON emergencies(reported_at);
