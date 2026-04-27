-- =====================================================
-- TPT FLIGHT CONTROL SAFETY FOUNDATION SCHEMA
-- Phase 23: Safety Foundation Layer
-- ICAO Annex 11 / FAA Order 7110.65 Compliant
-- =====================================================

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = true;
SET client_min_messages = warning;
SET row_security = on;

-- =====================================================
-- 1. WRITE AHEAD LOGGING SYSTEM
-- Immutable cryptographically signed operation log
-- =====================================================

CREATE TABLE safety_operation_log (
    log_id BIGSERIAL PRIMARY KEY,
    timestamp TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    sequence_number BIGINT NOT NULL UNIQUE,
    operation_type VARCHAR(64) NOT NULL,
    actor_id UUID,
    subject_type VARCHAR(64),
    subject_id VARCHAR(128),
    operation_data JSONB NOT NULL,
    previous_hash CHAR(64) NOT NULL,
    entry_hash CHAR(64) NOT NULL UNIQUE,
    signature VARCHAR(128) NOT NULL,
    node_id VARCHAR(64) NOT NULL,
    process_id VARCHAR(64) NOT NULL,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verification_timestamp TIMESTAMPTZ,

    CONSTRAINT chk_sequence_order CHECK (sequence_number > 0),
    CONSTRAINT chk_hash_format CHECK (entry_hash ~ '^[a-f0-9]{64}$'),
    CONSTRAINT chk_previous_hash_format CHECK (previous_hash ~ '^[a-f0-9]{64}$')
);

-- Immutable trigger: prevent UPDATE/DELETE on log table
CREATE OR REPLACE FUNCTION safety_log_immutable()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'Safety operation log is immutable. Modifications are not permitted.';
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

CREATE TRIGGER trigger_safety_log_immutable
    BEFORE UPDATE OR DELETE ON safety_operation_log
    FOR EACH ROW EXECUTE FUNCTION safety_log_immutable();

CREATE UNIQUE INDEX idx_safety_log_sequence ON safety_operation_log(sequence_number);
CREATE INDEX idx_safety_log_timestamp ON safety_operation_log(timestamp);
CREATE INDEX idx_safety_log_operation ON safety_operation_log(operation_type);
CREATE INDEX idx_safety_log_actor ON safety_operation_log(actor_id);

-- =====================================================
-- 2. WATCHDOG MONITOR
-- Independent dead man switch tracking
-- =====================================================

CREATE TABLE safety_watchdog_status (
    process_id VARCHAR(128) PRIMARY KEY,
    process_name VARCHAR(64) NOT NULL,
    process_priority INTEGER NOT NULL CHECK (process_priority BETWEEN 1 AND 10),
    last_heartbeat TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    heartbeat_interval_ms INTEGER NOT NULL CHECK (heartbeat_interval_ms > 0),
    missed_heartbeats INTEGER NOT NULL DEFAULT 0,
    max_missed_heartbeats INTEGER NOT NULL CHECK (max_missed_heartbeats > 0),
    process_status VARCHAR(32) NOT NULL DEFAULT 'HEALTHY',
    last_status_change TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    failure_action VARCHAR(64) NOT NULL DEFAULT 'ALERT',
    is_monitored BOOLEAN NOT NULL DEFAULT true,

    CONSTRAINT chk_valid_status CHECK (process_status IN ('HEALTHY', 'DEGRADED', 'FAILED', 'TERMINATED', 'UNKNOWN'))
);

CREATE INDEX idx_watchdog_status ON safety_watchdog_status(process_status);
CREATE INDEX idx_watchdog_heartbeat ON safety_watchdog_status(last_heartbeat);

-- =====================================================
-- 3. SAFETY BOUNDARY ENGINE
-- Hard coded physical limits enforcement
-- =====================================================

CREATE TABLE safety_boundary_definitions (
    boundary_id VARCHAR(64) PRIMARY KEY,
    boundary_name VARCHAR(128) NOT NULL,
    boundary_type VARCHAR(64) NOT NULL,
    priority INTEGER NOT NULL DEFAULT 50 CHECK (priority BETWEEN 1 AND 100),
    minimum_value DOUBLE PRECISION,
    maximum_value DOUBLE PRECISION,
    geometry GEOGRAPHY(POLYGON, 4326),
    violation_severity INTEGER NOT NULL CHECK (violation_severity BETWEEN 1 AND 7),
    is_enforced BOOLEAN NOT NULL DEFAULT true,
    last_updated TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_by UUID NOT NULL,

    CONSTRAINT chk_boundary_type CHECK (boundary_type IN (
        'ALTITUDE', 'SPEED', 'VERTICAL_SPEED', 'HEADING',
        'SEPARATION_HORIZONTAL', 'SEPARATION_VERTICAL',
        'AIRSPACE_RESTRICTED', 'RUNWAY_OCCUPANCY',
        'PERFORMANCE_LIMIT', 'SYSTEM_PARAMETER'
    ))
);

CREATE INDEX idx_boundary_type ON safety_boundary_definitions(boundary_type);
CREATE INDEX idx_boundary_enforced ON safety_boundary_definitions(is_enforced);

