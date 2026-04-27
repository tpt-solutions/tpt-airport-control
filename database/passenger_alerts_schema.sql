-- Passenger Alerts System Schema
-- Manages real-time notifications and travel reminders for passengers

-- Alert templates for different types of notifications
CREATE TABLE IF NOT EXISTS alert_templates (
    template_id SERIAL PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    template_type VARCHAR(50) NOT NULL, -- 'flight_update', 'gate_change', 'delay', 'reminder', 'emergency'
    subject VARCHAR(255) NOT NULL,
    message_template TEXT NOT NULL,
    variables JSONB DEFAULT '{}', -- Available variables for template substitution
    channels JSONB DEFAULT '["push"]', -- Supported channels: push, sms, email
    priority VARCHAR(20) DEFAULT 'normal', -- 'low', 'normal', 'high', 'critical'
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Passenger notification preferences
CREATE TABLE IF NOT EXISTS notification_preferences (
    preference_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    alert_type VARCHAR(50), -- Specific alert type or 'all'
    channels JSONB DEFAULT '["push"]', -- Enabled channels for this alert type
    quiet_hours_start TIME,
    quiet_hours_end TIME,
    timezone VARCHAR(50) DEFAULT 'UTC',
    language VARCHAR(10) DEFAULT 'en',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(passenger_id, alert_type)
);

-- Alert instances (actual sent notifications)
CREATE TABLE IF NOT EXISTS alert_instances (
    alert_id SERIAL PRIMARY KEY,
    template_id INTEGER REFERENCES alert_templates(template_id),
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    alert_type VARCHAR(50) NOT NULL,
    subject VARCHAR(255),
    message TEXT,
    channels_used JSONB DEFAULT '[]',
    priority VARCHAR(20) DEFAULT 'normal',
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'sent', 'delivered', 'failed'
    scheduled_time TIMESTAMP,
    sent_time TIMESTAMP,
    delivery_time TIMESTAMP,
    variables_used JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Push notification subscriptions
CREATE TABLE IF NOT EXISTS push_subscriptions (
    subscription_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    endpoint TEXT NOT NULL,
    p256dh_key TEXT,
    auth_key TEXT,
    user_agent TEXT,
    device_type VARCHAR(50), -- 'mobile', 'desktop', 'tablet'
    browser VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(passenger_id, endpoint)
);

-- SMS delivery tracking
CREATE TABLE IF NOT EXISTS sms_deliveries (
    delivery_id SERIAL PRIMARY KEY,
    alert_id INTEGER REFERENCES alert_instances(alert_id),
    phone_number VARCHAR(20) NOT NULL,
    provider VARCHAR(50), -- 'twilio', 'aws_sns', 'messagebird'
    message_sid VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'sent', 'delivered', 'failed'
    error_message TEXT,
    cost DECIMAL(6,4),
    sent_time TIMESTAMP,
    delivered_time TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Email delivery tracking
CREATE TABLE IF NOT EXISTS email_deliveries (
    delivery_id SERIAL PRIMARY KEY,
    alert_id INTEGER REFERENCES alert_instances(alert_id),
    email_address VARCHAR(255) NOT NULL,
    provider VARCHAR(50), -- 'sendgrid', 'aws_ses', 'mailgun'
    message_id VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'sent', 'delivered', 'opened', 'clicked', 'failed'
    error_message TEXT,
    cost DECIMAL(6,4),
    sent_time TIMESTAMP,
    delivered_time TIMESTAMP,
    opened_time TIMESTAMP,
    clicked_time TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Travel reminders and itinerary
CREATE TABLE IF NOT EXISTS travel_itineraries (
    itinerary_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    reminder_type VARCHAR(50), -- 'checkin_opens', 'boarding_starts', 'gate_change', 'delay_update'
    scheduled_time TIMESTAMP NOT NULL,
    reminder_time TIMESTAMP NOT NULL,
    is_sent BOOLEAN DEFAULT FALSE,
    sent_time TIMESTAMP,
    location_context JSONB DEFAULT '{}', -- GPS coordinates, airport zone
    weather_context JSONB DEFAULT '{}', -- Weather conditions at reminder time
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Location-based alerts
CREATE TABLE IF NOT EXISTS location_alerts (
    location_alert_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    alert_type VARCHAR(50), -- 'arrival_at_airport', 'near_gate', 'security_line', 'baggage_claim'
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    radius_meters INTEGER DEFAULT 100,
    airport_code VARCHAR(10),
    zone VARCHAR(50), -- 'terminal_a', 'gate_12', 'security', 'baggage_claim'
    message_template TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    trigger_count INTEGER DEFAULT 0,
    last_triggered TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Alert delivery analytics
CREATE TABLE IF NOT EXISTS alert_analytics (
    analytics_id SERIAL PRIMARY KEY,
    date DATE,
    alert_type VARCHAR(50),
    total_sent INTEGER DEFAULT 0,
    total_delivered INTEGER DEFAULT 0,
    total_opened INTEGER DEFAULT 0,
    total_clicked INTEGER DEFAULT 0,
    total_failed INTEGER DEFAULT 0,
    avg_delivery_time INTEGER, -- in seconds
    channel_breakdown JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(date, alert_type)
);

-- Alert queue for batch processing
CREATE TABLE IF NOT EXISTS alert_queue (
    queue_id SERIAL PRIMARY KEY,
    alert_id INTEGER REFERENCES alert_instances(alert_id),
    channel VARCHAR(20) NOT NULL, -- 'push', 'sms', 'email'
    priority INTEGER DEFAULT 1, -- 1=low, 2=normal, 3=high, 4=critical
    status VARCHAR(20) DEFAULT 'queued', -- 'queued', 'processing', 'sent', 'failed'
    retry_count INTEGER DEFAULT 0,
    max_retries INTEGER DEFAULT 3,
    next_retry_time TIMESTAMP,
    error_message TEXT,
    queued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP
);

-- Smart notification rules
CREATE TABLE IF NOT EXISTS smart_notification_rules (
    rule_id SERIAL PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    trigger_event VARCHAR(50) NOT NULL, -- 'flight_delay', 'gate_change', 'weather_alert'
    conditions JSONB NOT NULL, -- Conditions that must be met
    actions JSONB NOT NULL, -- Actions to take when triggered
    priority VARCHAR(20) DEFAULT 'normal',
    is_active BOOLEAN DEFAULT TRUE,
    cooldown_minutes INTEGER DEFAULT 60, -- Minimum time between notifications
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notification suppression rules
CREATE TABLE IF NOT EXISTS notification_suppression (
    suppression_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    alert_type VARCHAR(50),
    suppress_until TIMESTAMP,
    reason VARCHAR(100), -- 'user_request', 'frequent_traveler', 'vip_status'
    created_by INTEGER, -- User who created the suppression
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_alert_templates_type ON alert_templates(template_type);
CREATE INDEX IF NOT EXISTS idx_notification_preferences_passenger ON notification_preferences(passenger_id);
CREATE INDEX IF NOT EXISTS idx_alert_instances_passenger ON alert_instances(passenger_id);
CREATE INDEX IF NOT EXISTS idx_alert_instances_status ON alert_instances(status);
CREATE INDEX IF NOT EXISTS idx_alert_instances_scheduled ON alert_instances(scheduled_time);
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_passenger ON push_subscriptions(passenger_id);
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_active ON push_subscriptions(is_active);
CREATE INDEX IF NOT EXISTS idx_sms_deliveries_alert ON sms_deliveries(alert_id);
CREATE INDEX IF NOT EXISTS idx_email_deliveries_alert ON email_deliveries(alert_id);
CREATE INDEX IF NOT EXISTS idx_travel_itineraries_passenger ON travel_itineraries(passenger_id);
CREATE INDEX IF NOT EXISTS idx_travel_itineraries_reminder ON travel_itineraries(reminder_time);
CREATE INDEX IF NOT EXISTS idx_location_alerts_passenger ON location_alerts(passenger_id);
CREATE INDEX IF NOT EXISTS idx_location_alerts_zone ON location_alerts(airport_code, zone);
CREATE INDEX IF NOT EXISTS idx_alert_analytics_date ON alert_analytics(date);
CREATE INDEX IF NOT EXISTS idx_alert_queue_status ON alert_queue(status);
CREATE INDEX IF NOT EXISTS idx_alert_queue_priority ON alert_queue(priority DESC);
CREATE INDEX IF NOT EXISTS idx_smart_rules_trigger ON smart_notification_rules(trigger_event);
CREATE INDEX IF NOT EXISTS idx_suppression_passenger ON notification_suppression(passenger_id);

-- Insert default alert templates
INSERT INTO alert_templates (template_name, template_type, subject, message_template, variables, channels, priority) VALUES
('Flight Delay Alert', 'delay', 'Flight {{flight_number}} Delayed', 'Your flight {{flight_number}} from {{origin}} to {{destination}} has been delayed by {{delay_minutes}} minutes. New departure time: {{new_departure_time}}.', '{"flight_number": "string", "origin": "string", "destination": "string", "delay_minutes": "number", "new_departure_time": "string"}', '["push", "sms", "email"]', 'high'),
('Gate Change Alert', 'gate_change', 'Gate Change for Flight {{flight_number}}', 'Your flight {{flight_number}} has been moved to Gate {{new_gate}}. Please proceed to the new gate immediately.', '{"flight_number": "string", "new_gate": "string"}', '["push", "sms"]', 'high'),
('Boarding Reminder', 'reminder', 'Boarding Starts Soon', 'Boarding for flight {{flight_number}} to {{destination}} begins in {{minutes_until_boarding}} minutes at Gate {{gate}}.', '{"flight_number": "string", "destination": "string", "minutes_until_boarding": "number", "gate": "string"}', '["push"]', 'normal'),
('Check-in Opens', 'reminder', 'Check-in Now Available', 'Online check-in is now available for your flight {{flight_number}} to {{destination}}. Check in before {{checkin_deadline}}.', '{"flight_number": "string", "destination": "string", "checkin_deadline": "string"}', '["push", "email"]', 'normal'),
('Baggage Claim Alert', 'flight_update', 'Baggage Available', 'Your baggage from flight {{flight_number}} is now available at Carousel {{carousel}}.', '{"flight_number": "string", "carousel": "string"}', '["push"]', 'normal'),
('Weather Alert', 'emergency', 'Weather Impact on Your Flight', 'Due to weather conditions, your flight {{flight_number}} may be affected. Please monitor updates.', '{"flight_number": "string"}', '["push", "sms"]', 'critical'),
('Security Reminder', 'reminder', 'Security Checkpoint Ahead', 'You are approaching the security checkpoint. Please have your boarding pass and ID ready.', '{}', '["push"]', 'normal')
ON CONFLICT DO NOTHING;

-- Insert default smart notification rules
INSERT INTO smart_notification_rules (rule_name, trigger_event, conditions, actions, priority, cooldown_minutes) VALUES
('Gate Change Proximity', 'gate_change', '{"passenger_near_gate": true, "time_until_boarding": {"lt": 30}}', '{"send_immediate_alert": true, "channels": ["push", "sms"]}', 'high', 5),
('Delay Escalation', 'flight_delay', '{"delay_minutes": {"gte": 60}, "previous_notification_sent": false}', '{"send_alert": true, "channels": ["push", "sms", "email"], "include_rebooking_options": true}', 'high', 30),
('Weather Impact', 'weather_alert', '{"severity": {"gte": "moderate"}, "flight_within_hours": 24}', '{"send_alert": true, "channels": ["push", "email"], "include_alternatives": true}', 'critical', 120),
('Frequent Traveler Quiet Hours', 'any', '{"passenger_tier": "gold", "current_time_in_quiet_hours": true}', '{"suppress_non_critical": true}', 'low', 1440)
ON CONFLICT DO NOTHING;

-- Function to send alert notification
CREATE OR REPLACE FUNCTION send_alert_notification(
    p_template_id INTEGER,
    p_passenger_id INTEGER,
    p_booking_id INTEGER DEFAULT NULL,
    p_flight_id INTEGER DEFAULT NULL,
    p_variables JSONB DEFAULT '{}',
    p_scheduled_time TIMESTAMP DEFAULT NOW()
) RETURNS INTEGER AS $$
DECLARE
    v_template alert_templates%ROWTYPE;
    v_alert_id INTEGER;
    v_subject TEXT;
    v_message TEXT;
BEGIN
    -- Get template
    SELECT * INTO v_template FROM alert_templates WHERE template_id = p_template_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Alert template not found: %', p_template_id;
    END IF;

    -- Substitute variables in subject and message
    v_subject := substitute_template_variables(v_template.subject, p_variables);
    v_message := substitute_template_variables(v_template.message_template, p_variables);

    -- Create alert instance
    INSERT INTO alert_instances (
        template_id, passenger_id, booking_id, flight_id,
        alert_type, subject, message, priority, scheduled_time, variables_used
    ) VALUES (
        p_template_id, p_passenger_id, p_booking_id, p_flight_id,
        v_template.template_type, v_subject, v_message, v_template.priority,
        p_scheduled_time, p_variables
    ) RETURNING alert_id INTO v_alert_id;

    -- Queue for delivery
    INSERT INTO alert_queue (alert_id, channel, priority)
    SELECT v_alert_id, channel, CASE v_template.priority
        WHEN 'low' THEN 1
        WHEN 'normal' THEN 2
        WHEN 'high' THEN 3
        WHEN 'critical' THEN 4
        ELSE 2
    END
    FROM jsonb_array_elements_text(v_template.channels::jsonb) AS channel;

    RETURN v_alert_id;
END;
$$ LANGUAGE plpgsql;

-- Function to substitute template variables
CREATE OR REPLACE FUNCTION substitute_template_variables(
    p_template TEXT,
    p_variables JSONB
) RETURNS TEXT AS $$
DECLARE
    v_result TEXT := p_template;
    v_key TEXT;
    v_value TEXT;
BEGIN
    FOR v_key, v_value IN SELECT * FROM jsonb_object_keys(p_variables) k CROSS JOIN jsonb_extract_path_text(p_variables, k) v
    LOOP
        v_result := REPLACE(v_result, '{{' || v_key || '}}', v_value);
    END LOOP;

    RETURN v_result;
END;
$$ LANGUAGE plpgsql;

-- Function to create travel reminders for a booking
CREATE OR REPLACE FUNCTION create_travel_reminders(p_booking_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    v_booking RECORD;
    v_reminder_count INTEGER := 0;
BEGIN
    -- Get booking details
    SELECT b.*, f.flight_number, f.departure_time, f.origin, f.destination, f.gate
    INTO v_booking
    FROM bookings b
    JOIN flights f ON b.flight_id = f.flight_id
    WHERE b.booking_id = p_booking_id;

    IF NOT FOUND THEN
        RETURN 0;
    END IF;

    -- Check-in opens reminder (24 hours before departure)
    INSERT INTO travel_itineraries (
        passenger_id, booking_id, flight_id, reminder_type,
        scheduled_time, reminder_time
    ) VALUES (
        v_booking.passenger_id, p_booking_id, v_booking.flight_id, 'checkin_opens',
        v_booking.departure_time, v_booking.departure_time - INTERVAL '24 hours'
    );
    v_reminder_count := v_reminder_count + 1;

    -- Boarding starts reminder (30 minutes before boarding)
    INSERT INTO travel_itineraries (
        passenger_id, booking_id, flight_id, reminder_type,
        scheduled_time, reminder_time
    ) VALUES (
        v_booking.passenger_id, p_booking_id, v_booking.flight_id, 'boarding_starts',
        v_booking.departure_time, v_booking.departure_time - INTERVAL '30 minutes'
    );
    v_reminder_count := v_reminder_count + 1;

    -- Final boarding call (15 minutes before departure)
    INSERT INTO travel_itineraries (
        passenger_id, booking_id, flight_id, reminder_type,
        scheduled_time, reminder_time
    ) VALUES (
        v_booking.passenger_id, p_booking_id, v_booking.flight_id, 'final_boarding',
        v_booking.departure_time, v_booking.departure_time - INTERVAL '15 minutes'
    );
    v_reminder_count := v_reminder_count + 1;

    RETURN v_reminder_count;
END;
$$ LANGUAGE plpgsql;

-- Function to check and send pending alerts
CREATE OR REPLACE FUNCTION process_pending_alerts()
RETURNS INTEGER AS $$
DECLARE
    v_alert RECORD;
    v_processed_count INTEGER := 0;
BEGIN
    FOR v_alert IN
        SELECT * FROM alert_instances
        WHERE status = 'pending'
        AND (scheduled_time IS NULL OR scheduled_time <= NOW())
        ORDER BY priority DESC, created_at ASC
        LIMIT 50
    LOOP
        -- Mark as processing
        UPDATE alert_instances SET status = 'processing' WHERE alert_id = v_alert.alert_id;

        -- Here you would integrate with actual notification services
        -- For now, we'll just mark as sent
        UPDATE alert_instances
        SET status = 'sent', sent_time = NOW()
        WHERE alert_id = v_alert.alert_id;

        UPDATE alert_queue
        SET status = 'sent', processed_at = NOW()
        WHERE alert_id = v_alert.alert_id;

        v_processed_count := v_processed_count + 1;
    END LOOP;

    RETURN v_processed_count;
END;
$$ LANGUAGE plpgsql;

-- Function to get passenger notification preferences
CREATE OR REPLACE FUNCTION get_passenger_notification_settings(p_passenger_id INTEGER)
RETURNS JSON AS $$
DECLARE
    v_preferences JSON;
BEGIN
    SELECT json_build_object(
        'passenger_id', p_passenger_id,
        'preferences', COALESCE(
            json_agg(
                json_build_object(
                    'alert_type', alert_type,
                    'channels', channels,
                    'quiet_hours_start', quiet_hours_start,
                    'quiet_hours_end', quiet_hours_end,
                    'language', language
                )
            ) FILTER (WHERE alert_type IS NOT NULL),
            '[]'::json
        ),
        'global_settings', json_build_object(
            'timezone', COALESCE(MIN(timezone), 'UTC'),
            'is_active', BOOL_AND(is_active)
        )
    )
    INTO v_preferences
    FROM notification_preferences
    WHERE passenger_id = p_passenger_id;

    RETURN v_preferences;
END;
$$ LANGUAGE plpgsql;

-- Trigger to automatically create travel reminders when booking is confirmed
CREATE OR REPLACE FUNCTION auto_create_travel_reminders()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.booking_status = 'confirmed' AND (OLD.booking_status IS NULL OR OLD.booking_status != 'confirmed') THEN
        PERFORM create_travel_reminders(NEW.booking_id);
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger for automatic reminder creation
CREATE TRIGGER auto_create_travel_reminders_trigger
    AFTER UPDATE ON bookings
    FOR EACH ROW
    EXECUTE FUNCTION auto_create_travel_reminders();
