-- Demo Mode Database Schema
-- Tables for Airport Operations Simulator gamification and demo features

-- Demo achievements table
CREATE TABLE IF NOT EXISTS demo_achievements (
    achievement_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    achievement_type VARCHAR(50) NOT NULL, -- 'scenario_complete', 'role_mastered', 'efficiency', etc.
    achievement_name VARCHAR(100) NOT NULL,
    description TEXT,
    points_earned INTEGER DEFAULT 0,
    badge_icon VARCHAR(100), -- URL or icon name
    rarity VARCHAR(20) DEFAULT 'common', -- 'common', 'rare', 'epic', 'legendary'
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSONB DEFAULT '{}', -- Additional achievement data
    UNIQUE(user_id, achievement_type, achievement_name)
);

-- Demo scenario attempts table
CREATE TABLE IF NOT EXISTS demo_scenario_attempts (
    attempt_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    scenario_id VARCHAR(50) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    progress INTEGER DEFAULT 0, -- 0-100
    score INTEGER DEFAULT 0,
    time_taken INTEGER, -- in seconds
    success BOOLEAN DEFAULT FALSE,
    feedback TEXT, -- AI-generated feedback
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, scenario_id, started_at)
);

-- Demo user progress table
CREATE TABLE IF NOT EXISTS demo_user_progress (
    progress_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    role_name VARCHAR(50) NOT NULL,
    scenarios_completed INTEGER DEFAULT 0,
    total_score INTEGER DEFAULT 0,
    best_time INTEGER, -- best completion time in seconds
    skill_level VARCHAR(20) DEFAULT 'beginner', -- 'beginner', 'intermediate', 'advanced', 'expert'
    experience_points INTEGER DEFAULT 0,
    last_played TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    preferences JSONB DEFAULT '{}', -- User demo preferences
    UNIQUE(user_id, role_name)
);

-- Demo leaderboard snapshots
CREATE TABLE IF NOT EXISTS demo_leaderboard_snapshots (
    snapshot_id SERIAL PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    rank INTEGER NOT NULL,
    total_score INTEGER DEFAULT 0,
    achievements_count INTEGER DEFAULT 0,
    scenarios_completed INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(snapshot_date, user_id)
);