-- =====================================================
-- 4. ALERT ESCALATION HIERARCHY
-- 7 level alert system with dead man acknowledgement
-- =====================================================

CREATE TABLE safety_alerts (
    alert_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    alert_level INTEGER NOT NULL CHECK (alert_level BETWEEN 0 AND 6),
    alert_type VARCHAR(64) NOT NULL,
    alert_message TEXT NOT NULL,
    source_component VARCHAR(128) NOT NULL,
    timestamp TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    acknowledged BOOLEAN NOT NULL DEFAULT false,
    acknowledged_by UUID,
    acknowledged_timestamp TIMESTAMPTZ,
    escalation_level INTEGER NOT NULL DEFAULT 0,
    last_escalation TIMESTAMPTZ,
    next_escalation TIMESTAMPTZ,
    alert_status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
    assigned_to UUID,
    resolution_notes TEXT,
    resolved_timestamp TIMESTAMPTZ,

    CONSTRAINT chk_alert_status CHECK (alert_status IN ('ACTIVE', 'ACKNOWLEDGED', 'ESCALATED', 'RESOLVED', 'CLOSED'))
);

CREATE INDEX idx_alerts_level ON safety_alerts(alert_level);
CREATE INDEX idx_alerts_status ON safety_alerts(alert_status);
CREATE INDEX idx_alerts_timestamp ON safety_alerts(timestamp);
CREATE INDEX idx_alerts_assigned ON safety_alerts(assigned_to);

-- =====================================================
-- 5. SENSOR HEALTH MANAGER
-- Per sensor confidence scoring and fault detection
-- =====================================================

CREATE TABLE sensor_health_metrics (
    sensor_id VARCHAR(128) PRIMARY KEY,
    sensor_type VARCHAR(64) NOT NULL,
    sensor_location GEOGRAPHY(POINT, 4326),
    confidence_score DOUBLE PRECISION NOT NULL DEFAULT 1.0 CHECK (confidence_score BETWEEN 0.0 AND 1.0),
    last_update TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    update_frequency_ms INTEGER NOT NULL,
    missed_updates INTEGER NOT NULL DEFAULT 0,
    fault_count INTEGER NOT NULL DEFAULT 0,
    deviation_score DOUBLE PRECISION NOT NULL DEFAULT 0.0 CHECK (deviation_score BETWEEN 0.0 AND 1.0),
    noise_floor DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    signal_strength DOUBLE PRECISION NOT NULL DEFAULT 1.0,
    sensor_status VARCHAR(32) NOT NULL DEFAULT 'OPERATIONAL',
    fault_flags INTEGER NOT NULL DEFAULT 0,
    last_calibration TIMESTAMPTZ,

    CONSTRAINT chk_sensor_status CHECK (sensor_status IN (
        'OPERATIONAL', 'DEGRADED', 'FAULTY', 'FAILED', 'CALIBRATING', 'OFFLINE'
    ))
);

CREATE INDEX idx_sensor_health ON sensor_health_metrics(sensor_status);
CREATE INDEX idx_sensor_confidence ON sensor_health_metrics(confidence_score);
CREATE INDEX idx_sensor_type ON sensor_health_metrics(sensor_type);

-- =====================================================
-- 6. SAFETY AUDIT TRAIL
-- All safety related operations audit log
-- =====================================================

CREATE TABLE safety_audit_trail (
    audit_id BIGSERIAL PRIMARY KEY,
    timestamp TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    component VARCHAR(128) NOT NULL,
    action VARCHAR(64) NOT NULL,
    actor_id UUID,
    subject_id VARCHAR(128),
    old_value JSONB,
    new_value JSONB,
    change_reason TEXT,
    client_ip INET,
    session_id VARCHAR(128),

    CONSTRAINT chk_valid_action CHECK (action IN (
        'CREATE', 'MODIFY', 'DELETE', 'ENABLE', 'DISABLE',
        'ACKNOWLEDGE', 'ESCALATE', 'RESOLVE', 'OVERRIDE',
        'CALIBRATE', 'RESET', 'FAILOVER'
    ))
);

CREATE INDEX idx_audit_timestamp ON safety_audit_trail(timestamp);
CREATE INDEX idx_audit_component ON safety_audit_trail(component);
CREATE INDEX idx_audit_action ON safety_audit_trail(action);

-- =====================================================
-- SAFETY CONFIGURATION
-- =====================================================

CREATE TABLE safety_configuration (
    config_key VARCHAR(128) PRIMARY KEY,
    config_value VARCHAR(256) NOT NULL,
    config_type VARCHAR(32) NOT NULL DEFAULT 'STRING',
    is_immutable BOOLEAN NOT NULL DEFAULT false,
    description TEXT,
    last_updated TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_by UUID
);

