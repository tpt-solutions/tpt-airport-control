-- Flight Control Database Schema - Flight Management Module
-- Core flight operations, airlines, aircraft, passengers, and bookings

-- Users and Authentication
CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
);

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INTEGER REFERENCES roles(id),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Modules Configuration
CREATE TABLE modules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_enabled BOOLEAN DEFAULT FALSE
);

CREATE TABLE role_permissions (
    id SERIAL PRIMARY KEY,
    role_id INTEGER REFERENCES roles(id),
    module_id INTEGER REFERENCES modules(id),
    permission VARCHAR(50) NOT NULL
);

CREATE TABLE user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    module_id INTEGER REFERENCES modules(id),
    permission VARCHAR(50) NOT NULL -- e.g., 'read', 'write', 'admin'
);

-- Flights
CREATE TABLE airlines (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL,
    country VARCHAR(100)
);

CREATE TABLE aircraft (
    id SERIAL PRIMARY KEY,
    model VARCHAR(100) NOT NULL,
    registration VARCHAR(20) UNIQUE NOT NULL,
    capacity INTEGER NOT NULL
);

CREATE TABLE flights (
    id SERIAL PRIMARY KEY,
    flight_number VARCHAR(20) UNIQUE NOT NULL,
    airline_id INTEGER REFERENCES airlines(id),
    aircraft_id INTEGER REFERENCES aircraft(id),
    origin VARCHAR(10) NOT NULL,
    destination VARCHAR(10) NOT NULL,
    scheduled_departure TIMESTAMP NOT NULL,
    scheduled_arrival TIMESTAMP NOT NULL,
    actual_departure TIMESTAMP,
    actual_arrival TIMESTAMP,
    status VARCHAR(50) DEFAULT 'scheduled', -- scheduled, boarding, departed, arrived, delayed, cancelled
    gate VARCHAR(10),
    terminal VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Passengers and Bookings
CREATE TABLE passengers (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    passport_number VARCHAR(20),
    nationality VARCHAR(100),
    date_of_birth DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bookings (
    id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(id),
    flight_id INTEGER REFERENCES flights(id),
    seat_number VARCHAR(10),
    booking_reference VARCHAR(20) UNIQUE NOT NULL,
    status VARCHAR(50) DEFAULT 'confirmed', -- confirmed, cancelled, checked-in
    total_amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    payment_status VARCHAR(50) DEFAULT 'pending', -- pending, paid, refunded
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Baggage
CREATE TABLE baggage (
    id SERIAL PRIMARY KEY,
    booking_id INTEGER REFERENCES bookings(id),
    tag_number VARCHAR(20) UNIQUE NOT NULL,
    weight DECIMAL(5,2),
    status VARCHAR(50) DEFAULT 'checked', -- checked, loaded, unloaded, claimed, lost
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for flight management
CREATE INDEX idx_flights_status ON flights(status);
CREATE INDEX idx_flights_scheduled_departure ON flights(scheduled_departure);
CREATE INDEX idx_bookings_flight_id ON bookings(flight_id);
CREATE INDEX idx_baggage_booking_id ON baggage(booking_id);