-- Demo scenario definitions
CREATE TABLE IF NOT EXISTS demo_scenarios (
    scenario_id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general', -- 'emergency', 'peak_hours', 'weather', 'security', etc.
    difficulty VARCHAR(20) DEFAULT 'intermediate', -- 'beginner', 'intermediate', 'advanced', 'expert'
    estimated_duration INTEGER, -- in minutes
    max_score INTEGER DEFAULT 1000,
    objectives JSONB DEFAULT '[]', -- Array of objective descriptions
    required_roles JSONB DEFAULT '[]', -- Array of role names that can play this scenario
    prerequisites JSONB DEFAULT '[]', -- Required achievements or scenarios
    config JSONB DEFAULT '{}', -- Scenario-specific configuration
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Demo user sessions
CREATE TABLE IF NOT EXISTS demo_sessions (
    session_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    role_id INTEGER REFERENCES roles(id),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP,
    total_score INTEGER DEFAULT 0,
    scenarios_played INTEGER DEFAULT 0,
    achievements_unlocked INTEGER DEFAULT 0,
    session_duration INTEGER, -- in seconds
    feedback_rating INTEGER, -- 1-5 stars
    feedback_text TEXT,
    metadata JSONB DEFAULT '{}'
);

-- Demo tutorial progress
CREATE TABLE IF NOT EXISTS demo_tutorial_progress (
    progress_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    tutorial_id VARCHAR(50) NOT NULL,
    step_completed INTEGER DEFAULT 0,
    total_steps INTEGER DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    UNIQUE(user_id, tutorial_id)
);

-- Demo feature flags (for gradual rollouts)
CREATE TABLE IF NOT EXISTS demo_feature_flags (
    flag_id SERIAL PRIMARY KEY,
    feature_name VARCHAR(100) NOT NULL UNIQUE,
    is_enabled BOOLEAN DEFAULT FALSE,
    rollout_percentage INTEGER DEFAULT 0, -- 0-100
    target_roles JSONB DEFAULT '[]', -- Which roles can access this feature
    conditions JSONB DEFAULT '{}', -- Additional conditions
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Demo analytics and metrics
CREATE TABLE IF NOT EXISTS demo_analytics (
    analytics_id SERIAL PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL, -- 'scenario_start', 'achievement_unlock', 'session_end', etc.
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    session_id INTEGER REFERENCES demo_sessions(session_id) ON DELETE CASCADE,
    event_data JSONB DEFAULT '{}',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_demo_achievements_user ON demo_achievements(user_id);
CREATE INDEX IF NOT EXISTS idx_demo_achievements_type ON demo_achievements(achievement_type);
CREATE INDEX IF NOT EXISTS idx_demo_scenario_attempts_user ON demo_scenario_attempts(user_id);
CREATE INDEX IF NOT EXISTS idx_demo_scenario_attempts_scenario ON demo_scenario_attempts(scenario_id);
CREATE INDEX IF NOT EXISTS idx_demo_user_progress_user ON demo_user_progress(user_id);
CREATE INDEX IF NOT EXISTS idx_demo_leaderboard_date ON demo_leaderboard_snapshots(snapshot_date);
CREATE INDEX IF NOT EXISTS idx_demo_sessions_user ON demo_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_demo_analytics_event ON demo_analytics(event_type);
CREATE INDEX IF NOT EXISTS idx_demo_analytics_timestamp ON demo_analytics(timestamp);

-- Insert sample scenarios
INSERT INTO demo_scenarios (scenario_id, title, description, category, difficulty, estimated_duration, max_score, objectives, required_roles) VALUES
('morning_rush', 'Morning Rush Hour', 'Handle peak morning traffic with multiple arrivals and departures', 'peak_hours', 'intermediate', 15, 1000,
 '["Process 20 flights on time", "Handle 2 gate changes efficiently", "Maintain passenger satisfaction above 85%"]',
 '["controller", "dispatcher", "operator"]'),

('weather_diversion', 'Weather Emergency Diversion', 'Manage flight diversions due to sudden weather changes', 'emergency', 'advanced', 20, 1500,
 '["Divert 5 flights safely", "Rebook 200+ passengers", "Communicate effectively with airlines"]',
 '["controller", "emergency_coordinator", "dispatcher"]'),

('security_threat', 'Security Threat Response', 'Respond to a security incident and coordinate emergency procedures', 'security', 'advanced', 18, 1200,
 '["Secure affected areas within 5 minutes", "Evacuate 500+ passengers safely", "Coordinate with authorities"]',
 '["security_officer", "emergency_coordinator", "controller"]'),

('cargo_crisis', 'Perishable Cargo Emergency', 'Handle temperature-sensitive cargo that needs immediate attention', 'cargo', 'intermediate', 12, 800,
 '["Identify affected cargo containers", "Coordinate alternative routing", "Minimize financial losses"]',
 '["cargo_manager", "infrastructure_manager", "dispatcher"]'),

('vip_handling', 'VIP Passenger Arrival', 'Manage high-profile passenger arrival with special requirements', 'passenger', 'intermediate', 10, 600,
 '["Coordinate special services team", "Ensure passenger privacy", "Manage media presence"]',
 '["passenger_services_rep", "security_officer", "commercial_manager"]'),

('infrastructure_failure', 'Building Systems Failure', 'Respond to critical infrastructure failure affecting operations', 'infrastructure', 'advanced', 16, 1100,
 '["Identify system failure cause", "Implement backup systems", "Minimize operational disruption"]',
 '["infrastructure_manager", "emergency_coordinator", "dispatcher"]'),

('drone_incident', 'UAV Airspace Violation', 'Handle unauthorized drone activity in controlled airspace', 'drones', 'intermediate', 14, 900,
 '["Detect drone intrusion", "Coordinate interception", "Ensure flight safety"]',
 '["drone_operator", "controller", "security_officer"]'),

('customs_rush', 'International Flight Wave', 'Process high volume of international arrivals efficiently', 'customs', 'beginner', 12, 700,
 '["Process 150+ passengers", "Identify high-risk items", "Maintain processing speed"]',
 '["customs_officer", "security_officer", "passenger_services_rep"]'),

('sustainability_crisis', 'Environmental Incident', 'Respond to environmental emergency affecting airport operations', 'sustainability', 'intermediate', 10, 650,
 '["Assess environmental impact", "Implement containment procedures", "Coordinate cleanup efforts"]',
 '["sustainability_officer", "emergency_coordinator", "infrastructure_manager"]'),

('ai_system_failure', 'AI System Malfunction', 'Handle failure of automated systems requiring manual intervention', 'ai', 'advanced', 18, 1300,
 '["Identify system failure", "Switch to manual operations", "Restore automated systems"]',
 '["ai_analyst", "infrastructure_manager", "dispatcher"]')
ON CONFLICT (scenario_id) DO NOTHING;

-- Insert sample achievements
INSERT INTO demo_achievements (user_id, achievement_type, achievement_name, description, points_earned, rarity) VALUES
(1, 'first_scenario', 'First Flight', 'Complete your first airport scenario', 100, 'common'),
(1, 'speed_demon', 'Speed Demon', 'Complete a scenario in under 5 minutes', 250, 'rare'),
(1, 'perfectionist', 'Perfectionist', 'Achieve 100% score on any scenario', 500, 'epic'),
(1, 'crisis_manager', 'Crisis Manager', 'Successfully handle 5 emergency scenarios', 750, 'legendary'),
(1, 'role_master', 'Air Traffic Controller', 'Master the controller role with 10+ scenarios', 1000, 'legendary')
ON CONFLICT (user_id, achievement_type, achievement_name) DO NOTHING;

-- Function to update user progress
CREATE OR REPLACE FUNCTION update_demo_progress()
RETURNS TRIGGER AS $$
BEGIN
    -- Update user progress when achievements are unlocked
    IF TG_OP = 'INSERT' AND TG_TABLE_NAME = 'demo_achievements' THEN
        INSERT INTO demo_user_progress (user_id, role_name, scenarios_completed, total_score, experience_points, last_played)
        VALUES (NEW.user_id, 'demo_player', 1, NEW.points_earned, NEW.points_earned, NOW())
        ON CONFLICT (user_id, role_name) DO UPDATE SET
            total_score = demo_user_progress.total_score + NEW.points_earned,
            experience_points = demo_user_progress.experience_points + NEW.points_earned,
            last_played = NOW();

    -- Update progress when scenarios are completed
    ELSIF TG_OP = 'UPDATE' AND TG_TABLE_NAME = 'demo_scenario_attempts' AND NEW.completed_at IS NOT NULL THEN
        UPDATE demo_user_progress
        SET scenarios_completed = scenarios_completed + 1,
            total_score = total_score + NEW.score,
            experience_points = experience_points + NEW.score,
            last_played = NOW()
        WHERE user_id = NEW.user_id;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Triggers for automatic progress updates
CREATE TRIGGER update_progress_on_achievement
    AFTER INSERT ON demo_achievements
    FOR EACH ROW
    EXECUTE FUNCTION update_demo_progress();

CREATE TRIGGER update_progress_on_scenario_complete
    AFTER UPDATE ON demo_scenario_attempts
    FOR EACH ROW
    WHEN (OLD.completed_at IS NULL AND NEW.completed_at IS NOT NULL)
    EXECUTE FUNCTION update_demo_progress();

-- Function to calculate skill level based on experience
CREATE OR REPLACE FUNCTION calculate_skill_level(exp_points INTEGER)
RETURNS VARCHAR(20) AS $$
BEGIN
    RETURN CASE
        WHEN exp_points >= 10000 THEN 'expert'
        WHEN exp_points >= 5000 THEN 'advanced'
        WHEN exp_points >= 1000 THEN 'intermediate'
        ELSE 'beginner'
    END;
END;
$$ LANGUAGE plpgsql;

-- Function to generate daily leaderboard snapshots
CREATE OR REPLACE FUNCTION generate_leaderboard_snapshot()
RETURNS VOID AS $$
BEGIN
    INSERT INTO demo_leaderboard_snapshots (snapshot_date, user_id, rank, total_score, achievements_count, scenarios_completed)
    SELECT
        CURRENT_DATE,
        dup.user_id,
        ROW_NUMBER() OVER (ORDER BY dup.total_score DESC, dup.scenarios_completed DESC),
        dup.total_score,
        COALESCE(da.achievement_count, 0),
        dup.scenarios_completed
    FROM demo_user_progress dup
    LEFT JOIN (
        SELECT user_id, COUNT(*) as achievement_count
        FROM demo_achievements
        GROUP BY user_id
    ) da ON dup.user_id = da.user_id
    ORDER BY dup.total_score DESC, dup.scenarios_completed DESC;
END;
$$ LANGUAGE plpgsql;
