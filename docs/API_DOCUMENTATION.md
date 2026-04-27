# Flight Control Software API Documentation

## Overview

The Flight Control Software provides a comprehensive REST API for managing airport operations, flight control, passenger services, and real-time data processing. The API is built with PHP and follows RESTful principles with JSON responses.

## Base URL
```
http://localhost:8000/api/
```

## Authentication

All API endpoints require authentication except for login and registration. Use JWT tokens in the Authorization header:

```
Authorization: Bearer <jwt_token>
```

### Login
```http
POST /api/auth.php?action=login
Content-Type: application/json

{
  "username": "operator",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "username": "operator",
    "first_name": "John",
    "last_name": "Doe",
    "role_name": "operator"
  }
}
```

### Register
```http
POST /api/auth.php?action=register
Content-Type: application/json

{
  "username": "newuser",
  "email": "newuser@example.com",
  "password": "securepassword123",
  "first_name": "New",
  "last_name": "User"
}
```

## Core API Endpoints

### Flights Management

#### Get All Flights
```http
GET /api/flights.php?page=1&limit=20&status=scheduled&origin=JFK
```

**Query Parameters:**
- `page` (int): Page number for pagination
- `limit` (int): Number of results per page
- `status` (string): Filter by flight status
- `origin` (string): Filter by origin airport
- `destination` (string): Filter by destination airport
- `airline_id` (int): Filter by airline

**Response:**
```json
{
  "success": true,
  "flights": [
    {
      "id": 1,
      "flight_number": "AA001",
      "origin": "JFK",
      "destination": "LAX",
      "scheduled_departure": "2025-01-15T08:00:00Z",
      "scheduled_arrival": "2025-01-15T11:00:00Z",
      "status": "scheduled",
      "gate": "A12",
      "terminal": "1",
      "aircraft_id": 1,
      "airline_name": "American Airlines"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "pages": 8
  }
}
```

#### Get Flight by ID
```http
GET /api/flights.php/123
```

#### Create Flight
```http
POST /api/flights.php
Content-Type: application/json
Authorization: Bearer <token>

{
  "flight_number": "AA002",
  "origin": "JFK",
  "destination": "LAX",
  "scheduled_departure": "2025-01-15T08:00:00Z",
  "scheduled_arrival": "2025-01-15T11:00:00Z",
  "aircraft_id": 1,
  "airline_id": 1
}
```

#### Update Flight
```http
PUT /api/flights.php/123
Content-Type: application/json
Authorization: Bearer <token>

{
  "status": "boarding",
  "gate": "B15",
  "delay_minutes": 15
}
```

#### Delete Flight
```http
DELETE /api/flights.php/123
Authorization: Bearer <token>
```

### Passenger Management

#### Get All Passengers
```http
GET /api/passengers.php?page=1&limit=50&search=john
```

#### Get Passenger by ID
```http
GET /api/passengers.php/456
```

#### Create Passenger
```http
POST /api/passengers.php
Content-Type: application/json
Authorization: Bearer <token>

{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@example.com",
  "phone": "+1234567890",
  "passport_number": "P123456789",
  "nationality": "USA"
}
```

#### Update Passenger
```http
PUT /api/passengers.php/456
Content-Type: application/json
Authorization: Bearer <token>

{
  "phone": "+1987654321",
  "email": "john.doe.updated@example.com"
}
```

### Booking Management

#### Get All Bookings
```http
GET /api/bookings.php?page=1&limit=20&passenger_id=456&flight_id=123
```

#### Create Booking
```http
POST /api/bookings.php
Content-Type: application/json
Authorization: Bearer <token>

{
  "passenger_id": 456,
  "flight_id": 123,
  "seat_number": "12A",
  "total_amount": 299.99,
  "currency": "USD"
}
```

#### Update Booking
```http
PUT /api/bookings.php/789
Content-Type: application/json
Authorization: Bearer <token>

{
  "seat_number": "14B",
  "status": "confirmed"
}
```

### Baggage Tracking

#### Get Baggage by Booking
```http
GET /api/baggage.php?booking_id=789
```

