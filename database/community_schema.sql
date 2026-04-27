-- Community Features Schema for Airport Operations Simulator
-- Manages leaderboards, social interactions, and community engagement

-- User follows table for social networking
CREATE TABLE IF NOT EXISTS user_follows (
    id SERIAL PRIMARY KEY,
    follower_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    followed_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(follower_id, followed_id),
    CHECK (follower_id != followed_id)
);

-- Social shares table for tracking achievement shares
CREATE TABLE IF NOT EXISTS social_shares (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    achievement_id VARCHAR(100) NOT NULL,
    platform VARCHAR(50) NOT NULL, -- twitter, facebook, linkedin, etc.
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    share_url VARCHAR(500),
    click_count INTEGER DEFAULT 0
);

-- User activity log for community feed
CREATE TABLE IF NOT EXISTS user_activity_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    activity_type VARCHAR(50) NOT NULL, -- achievement_unlocked, scenario_completed, level_up, etc.
    activity_data JSONB DEFAULT '{}',
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Community challenges table
CREATE TABLE IF NOT EXISTS community_challenges (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    challenge_type VARCHAR(50) NOT NULL, -- daily, weekly, monthly, special
    requirements JSONB DEFAULT '{}', -- Challenge requirements
    rewards JSONB DEFAULT '{}', -- Points, badges, etc.
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    participant_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Challenge participants table
CREATE TABLE IF NOT EXISTS challenge_participants (
    id SERIAL PRIMARY KEY,
    challenge_id INTEGER REFERENCES community_challenges(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    progress JSONB DEFAULT '{}',
    completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP,
    reward_claimed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(challenge_id, user_id)
);

-- Leaderboard snapshots for historical tracking
CREATE TABLE IF NOT EXISTS leaderboard_snapshots (
    id SERIAL PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    timeframe VARCHAR(20) DEFAULT 'all', -- daily, weekly, monthly, all
    leaderboard_type VARCHAR(50) DEFAULT 'global', -- global, role, scenario
    reference_id VARCHAR(100), -- role_id or scenario_id for specific leaderboards
    data JSONB DEFAULT '[]', -- Top 100 leaderboard entries
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(snapshot_date, timeframe, leaderboard_type, reference_id)
);

-- Achievement showcase table for featured achievements
CREATE TABLE IF NOT EXISTS achievement_showcase (
    id SERIAL PRIMARY KEY,
    achievement_type VARCHAR(100) NOT NULL,
    showcase_title VARCHAR(255),
    showcase_description TEXT,
    featured_image_url VARCHAR(500),
    is_featured BOOLEAN DEFAULT FALSE,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(achievement_type)
);

-- Community events table
CREATE TABLE IF NOT EXISTS community_events (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type VARCHAR(50) NOT NULL, -- tournament, challenge, celebration
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    rewards JSONB DEFAULT '{}',
    participant_limit INTEGER,
    current_participants INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Event participants table
CREATE TABLE IF NOT EXISTS event_participants (
    id SERIAL PRIMARY KEY,
    event_id INTEGER REFERENCES community_events(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score INTEGER DEFAULT 0,
    rank INTEGER,
    reward_claimed BOOLEAN DEFAULT FALSE,
    UNIQUE(event_id, user_id)
);

-- User reputation system
CREATE TABLE IF NOT EXISTS user_reputation (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    reputation_score INTEGER DEFAULT 0,
    level INTEGER DEFAULT 1,
    title VARCHAR(100) DEFAULT 'Rookie',
    badges JSONB DEFAULT '[]', -- Array of earned badge IDs
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id)
);

-- Reputation badges table
CREATE TABLE IF NOT EXISTS reputation_badges (
    id SERIAL PRIMARY KEY,
    badge_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon_url VARCHAR(500),
    requirement_type VARCHAR(50) NOT NULL, -- score, achievements, scenarios, etc.
    requirement_value INTEGER NOT NULL,
    rarity VARCHAR(20) DEFAULT 'common', -- common, rare, epic, legendary
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default reputation badges
INSERT INTO reputation_badges (badge_key, name, description, requirement_type, requirement_value, rarity) VALUES
('first_steps', 'First Steps', 'Complete your first scenario', 'scenarios', 1, 'common'),
('scenario_explorer', 'Scenario Explorer', 'Complete 10 different scenarios', 'scenarios', 10, 'common'),
('achievement_hunter', 'Achievement Hunter', 'Unlock 5 achievements', 'achievements', 5, 'common'),
('dedicated_player', 'Dedicated Player', 'Play for 7 consecutive days', 'streak', 7, 'rare'),
('high_scorer', 'High Scorer', 'Achieve a score above 5000 points', 'score', 5000, 'rare'),
('speed_demon', 'Speed Demon', 'Complete a scenario in under 5 minutes', 'speed', 300, 'epic'),
('perfectionist', 'Perfectionist', 'Achieve 100% score on 3 scenarios', 'perfection', 3, 'epic'),
('social_butterfly', 'Social Butterfly', 'Have 10 followers', 'followers', 10, 'rare'),
('community_leader', 'Community Leader', 'Reach top 10 on global leaderboard', 'rank', 10, 'legendary'),
('veteran_player', 'Veteran Player', 'Play for 30 days', 'days_active', 30, 'epic'),
('master_airport_manager', 'Master Airport Manager', 'Complete all expert scenarios', 'expert_completion', 10, 'legendary');

-- User preferences for community features
CREATE TABLE IF NOT EXISTS user_community_preferences (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    show_in_leaderboards BOOLEAN DEFAULT TRUE,
    allow_following BOOLEAN DEFAULT TRUE,
    public_profile BOOLEAN DEFAULT TRUE,
    share_achievements BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id)
);

-- Community discussions/comments table
CREATE TABLE IF NOT EXISTS community_discussions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    discussion_type VARCHAR(50) NOT NULL, -- achievement, scenario, general
    reference_id VARCHAR(100), -- achievement_type or scenario_id
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_locked BOOLEAN DEFAULT FALSE,
    reply_count INTEGER DEFAULT 0,
    last_reply_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Discussion replies table
CREATE TABLE IF NOT EXISTS discussion_replies (
    id SERIAL PRIMARY KEY,
    discussion_id INTEGER REFERENCES community_discussions(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    parent_reply_id INTEGER REFERENCES discussion_replies(id) ON DELETE CASCADE,
    is_solution BOOLEAN DEFAULT FALSE,
    likes_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reply likes table
CREATE TABLE IF NOT EXISTS reply_likes (
    id SERIAL PRIMARY KEY,
    reply_id INTEGER REFERENCES discussion_replies(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(reply_id, user_id)
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_user_follows_follower ON user_follows(follower_id);
CREATE INDEX IF NOT EXISTS idx_user_follows_followed ON user_follows(followed_id);
CREATE INDEX IF NOT EXISTS idx_social_shares_user ON social_shares(user_id);
CREATE INDEX IF NOT EXISTS idx_social_shares_achievement ON social_shares(achievement_id);
CREATE INDEX IF NOT EXISTS idx_user_activity_user ON user_activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_user_activity_type ON user_activity_log(activity_type);
CREATE INDEX IF NOT EXISTS idx_challenge_participants_challenge ON challenge_participants(challenge_id);
CREATE INDEX IF NOT EXISTS idx_challenge_participants_user ON challenge_participants(user_id);
CREATE INDEX IF NOT EXISTS idx_leaderboard_snapshots_date ON leaderboard_snapshots(snapshot_date);
CREATE INDEX IF NOT EXISTS idx_event_participants_event ON event_participants(event_id);
CREATE INDEX IF NOT EXISTS idx_event_participants_user ON event_participants(user_id);
CREATE INDEX IF NOT EXISTS idx_user_reputation_user ON user_reputation(user_id);
CREATE INDEX IF NOT EXISTS idx_community_discussions_type ON community_discussions(discussion_type);
CREATE INDEX IF NOT EXISTS idx_discussion_replies_discussion ON discussion_replies(discussion_id);

-- Function to update user reputation
CREATE OR REPLACE FUNCTION update_user_reputation(user_id_param INTEGER)
RETURNS VOID AS $$
DECLARE
    total_score INTEGER := 0;
    scenarios_completed INTEGER := 0;
    achievements_count INTEGER := 0;
    followers_count INTEGER := 0;
    new_reputation INTEGER := 0;
    new_level INTEGER := 1;
    new_title VARCHAR(100) := 'Rookie';
BEGIN
    -- Get user stats
    SELECT
        COALESCE(dup.total_score, 0),
        COALESCE(dup.scenarios_completed, 0),
        COALESCE(da.achievement_count, 0),
        COALESCE(uf.followers_count, 0)
    INTO total_score, scenarios_completed, achievements_count, followers_count
    FROM demo_user_progress dup
    LEFT JOIN (SELECT user_id, COUNT(*) as achievement_count FROM demo_achievements GROUP BY user_id) da ON dup.user_id = da.user_id
    LEFT JOIN (SELECT followed_id, COUNT(*) as followers_count FROM user_follows GROUP BY followed_id) uf ON dup.user_id = uf.followed_id
    WHERE dup.user_id = user_id_param;

    -- Calculate reputation score
    new_reputation := (total_score / 100) + (scenarios_completed * 10) + (achievements_count * 25) + (followers_count * 5);

    -- Calculate level (every 1000 reputation points = 1 level)
    new_level := GREATEST(1, FLOOR(new_reputation / 1000) + 1);

    -- Determine title based on level and achievements
    SELECT CASE
        WHEN new_level >= 10 AND achievements_count >= 20 THEN 'Airport Operations Legend'
        WHEN new_level >= 8 AND scenarios_completed >= 30 THEN 'Senior Airport Manager'
        WHEN new_level >= 6 AND achievements_count >= 10 THEN 'Experienced Controller'
        WHEN new_level >= 4 AND scenarios_completed >= 15 THEN 'Skilled Dispatcher'
        WHEN new_level >= 2 THEN 'Qualified Operator'
        ELSE 'Rookie Controller'
    END INTO new_title;

    -- Insert or update reputation
    INSERT INTO user_reputation (user_id, reputation_score, level, title, last_activity)
    VALUES (user_id_param, new_reputation, new_level, new_title, NOW())
    ON CONFLICT (user_id) DO UPDATE SET
        reputation_score = new_reputation,
        level = new_level,
        title = new_title,
        last_activity = NOW(),
        updated_at = NOW();

    -- Check for new badges
    PERFORM check_and_award_badges(user_id_param, total_score, scenarios_completed, achievements_count, followers_count);
END;
$$ LANGUAGE plpgsql;

-- Function to check and award reputation badges
CREATE OR REPLACE FUNCTION check_and_award_badges(
    user_id_param INTEGER,
    total_score INTEGER,
    scenarios_completed INTEGER,
    achievements_count INTEGER,
    followers_count INTEGER
)
RETURNS VOID AS $$
DECLARE
    badge_record RECORD;
    user_badges JSONB;
BEGIN
    -- Get user's current badges
    SELECT badges INTO user_badges
    FROM user_reputation
    WHERE user_id = user_id_param;

    IF user_badges IS NULL THEN
        user_badges := '[]';
    END IF;

    -- Check each badge requirement
    FOR badge_record IN SELECT * FROM reputation_badges WHERE is_active = TRUE LOOP
        -- Skip if user already has this badge
        IF user_badges ? badge_record.badge_key THEN
            CONTINUE;
        END IF;

        -- Check if requirement is met
        IF (badge_record.requirement_type = 'score' AND total_score >= badge_record.requirement_value) OR
           (badge_record.requirement_type = 'scenarios' AND scenarios_completed >= badge_record.requirement_value) OR
           (badge_record.requirement_type = 'achievements' AND achievements_count >= badge_record.requirement_value) OR
           (badge_record.requirement_type = 'followers' AND followers_count >= badge_record.requirement_value) THEN

            -- Award badge
            user_badges := user_badges || jsonb_build_array(badge_record.badge_key);

            -- Log badge achievement
            INSERT INTO user_activity_log (user_id, activity_type, activity_data)
            VALUES (user_id_param, 'badge_earned', jsonb_build_object(
                'badge_key', badge_record.badge_key,
                'badge_name', badge_record.name,
                'rarity', badge_record.rarity
            ));
        END IF;
    END LOOP;

    -- Update user's badges
    UPDATE user_reputation
    SET badges = user_badges, updated_at = NOW()
    WHERE user_id = user_id_param;
END;
$$ LANGUAGE plpgsql;

-- Function to create leaderboard snapshot
CREATE OR REPLACE FUNCTION create_leaderboard_snapshot(timeframe_param VARCHAR(20) DEFAULT 'all')
RETURNS VOID AS $$
DECLARE
    snapshot_date DATE := CURRENT_DATE;
BEGIN
    -- Global leaderboard
    INSERT INTO leaderboard_snapshots (snapshot_date, timeframe, leaderboard_type, data)
    SELECT
        snapshot_date,
        timeframe_param,
        'global',
        jsonb_agg(
            jsonb_build_object(
                'rank', ROW_NUMBER() OVER (ORDER BY COALESCE(dup.total_score, 0) DESC),
                'user_id', u.id,
                'username', u.username,
                'total_score', COALESCE(dup.total_score, 0),
                'achievements_count', COALESCE(da.achievement_count, 0)
            )
        )
    FROM users u
    LEFT JOIN demo_user_progress dup ON u.id = dup.user_id
    LEFT JOIN (SELECT user_id, COUNT(*) as achievement_count FROM demo_achievements GROUP BY user_id) da ON u.id = da.user_id
    WHERE u.role_id IN (SELECT id FROM roles WHERE name != 'super_admin')
    ORDER BY COALESCE(dup.total_score, 0) DESC
    LIMIT 100
    ON CONFLICT (snapshot_date, timeframe, leaderboard_type, reference_id)
    DO UPDATE SET data = EXCLUDED.data, created_at = NOW();

    -- Role-specific leaderboards
    FOR role_record IN SELECT id, name FROM roles WHERE name NOT IN ('super_admin', 'admin') LOOP
        INSERT INTO leaderboard_snapshots (snapshot_date, timeframe, leaderboard_type, reference_id, data)
        SELECT
            snapshot_date,
            timeframe_param,
            'role',
            role_record.id::TEXT,
            jsonb_agg(
                jsonb_build_object(
                    'rank', ROW_NUMBER() OVER (ORDER BY COALESCE(dup.total_score, 0) DESC),
                    'user_id', u.id,
                    'username', u.username,
                    'total_score', COALESCE(dup.total_score, 0)
                )
            )
        FROM users u
        LEFT JOIN demo_user_progress dup ON u.id = dup.user_id
        WHERE u.role_id = role_record.id
        ORDER BY COALESCE(dup.total_score, 0) DESC
        LIMIT 50
        ON CONFLICT (snapshot_date, timeframe, leaderboard_type, reference_id)
        DO UPDATE SET data = EXCLUDED.data, created_at = NOW();
    END LOOP;
END;
$$ LANGUAGE plpgsql;

-- Trigger to update reputation when user stats change
CREATE OR REPLACE FUNCTION trigger_reputation_update()
RETURNS TRIGGER AS $$
BEGIN
    -- Update reputation for the affected user
    PERFORM update_user_reputation(NEW.user_id);

    -- Log activity
    INSERT INTO user_activity_log (user_id, activity_type, activity_data)
    VALUES (NEW.user_id, TG_ARGV[0], jsonb_build_object('table', TG_TABLE_NAME));

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create triggers for reputation updates
DROP TRIGGER IF EXISTS trigger_reputation_on_achievement ON demo_achievements;
CREATE TRIGGER trigger_reputation_on_achievement
    AFTER INSERT ON demo_achievements
    FOR EACH ROW
    EXECUTE FUNCTION trigger_reputation_update('achievement_unlocked');

DROP TRIGGER IF EXISTS trigger_reputation_on_scenario ON demo_scenario_attempts;
CREATE TRIGGER trigger_reputation_on_scenario
    AFTER INSERT ON demo_scenario_attempts
    FOR EACH ROW
    WHEN (NEW.success = TRUE)
    EXECUTE FUNCTION trigger_reputation_update('scenario_completed');

-- Function to clean up old data
CREATE OR REPLACE FUNCTION cleanup_old_community_data()
RETURNS VOID AS $$
BEGIN
    -- Delete old leaderboard snapshots (keep last 90 days)
    DELETE FROM leaderboard_snapshots
    WHERE snapshot_date < CURRENT_DATE - INTERVAL '90 days';

    -- Delete old activity logs (keep last 30 days)
    DELETE FROM user_activity_log
    WHERE created_at < CURRENT_TIMESTAMP - INTERVAL '30 days';

    -- Delete old social shares (keep last 60 days)
    DELETE FROM social_shares
    WHERE shared_at < CURRENT_TIMESTAMP - INTERVAL '60 days';
END;
$$ LANGUAGE plpgsql;