-- Insert default safety boundaries (HARD CODED LIMITS)
INSERT INTO safety_boundary_definitions (boundary_id, boundary_name, boundary_type, priority, minimum_value, maximum_value, violation_severity, is_enforced, updated_by) VALUES
('MIN_ALTITUDE_GROUND', 'Minimum Ground Altitude', 'ALTITUDE', 100, 0, 500, 5, true, '00000000-0000-0000-0000-000000000000'),
('MAX_SPEED_TERMINAL', 'Maximum Terminal Area Speed', 'SPEED', 90, 0, 250, 4, true, '00000000-0000-0000-0000-000000000000'),
('MIN_SEPARATION_HORIZONTAL', 'Minimum Horizontal Separation', 'SEPARATION_HORIZONTAL', 100, 3, NULL, 6, true, '00000000-0000-0000-0000-000000000000'),
('MIN_SEPARATION_VERTICAL', 'Minimum Vertical Separation', 'SEPARATION_VERTICAL', 100, 1000, NULL, 6, true, '00000000-0000-0000-0000-000000000000'),
('MAX_RUNWAY_OCCUPANCY', 'Maximum Runway Occupancy Time', 'RUNWAY_OCCUPANCY', 95, 0, 90, 5, true, '00000000-0000-0000-0000-000000000000'),
('MAX_VERTICAL_SPEED', 'Maximum Vertical Speed', 'VERTICAL_SPEED', 80, -6000, 6000, 3, true, '00000000-0000-0000-0000-000000000000');

-- Insert default watchdog configuration
INSERT INTO safety_configuration (config_key, config_value, config_type, is_immutable, description) VALUES
('WATCHDOG_GLOBAL_ENABLED', 'true', 'BOOLEAN', true, 'Global watchdog monitoring enable'),
('WATCHDOG_DEFAULT_HEARTBEAT_INTERVAL', '1000', 'INTEGER', false, 'Default heartbeat interval in ms'),
('WATCHDOG_DEFAULT_MAX_MISSED', '3', 'INTEGER', false, 'Default maximum missed heartbeats'),
('ALERT_ESCALATION_INTERVAL_BASE', '30', 'INTEGER', false, 'Base alert escalation interval in seconds'),
('ALERT_ESCALATION_MULTIPLIER', '0.5', 'FLOAT', false, 'Alert escalation time multiplier per level'),
('SENSOR_HEALTH_DECAY_RATE', '0.01', 'FLOAT', false, 'Confidence score decay per missed update'),
('SAFETY_LOG_SIGNING_ENABLED', 'true', 'BOOLEAN', true, 'Enable cryptographic log signing'),
('SAFETY_FAIL_CLOSED', 'true', 'BOOLEAN', true, 'Fail closed on safety component failure');

GRANT SELECT ON ALL TABLES IN SCHEMA public TO safety_reader;
GRANT INSERT ON safety_operation_log, safety_alerts, sensor_health_metrics, safety_audit_trail TO safety_writer;

-- =====================================================
-- 7. RAFT CONSENSUS CLUSTER
-- Phase 25: Cluster & Failover System
-- =====================================================

CREATE TABLE raft_state (
    node_id VARCHAR(64) PRIMARY KEY,
    term BIGINT NOT NULL DEFAULT 0,
    voted_for VARCHAR(64),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_heartbeat TIMESTAMPTZ
);

CREATE TABLE raft_log_entries (
    log_index BIGINT PRIMARY KEY,
    term BIGINT NOT NULL,
    command VARCHAR(128) NOT NULL,
    data JSONB NOT NULL,
    timestamp TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    committed BOOLEAN NOT NULL DEFAULT FALSE,
    applied BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE raft_cluster_peers (
    peer_id VARCHAR(64) PRIMARY KEY,
    peer_address VARCHAR(256) NOT NULL,
    peer_port INTEGER NOT NULL,
    last_seen TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    status VARCHAR(32) NOT NULL DEFAULT 'ONLINE',
    priority INTEGER NOT NULL DEFAULT 50,
    is_voting_member BOOLEAN NOT NULL DEFAULT true
);

CREATE INDEX idx_raft_log_term ON raft_log_entries(term);
CREATE INDEX idx_raft_log_committed ON raft_log_entries(committed);
CREATE INDEX idx_raft_peer_status ON raft_cluster_peers(status);

-- =====================================================
-- SAFETY CONFIGURATION
-- =====================================================

INSERT INTO safety_configuration (config_key, config_value, config_type, is_immutable, description) VALUES
('RAFT_CLUSTER_ENABLED', 'true', 'BOOLEAN', false, 'Enable Raft consensus cluster'),
('RAFT_ELECTION_TIMEOUT_MIN', '150', 'INTEGER', true, 'Minimum election timeout in milliseconds'),
('RAFT_ELECTION_TIMEOUT_MAX', '300', 'INTEGER', true, 'Maximum election timeout in milliseconds'),
('RAFT_HEARTBEAT_INTERVAL', '50', 'INTEGER', true, 'Leader heartbeat interval in milliseconds'),
('RAFT_MAJORITY_REQUIRED', '2', 'INTEGER', true, 'Quorum required for 3 node cluster'),
('RAFT_FAILOVER_THRESHOLD', '500', 'INTEGER', true, 'Maximum failover time in milliseconds');

-- No user shall have UPDATE/DELETE privileges on safety tables
REVOKE UPDATE, DELETE ON ALL TABLES IN SCHEMA public FROM public;