#### Create Baggage Record
```http
POST /api/baggage.php
Content-Type: application/json
Authorization: Bearer <token>

{
  "booking_id": 789,
  "weight_kg": 23.5,
  "dimensions": "55x40x20",
  "tag_number": "ABC123"
}
```

#### Update Baggage Status
```http
PUT /api/baggage.php/101
Content-Type: application/json
Authorization: Bearer <token>

{
  "status": "loaded",
  "location": "Cargo Hold A"
}
```

### Check-in & Boarding

#### Process Check-in
```http
POST /api/checkin.php
Content-Type: application/json
Authorization: Bearer <token>

{
  "booking_id": 789,
  "security_cleared": true,
  "boarding_pass_issued": true
}
```

#### Get Boarding Pass
```http
GET /api/boarding-pass.php/789
Authorization: Bearer <token>
```

**Response:**
```json
{
  "success": true,
  "boarding_pass": {
    "booking_reference": "ABC123",
    "passenger_name": "John Doe",
    "flight_number": "AA001",
    "origin": "JFK",
    "destination": "LAX",
    "scheduled_departure": "2025-01-15T08:00:00Z",
    "seat_number": "12A",
    "gate": "A12",
    "terminal": "1",
    "qr_code_data": "eyJib29raW5nX3JlZiI6IkFCQzEyMyJ9",
    "issued_at": "2025-01-15T06:30:00Z",
    "valid_until": "2025-01-15T09:00:00Z"
  },
  "security_cleared": true
}
```

### Security Management

#### Get Security Screenings
```http
GET /api/security.php?page=1&limit=50&status=pending
```

#### Process Security Screening
```http
POST /api/security.php?action=screen&booking_id=789
Content-Type: application/json
Authorization: Bearer <token>

{
  "result": "pass",
  "notes": "All clear"
}
```

#### Validate QR Code
```http
POST /api/security.php?action=validate_qr
Content-Type: application/json
Authorization: Bearer <token>

{
  "qr_data": "eyJib29raW5nX3JlZiI6IkFCQzEyMyJ9"
}
```

### Maintenance Management

#### Get Maintenance Schedules
```http
GET /api/maintenance.php?page=1&limit=20&aircraft_id=1&status=scheduled
```

#### Create Maintenance Schedule
```http
POST /api/maintenance.php
Content-Type: application/json
Authorization: Bearer <token>

{
  "aircraft_id": 1,
  "maintenance_type": "routine_check",
  "scheduled_date": "2025-02-01",
  "notes": "Monthly inspection"
}
```

#### Update Maintenance Status
```http
PUT /api/maintenance.php/202
Content-Type: application/json
Authorization: Bearer <token>

{
  "completed": true,
  "notes": "Completed successfully"
}
```

### Crew Management

#### Get Crew Assignments
```http
GET /api/crew.php?page=1&limit=20&flight_id=123&role=pilot
```

#### Assign Crew Member
```http
POST /api/crew.php
Content-Type: application/json
Authorization: Bearer <token>

{
  "flight_id": 123,
  "user_id": 5,
  "role": "captain"
}
```

### Analytics & Reporting

#### Get Dashboard Statistics
```http
GET /api/analytics.php?action=stats
Authorization: Bearer <token>
```

**Response:**
```json
{
  "success": true,
  "stats": {
    "total_flights": 150,
    "active_flights": 45,
    "total_passengers": 12500,
    "checked_in_passengers": 8900,
    "total_bookings": 12800,
    "pending_maintenance": 12,
    "security_alerts": 3,
    "system_health": "healthy"
  }
}
```

#### Get Flight Performance Report
```http
GET /api/analytics.php?action=flight_performance&start_date=2025-01-01&end_date=2025-01-31
```

### Operational Logs

#### Get System Logs
```http
GET /api/logs.php?page=1&limit=50&level=WARNING&category=security
```

#### Create Log Entry
```http
POST /api/logs.php
Content-Type: application/json
Authorization: Bearer <token>

{
  "level": "INFO",
  "category": "flight",
  "message": "Flight AA001 departed on time",
  "details": "Gate A12, 150 passengers"
}
```

