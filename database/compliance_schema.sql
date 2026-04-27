-- Flight Control Database Schema - Compliance Module
-- Audit logs, GDPR compliance, and data retention policies

-- Compliance and Audit System
CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(100) NOT NULL,
    resource_id VARCHAR(100),
    details JSONB,
    ip_address INET,
    user_agent TEXT,
    session_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE compliance_reports (
    id SERIAL PRIMARY KEY,
    report_type VARCHAR(100) NOT NULL,
    report_data JSONB,
    generated_by INTEGER REFERENCES users(id),
    period_start DATE,
    period_end DATE,
    status VARCHAR(50) DEFAULT 'generated',
    file_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_retention_policies (
    id SERIAL PRIMARY KEY,
    data_type VARCHAR(100) NOT NULL,
    retention_period_days INTEGER NOT NULL,
    archival_required BOOLEAN DEFAULT FALSE,
    encryption_required BOOLEAN DEFAULT TRUE,
    deletion_method VARCHAR(50) DEFAULT 'hard_delete',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_deletion_logs (
    id SERIAL PRIMARY KEY,
    data_type VARCHAR(100) NOT NULL,
    record_count INTEGER NOT NULL,
    deletion_method VARCHAR(50) NOT NULL,
    reason VARCHAR(255),
    executed_by INTEGER REFERENCES users(id),
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- GDPR Compliance Features
CREATE TABLE data_subject_consents (
    id SERIAL PRIMARY KEY,
    consent_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100) NOT NULL, -- user_id, passenger_id, or external identifier
    data_subject_type VARCHAR(20) NOT NULL, -- user, passenger, employee
    consent_type VARCHAR(50) NOT NULL, -- marketing, analytics, profiling, etc.
    consent_given BOOLEAN NOT NULL,
    consent_date TIMESTAMP,
    consent_expiry TIMESTAMP,
    consent_withdrawn BOOLEAN DEFAULT FALSE,
    withdrawal_date TIMESTAMP,
    consent_version VARCHAR(20),
    legal_basis VARCHAR(100), -- consent, legitimate_interest, contract, etc.
    consent_scope TEXT,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_processing_activities (
    id SERIAL PRIMARY KEY,
    activity_id VARCHAR(50) UNIQUE NOT NULL,
    activity_name VARCHAR(200) NOT NULL,
    activity_description TEXT,
    legal_basis VARCHAR(100) NOT NULL,
    purpose VARCHAR(200) NOT NULL,
    data_categories JSONB, -- personal_data, sensitive_data, etc.
    data_subjects JSONB, -- customers, employees, etc.
    recipients JSONB, -- internal, external, third_parties
    retention_period VARCHAR(50),
    automated_decision_making BOOLEAN DEFAULT FALSE,
    international_transfer BOOLEAN DEFAULT FALSE,
    transfer_countries JSONB,
    dpo_approval_required BOOLEAN DEFAULT FALSE,
    dpo_approved BOOLEAN DEFAULT FALSE,
    dpo_approval_date TIMESTAMP,
    risk_assessment JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_subject_rights_requests (
    id SERIAL PRIMARY KEY,
    request_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100) NOT NULL,
    data_subject_type VARCHAR(20) NOT NULL,
    request_type VARCHAR(50) NOT NULL, -- access, rectification, erasure, restriction, portability, objection
    request_details TEXT,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) DEFAULT 'pending', -- pending, in_progress, completed, rejected
    completion_deadline TIMESTAMP,
    completed_date TIMESTAMP,
    response_provided TEXT,
    verification_method VARCHAR(50), -- identity_document, email_verification, etc.
    verification_status VARCHAR(20) DEFAULT 'pending',
    appeal_requested BOOLEAN DEFAULT FALSE,
    appeal_details TEXT,
    appeal_date TIMESTAMP,
    appeal_status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_breach_notifications (
    id SERIAL PRIMARY KEY,
    breach_id VARCHAR(50) UNIQUE NOT NULL,
    breach_date TIMESTAMP NOT NULL,
    discovery_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    breach_description TEXT NOT NULL,
    data_categories_affected JSONB,
    number_of_subjects_affected INTEGER,
    potential_consequences TEXT,
    measures_taken TEXT,
    supervisory_authority_notified BOOLEAN DEFAULT FALSE,
    notification_date TIMESTAMP,
    notification_reference VARCHAR(100),
    data_subjects_notified BOOLEAN DEFAULT FALSE,
    subjects_notification_date TIMESTAMP,
    risk_assessment JSONB,
    dpo_notified BOOLEAN DEFAULT FALSE,
    dpo_notification_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE privacy_impact_assessments (
    id SERIAL PRIMARY KEY,
    assessment_id VARCHAR(50) UNIQUE NOT NULL,
    project_name VARCHAR(200) NOT NULL,
    assessment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_protection_officer VARCHAR(100),
    processing_activities JSONB,
    data_flows JSONB,
    risks_identified JSONB,
    mitigation_measures JSONB,
    residual_risks JSONB,
    recommendations TEXT,
    approval_status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected
    approval_date TIMESTAMP,
    review_date TIMESTAMP,
    next_review_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_retention_schedules (
    id SERIAL PRIMARY KEY,
    schedule_id VARCHAR(50) UNIQUE NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    retention_purpose VARCHAR(200),
    retention_period VARCHAR(50) NOT NULL,
    retention_basis VARCHAR(100),
    disposal_method VARCHAR(50),
    review_frequency VARCHAR(20),
    last_review_date TIMESTAMP,
    next_review_date TIMESTAMP,
    legal_exceptions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cookie_consent_preferences (
    id SERIAL PRIMARY KEY,
    preference_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(100),
    session_id VARCHAR(255),
    ip_address INET,
    user_agent TEXT,
    necessary_cookies BOOLEAN DEFAULT TRUE,
    analytics_cookies BOOLEAN DEFAULT FALSE,
    marketing_cookies BOOLEAN DEFAULT FALSE,
    functional_cookies BOOLEAN DEFAULT FALSE,
    preferences_cookies BOOLEAN DEFAULT FALSE,
    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consent_expiry TIMESTAMP,
    consent_withdrawn BOOLEAN DEFAULT FALSE,
    withdrawal_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_anonymization_logs (
    id SERIAL PRIMARY KEY,
    log_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100),
    data_category VARCHAR(100),
    anonymization_method VARCHAR(50), -- pseudonymization, aggregation, masking, etc.
    anonymization_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    anonymization_reason VARCHAR(200),
    original_data_hash VARCHAR(128),
    anonymized_data_hash VARCHAR(128),
    reversibility BOOLEAN DEFAULT FALSE,
    retention_period VARCHAR(50),
    disposal_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE gdpr_audit_logs (
    id SERIAL PRIMARY KEY,
    audit_id VARCHAR(50) UNIQUE NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    data_subject_id VARCHAR(100),
    user_id VARCHAR(100),
    action_details JSONB,
    ip_address INET,
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    compliance_status VARCHAR(20) DEFAULT 'compliant'
);

-- Data Retention Policies
CREATE TABLE data_archival_logs (
    id SERIAL PRIMARY KEY,
    archival_id VARCHAR(50) UNIQUE NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    record_count INTEGER NOT NULL,
    archival_method VARCHAR(50), -- compression, encryption, offsite
    storage_location VARCHAR(200),
    archival_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    retention_period VARCHAR(50),
    disposal_date TIMESTAMP,
    checksum VARCHAR(128),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_disposal_logs (
    id SERIAL PRIMARY KEY,
    disposal_id VARCHAR(50) UNIQUE NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    record_count INTEGER NOT NULL,
    disposal_method VARCHAR(50), -- secure_deletion, shredding, degaussing
    disposal_reason VARCHAR(200),
    disposal_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    disposed_by VARCHAR(100),
    verification_method VARCHAR(50),
    compliance_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE retention_policy_executions (
    id SERIAL PRIMARY KEY,
    execution_id VARCHAR(50) UNIQUE NOT NULL,
    policy_id VARCHAR(50) NOT NULL,
    execution_type VARCHAR(30), -- archival, deletion, review
    records_processed INTEGER NOT NULL DEFAULT 0,
    execution_status VARCHAR(20), -- pending, running, completed, failed
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    error_message TEXT,
    next_execution_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_retention_exceptions (
    id SERIAL PRIMARY KEY,
    exception_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100),
    data_category VARCHAR(100) NOT NULL,
    exception_type VARCHAR(50), -- legal_hold, regulatory_requirement, business_need
    exception_reason TEXT,
    exception_duration VARCHAR(50),
    approved_by VARCHAR(100),
    approval_date TIMESTAMP,
    expiry_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active', -- active, expired, revoked
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_lifecycle_events (
    id SERIAL PRIMARY KEY,
    event_id VARCHAR(50) UNIQUE NOT NULL,
    data_subject_id VARCHAR(100),
    data_category VARCHAR(100) NOT NULL,
    event_type VARCHAR(30), -- created, accessed, modified, archived, deleted
    event_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id VARCHAR(100),
    ip_address INET,
    user_agent TEXT,
    event_details JSONB,
    compliance_status VARCHAR(20) DEFAULT 'compliant'
);

CREATE TABLE storage_optimization_metrics (
    id SERIAL PRIMARY KEY,
    metric_id VARCHAR(50) UNIQUE NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    total_records BIGINT NOT NULL DEFAULT 0,
    active_records BIGINT NOT NULL DEFAULT 0,
    archived_records BIGINT NOT NULL DEFAULT 0,
    deleted_records BIGINT NOT NULL DEFAULT 0,
    storage_size_bytes BIGINT NOT NULL DEFAULT 0,
    compression_ratio DECIMAL(5,2),
    last_optimization_date TIMESTAMP,
    next_optimization_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for compliance tables
CREATE INDEX idx_audit_logs_user ON audit_logs (user_id);
CREATE INDEX idx_audit_logs_action ON audit_logs (action);
CREATE INDEX idx_audit_logs_resource ON audit_logs (resource_type, resource_id);
CREATE INDEX idx_audit_logs_timestamp ON audit_logs (created_at);
CREATE INDEX idx_compliance_reports_type ON compliance_reports (report_type);
CREATE INDEX idx_data_deletion_logs_type ON data_deletion_logs (data_type);

CREATE INDEX idx_data_subject_consents_subject ON data_subject_consents (data_subject_id, data_subject_type);
CREATE INDEX idx_data_subject_consents_type ON data_subject_consents (consent_type);
CREATE INDEX idx_data_subject_consents_withdrawn ON data_subject_consents (consent_withdrawn);

CREATE INDEX idx_data_processing_activities_id ON data_processing_activities (activity_id);
CREATE INDEX idx_data_processing_activities_basis ON data_processing_activities (legal_basis);

CREATE INDEX idx_data_subject_rights_requests_subject ON data_subject_rights_requests (data_subject_id, data_subject_type);
CREATE INDEX idx_data_subject_rights_requests_type ON data_subject_rights_requests (request_type);
CREATE INDEX idx_data_subject_rights_requests_status ON data_subject_rights_requests (status);
CREATE INDEX idx_data_subject_rights_requests_deadline ON data_subject_rights_requests (completion_deadline);

CREATE INDEX idx_data_breach_notifications_id ON data_breach_notifications (breach_id);
CREATE INDEX idx_data_breach_notifications_date ON data_breach_notifications (breach_date);

CREATE INDEX idx_privacy_impact_assessments_id ON privacy_impact_assessments (assessment_id);
CREATE INDEX idx_privacy_impact_assessments_status ON privacy_impact_assessments (approval_status);

CREATE INDEX idx_data_retention_schedules_id ON data_retention_schedules (schedule_id);
CREATE INDEX idx_data_retention_schedules_category ON data_retention_schedules (data_category);

CREATE INDEX idx_cookie_consent_preferences_user ON cookie_consent_preferences (user_id);
CREATE INDEX idx_cookie_consent_preferences_session ON cookie_consent_preferences (session_id);

CREATE INDEX idx_data_anonymization_logs_subject ON data_anonymization_logs (data_subject_id);
CREATE INDEX idx_data_anonymization_logs_category ON data_anonymization_logs (data_category);

CREATE INDEX idx_gdpr_audit_logs_action ON gdpr_audit_logs (action_type);
CREATE INDEX idx_gdpr_audit_logs_subject ON gdpr_audit_logs (data_subject_id);
CREATE INDEX idx_gdpr_audit_logs_timestamp ON gdpr_audit_logs (timestamp);

CREATE INDEX idx_data_archival_logs_category ON data_archival_logs (data_category);
CREATE INDEX idx_data_archival_logs_date ON data_archival_logs (archival_date);
CREATE INDEX idx_data_disposal_logs_category ON data_disposal_logs (data_category);
CREATE INDEX idx_data_disposal_logs_date ON data_disposal_logs (disposal_date);
CREATE INDEX idx_retention_policy_executions_policy ON retention_policy_executions (policy_id);
CREATE INDEX idx_retention_policy_executions_status ON retention_policy_executions (execution_status);
CREATE INDEX idx_data_retention_exceptions_subject ON data_retention_exceptions (data_subject_id);
CREATE INDEX idx_data_retention_exceptions_category ON data_retention_exceptions (data_category);
CREATE INDEX idx_data_retention_exceptions_status ON data_retention_exceptions (status);
CREATE INDEX idx_data_lifecycle_events_subject ON data_lifecycle_events (data_subject_id);
CREATE INDEX idx_data_lifecycle_events_category ON data_lifecycle_events (data_category);
CREATE INDEX idx_data_lifecycle_events_type ON data_lifecycle_events (event_type);
CREATE INDEX idx_data_lifecycle_events_timestamp ON data_lifecycle_events (event_timestamp);
CREATE INDEX idx_storage_optimization_metrics_category ON storage_optimization_metrics (data_category);