## WebSocket Real-time Updates

The API also supports real-time updates via WebSocket for live data synchronization.

### WebSocket Connection
```javascript
const ws = new WebSocket('ws://localhost:8080');

// Subscribe to flight updates
ws.send(JSON.stringify({
  type: 'subscribe',
  data: {
    channel: 'flights',
    filters: { status: 'active' }
  }
}));

// Subscribe to passenger updates
ws.send(JSON.stringify({
  type: 'subscribe',
  data: {
    channel: 'passengers',
    filters: { flight_id: 123 }
  }
}));

// Handle incoming messages
ws.onmessage = (event) => {
  const data = JSON.parse(event.data);
  console.log('Real-time update:', data);
};
```

### WebSocket Message Types

#### Flight Updates
```json
{
  "type": "flight_update",
  "data": {
    "flight_id": 123,
    "flight_number": "AA001",
    "status": "boarding",
    "gate": "B15",
    "delay_minutes": 10
  }
}
```

#### Passenger Updates
```json
{
  "type": "passenger_update",
  "data": {
    "booking_id": 789,
    "passenger_name": "John Doe",
    "status": "checked_in",
    "gate": "B15"
  }
}
```

#### System Alerts
```json
{
  "type": "system_alert",
  "data": {
    "level": "warning",
    "message": "Security alert at gate A12",
    "category": "security",
    "timestamp": "2025-01-15T08:30:00Z"
  }
}
```

## Error Handling

All API endpoints return standardized error responses:

```json
{
  "success": false,
  "message": "Error description",
  "code": "ERROR_CODE"
}
```

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `409` - Conflict
- `422` - Unprocessable Entity
- `500` - Internal Server Error

### Error Codes
- `VALIDATION_ERROR` - Input validation failed
- `AUTHENTICATION_REQUIRED` - Authentication required
- `INSUFFICIENT_PERMISSIONS` - User lacks required permissions
- `RESOURCE_NOT_FOUND` - Requested resource not found
- `DUPLICATE_ENTRY` - Resource already exists
- `DATABASE_ERROR` - Database operation failed

## Rate Limiting

API endpoints are rate-limited to prevent abuse:
- General endpoints: 100 requests per minute
- Authentication endpoints: 10 requests per minute
- Administrative endpoints: 50 requests per minute

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

## Data Formats

### Date/Time Format
All dates and times use ISO 8601 format:
```
2025-01-15T08:30:00Z
```

### Currency Format
Currency values are stored as decimal numbers with 2 decimal places:
```json
{
  "amount": 299.99,
  "currency": "USD"
}
```

### Pagination
List endpoints support pagination with the following parameters:
```json
{
  "page": 1,
  "limit": 20,
  "total": 150,
  "pages": 8
}
```

## Security Considerations

1. **HTTPS Only**: All production deployments must use HTTPS
2. **Token Expiration**: JWT tokens expire after 1 hour
3. **Password Requirements**: Minimum 8 characters, mixed case, numbers, and symbols
4. **Input Validation**: All inputs are validated and sanitized
5. **SQL Injection Protection**: Prepared statements used throughout
6. **CORS Configuration**: Properly configured for allowed origins
7. **Audit Logging**: All operations are logged for security monitoring

## Development & Testing

### Running Tests
```bash
# PHP Unit Tests
cd backend
./vendor/bin/phpunit

# Frontend Tests
cd frontend
npm test
```

### API Testing Tools
- Postman Collection: `docs/FlightControlAPI.postman_collection.json`
- Swagger Documentation: `docs/api-docs.html`

## Support

For API support and questions:
- Email: api-support@flightcontrol.com
- Documentation: https://docs.flightcontrol.com
- Issue Tracker: https://github.com/flightcontrol/api/issues

## Module APIs

### Module Management

#### Get All Modules
```http
GET /api/modules.php
Authorization: Bearer <token>
```

**Response:**
```json
{
  "success": true,
  "modules": [
    {
      "module_id": "sustainability",
      "name": "Environmental & Sustainability",
      "enabled": true,
      "version": "1.0.0",
      "dependencies": ["passenger_services"]
    }
  ]
}
```

#### Enable Module
```http
POST /api/modules.php?action=enable
Content-Type: application/json
Authorization: Bearer <token>

{
  "module_id": "sustainability",
  "user_id": "admin"
}
```

#### Disable Module
```http
POST /api/modules.php?action=disable
Content-Type: application/json
Authorization: Bearer <token>

{
  "module_id": "sustainability",
  "user_id": "admin"
}
```

#### Get Module Configuration
```http
GET /api/modules.php/config/sustainability
Authorization: Bearer <token>
```

#### Update Module Configuration
```http
PUT /api/modules.php/config
Content-Type: application/json
Authorization: Bearer <token>

{
  "module_id": "sustainability",
  "config_key": "carbon_tracking_enabled",
  "config_value": "true",
  "data_type": "boolean"
}
```

### Sustainability Module API

#### Record Carbon Emission
```http
POST /api/sustainability.php?action=record_emission
Content-Type: application/json
Authorization: Bearer <token>

{
  "source_type": "building",
  "source_id": "terminal_a",
  "emission_type": "co2",
  "amount_kg": 1500.5,
  "measurement_date": "2025-01-15",
  "measurement_method": "calculated",
  "confidence_level": 95.2
}
```

#### Get Carbon Emissions
```http
GET /api/sustainability.php?action=get_emissions&source_id=terminal_a&start_date=2025-01-01&end_date=2025-01-31
Authorization: Bearer <token>
```

#### Record Noise Monitoring
```http
POST /api/sustainability.php?action=record_noise
Content-Type: application/json
Authorization: Bearer <token>

{
  "location": "runway_27l",
  "decibel_level": 85.5,
  "measurement_time": "2025-01-15T14:30:00Z",
  "duration_minutes": 15,
  "aircraft_type": "B737",
  "weather_conditions": "clear"
}
```

#### Get Sustainability Dashboard
```http
GET /api/sustainability.php?action=dashboard
Authorization: Bearer <token>
```

### Infrastructure Management Module API

#### Create Building
```http
POST /api/infrastructure.php?action=create_building
Content-Type: application/json
Authorization: Bearer <token>

{
  "building_name": "Terminal A",
  "building_type": "terminal",
  "total_area_sqm": 50000,
  "floor_count": 3,
  "construction_year": 2010,
  "energy_rating": "A"
}
```

#### Record Energy Consumption
```http
POST /api/infrastructure.php?action=record_energy
Content-Type: application/json
Authorization: Bearer <token>

{
  "building_id": "building_001",
  "energy_type": "electricity",
  "consumption_kwh": 15000,
  "cost_usd": 2250,
  "measurement_date": "2025-01-15",
  "peak_demand_kw": 500,
  "efficiency_rating": 85.5
}
```

#### Get Building Energy Consumption
```http
GET /api/infrastructure.php?action=get_energy&building_id=building_001&start_date=2025-01-01&end_date=2025-01-31
Authorization: Bearer <token>
```

#### Record IoT Sensor Reading
```http
POST /api/infrastructure.php?action=record_sensor
Content-Type: application/json
Authorization: Bearer <token>

{
  "sensor_id": "temp_sensor_001",
  "sensor_type": "temperature",
  "location": "terminal_a_level_2",
  "reading_value": 22.5,
  "unit": "celsius",
  "timestamp": "2025-01-15T14:30:00Z",
  "sensor_status": "active"
}
```

#### Get Infrastructure Dashboard
```http
GET /api/infrastructure.php?action=dashboard
Authorization: Bearer <token>
```

### Cargo Operations Module API

#### Create Cargo Manifest
```http
POST /api/cargo.php?action=create_manifest
Content-Type: application/json
Authorization: Bearer <token>

{
  "flight_id": 123,
  "cargo_type": "freight",
  "total_weight_kg": 2500,
  "total_volume_cbm": 15.5,
  "special_handling": ["perishable", "fragile"],
  "origin": "JFK",
  "destination": "LAX"
}
```

#### Record Cargo Item
```http
POST /api/cargo.php?action=record_item
Content-Type: application/json
Authorization: Bearer <token>

{
  "manifest_id": "manifest_001",
  "description": "Electronics shipment",
  "weight_kg": 150,
  "dimensions": "120x80x60",
  "value_usd": 5000,
  "origin_country": "China",
  "destination_country": "USA",
  "hazard_class": null
}
```

#### Get Cargo Manifest
```http
GET /api/cargo.php?action=get_manifest&manifest_id=manifest_001
Authorization: Bearer <token>
```

#### Record Temperature Reading
```http
POST /api/cargo.php?action=record_temperature
Content-Type: application/json
Authorization: Bearer <token>

{
  "cargo_id": "cargo_001",
  "temperature_celsius": 4.5,
  "humidity_percent": 65,
  "location": "cargo_hold_a",
  "timestamp": "2025-01-15T14:30:00Z"
}
```

#### Get Cargo Dashboard
```http
GET /api/cargo.php?action=dashboard
Authorization: Bearer <token>
```

### Emergency Management Module API

#### Create Emergency Protocol
```http
POST /api/emergency.php?action=create_protocol
Content-Type: application/json
Authorization: Bearer <token>

{
  "protocol_name": "Fire Evacuation",
  "protocol_type": "evacuation",
  "severity_level": "critical",
  "affected_zones": ["terminal_a", "terminal_b"],
  "response_actions": ["activate_alarms", "deploy_emergency_teams", "evacuate_passengers"],
  "estimated_duration_minutes": 30
}
```

#### Record Incident
```http
POST /api/emergency.php?action=record_incident
Content-Type: application/json
Authorization: Bearer <token>

{
  "incident_type": "medical_emergency",
  "severity_level": "high",
  "location": "gate_a12",
  "description": "Passenger experiencing chest pain",
  "affected_persons": 1,
  "medical_response_required": true,
  "evacuation_required": false
}
```

#### Get Active Emergencies
```http
GET /api/emergency.php?action=get_active
Authorization: Bearer <token>
```

#### Update Emergency Status
```http
PUT /api/emergency.php/incident_001
Content-Type: application/json
Authorization: Bearer <token>

{
  "status": "resolved",
  "resolution_notes": "Patient transported to hospital, stable condition",
  "response_time_minutes": 8,
  "affected_persons": 1
}
```

#### Get Emergency Dashboard
```http
GET /api/emergency.php?action=dashboard
Authorization: Bearer <token>
```

### Drone Operations Module API

#### Register Drone
```http
POST /api/drones.php?action=register
Content-Type: application/json
Authorization: Bearer <token>

{
  "drone_id": "DRONE001",
  "registration_number": "FAA-12345",
  "manufacturer": "DJI",
  "model": "Mavic 3",
  "drone_type": "multirotor",
  "max_takeoff_weight_kg": 0.9,
  "owner_name": "Airport Authority",
  "operator_license_number": "PILOT-001"
}
```

#### Create Flight Plan
```http
POST /api/drones.php?action=create_flight_plan
Content-Type: application/json
Authorization: Bearer <token>

{
  "drone_id": "DRONE001",
  "purpose": "inspection",
  "planned_departure": "2025-01-15T10:00:00Z",
  "planned_arrival": "2025-01-15T10:30:00Z",
  "max_altitude_meters": 120,
  "flight_path": {
    "type": "LineString",
    "coordinates": [[-73.7781, 40.6413], [-73.7800, 40.6420]]
  }
}
```

#### Approve Flight Plan
```http
POST /api/drones.php?action=approve_flight_plan
Content-Type: application/json
Authorization: Bearer <token>

{
  "flight_plan_id": "PLAN-20250115-0001",
  "approved_by": "atc_controller",
  "approval_notes": "Weather conditions acceptable"
}
```

#### Record Flight Operation
```http
POST /api/drones.php?action=record_operation
Content-Type: application/json
Authorization: Bearer <token>

{
  "flight_plan_id": "PLAN-20250115-0001",
  "drone_id": "DRONE001",
  "actual_departure": "2025-01-15T10:05:00Z",
  "actual_arrival": "2025-01-15T10:28:00Z",
  "actual_duration_minutes": 23,
  "operational_notes": "Inspection completed successfully"
}
```

#### Record Drone Telemetry
```http
POST /api/drones.php?action=record_telemetry
Content-Type: application/json
Authorization: Bearer <token>

{
  "drone_id": "DRONE001",
  "operation_id": "OP-20250115-0001",
  "latitude": 40.6413,
  "longitude": -73.7781,
  "altitude_msl": 100,
  "ground_speed_kmh": 25,
  "battery_percentage": 85,
  "signal_strength_dbm": -45
}
```

#### Get Drone Dashboard
```http
GET /api/drones.php?action=dashboard
Authorization: Bearer <token>
```

### Customs & Border Protection Module API

#### Register Passport
```http
POST /api/customs.php?action=register_passport
Content-Type: application/json
Authorization: Bearer <token>

{
  "passport_number": "A12345678",
  "issuing_country": "United States",
  "issue_date": "2020-01-15",
  "expiry_date": "2030-01-14",
  "holder_name": "John Smith",
  "holder_nationality": "American",
  "holder_birth_date": "1985-03-15"
}
```

#### Process Border Entry
```http
POST /api/customs.php?action=process_entry
Content-Type: application/json
Authorization: Bearer <token>

{
  "passport_number": "A12345678",
  "entry_type": "arrival",
  "port_of_entry": "JFK Terminal 1",
  "flight_number": "AA001",
  "purpose_of_visit": "tourism",
  "intended_stay_days": 14
}
```

#### Create Customs Declaration
```http
POST /api/customs.php?action=create_declaration
Content-Type: application/json
Authorization: Bearer <token>

{
  "passenger_data": {
    "passport_id": "PASS001",
    "flight_number": "AA001"
  },
  "goods_data": {
    "description": "Laptop computer",
    "value_usd": 1200,
    "quantity": 1,
    "origin_country": "USA"
  }
}
```

#### Validate Passport
```http
POST /api/customs.php?action=validate_passport
Content-Type: application/json
Authorization: Bearer <token>

{
  "passport_number": "A12345678"
}
```

#### Check Visa Validity
```http
POST /api/customs.php?action=check_visa
Content-Type: application/json
Authorization: Bearer <token>

{
  "passport_id": "PASS001",
  "entry_date": "2025-01-15"
}
```

#### Record Biometric Data
```http
POST /api/customs.php?action=record_biometric
Content-Type: application/json
Authorization: Bearer <token>

{
  "passport_id": "PASS001",
  "biometric_type": "fingerprint",
  "capture_device": "biometric_scanner_001",
  "quality_score": 0.95,
  "verification_status": "verified"
}
```

#### Get Border Control Dashboard
```http
GET /api/customs.php?action=dashboard
Authorization: Bearer <token>
```

### Advanced Security Module API

#### Process Facial Recognition
```http
POST /api/advanced-security.php?action=process_facial
Content-Type: application/json
Authorization: Bearer <token>

{
  "facial_features": {
    "landmarks": [[100, 150], [120, 145], [140, 155]],
    "confidence_score": 0.92
  },
  "capture_location": "terminal_a_entrance",
  "person_id": null
}
```

#### Analyze Behavior
```http
POST /api/advanced-security.php?action=analyze_behavior
Content-Type: application/json
Authorization: Bearer <token>

{
  "behavior_type": "loitering",
  "location_zone": "waiting_area_b",
  "duration_seconds": 450,
  "person_id": "PERSON001",
  "anomaly_detected": true,
  "anomaly_confidence": 0.85
}
```

#### Register Security Camera
```http
POST /api/advanced-security.php?action=register_camera
Content-Type: application/json
Authorization: Bearer <token>

{
  "camera_name": "Terminal A Main Entrance",
  "location_zone": "ZONE-ARRIVAL",
  "resolution_width": 1920,
  "resolution_height": 1080,
  "facial_recognition_enabled": true,
  "behavioral_analytics_enabled": true
}
```

#### Detect Threat
```http
POST /api/advanced-security.php?action=detect_threat
Content-Type: application/json
Authorization: Bearer <token>

{
  "event_type": "suspicious_behavior",
  "severity_level": "medium",
  "location_zone": "ZONE-SECURITY",
  "confidence_score": 0.78,
  "person_id": "PERSON001",
  "description": "Individual pacing nervously near security checkpoint"
}
```

#### Create Security Zone
```http
POST /api/advanced-security.php?action=create_zone
Content-Type: application/json
Authorization: Bearer <token>

{
  "zone_name": "Security Screening Area",
  "zone_type": "secure",
  "security_level": "high",
  "boundary_coordinates": {
    "type": "Polygon",
    "coordinates": [[[0,0], [50,0], [50,30], [0,30], [0,0]]]
  },
  "area_sqm": 1500
}
```

#### Record Access Event
```http
POST /api/advanced-security.php?action=record_access
Content-Type: application/json
Authorization: Bearer <token>

{
  "person_id": "PERSON001",
  "zone_id": "ZONE-SECURITY",
  "access_type": "entry",
  "access_method": "biometric",
  "authorization_status": "granted",
  "processing_time_ms": 250
}
```

#### Report Suspicious Activity
```http
POST /api/advanced-security.php?action=report_activity
Content-Type: application/json
Authorization: Bearer <token>

{
  "activity_type": "unattended_baggage",
  "severity_level": "medium",
  "location_zone": "ZONE-DEPARTURE",
  "description": "Black suitcase left unattended near gate A12",
  "camera_feeds": ["CAM-DEPARTURE-001"]
}
```

#### Record Security Incident
```http
POST /api/advanced-security.php?action=record_incident
Content-Type: application/json
Authorization: Bearer <token>

{
  "incident_type": "theft",
  "severity_level": "medium",
  "location_zone": "ZONE-ARRIVAL",
  "description": "Passenger wallet reported stolen",
  "suspect_description": {
    "height_cm": 175,
    "build": "medium",
    "clothing": "blue jacket, jeans"
  }
}
```

#### Create Security Alert
```http
POST /api/advanced-security.php?action=create_alert
Content-Type: application/json
Authorization: Bearer <token>

{
  "alert_type": "system_failure",
  "severity_level": "high",
  "alert_title": "Camera System Offline",
  "alert_description": "Security camera CAM-ARRIVAL-001 is offline",
  "affected_systems": ["facial_recognition", "behavioral_analytics"],
  "recommended_actions": ["dispatch_technician", "increase_security_presence"]
}
```

#### Get Security Dashboard
```http
GET /api/advanced-security.php?action=dashboard
Authorization: Bearer <token>
```

## Version History

### v1.0.0 (Current)
- Initial release with core flight management features
- Real-time WebSocket support
- Comprehensive security and authentication
- Full passenger and booking management
- Baggage tracking system
- Maintenance scheduling
- Analytics and reporting

### v1.1.0 (Modules Release)
- **Module System**: Pluggable architecture for enhanced functionality
- **Sustainability Module**: Carbon emissions, noise monitoring, green energy tracking
- **Infrastructure Management**: Building systems, IoT sensors, utilities monitoring
- **Cargo Operations**: Freight forwarding, customs clearance, hazardous materials
- **Emergency Management**: Crisis response, incident management, evacuation procedures
- **Drone Operations**: UAV traffic control, airspace management, regulatory compliance
- **Customs & Border Protection**: Passport management, border control, immigration tracking
- **Advanced Security**: Facial recognition, behavioral analytics, threat detection

### Planned Features (v1.2.0)
- AI Automation & Support Module
- Virtual Assistant Module
- Enhanced mobile applications
- Advanced analytics dashboards
- Automated reporting systems
- Third-party integrations
- Performance optimizations
