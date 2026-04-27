-- Subscription System Schema for Airport Operations Simulator
-- Manages user subscriptions, payments, and premium features

-- Subscription plans table
CREATE TABLE IF NOT EXISTS subscription_plans (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price INTEGER NOT NULL, -- Price in cents (e.g., 999 = $9.99)
    currency VARCHAR(3) DEFAULT 'usd',
    stripe_price_id VARCHAR(100),
    features JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    plan_id VARCHAR(50) REFERENCES subscription_plans(id),
    stripe_subscription_id VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending', -- pending, active, canceled, past_due, incomplete
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    trial_start TIMESTAMP,
    trial_end TIMESTAMP,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, stripe_subscription_id)
);

-- Subscription usage tracking
CREATE TABLE IF NOT EXISTS subscription_usage (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    subscription_id INTEGER REFERENCES subscriptions(id) ON DELETE CASCADE,
    feature_type VARCHAR(50) NOT NULL, -- scenarios, roles, storage, etc.
    usage_count INTEGER DEFAULT 0,
    usage_limit INTEGER DEFAULT -1, -- -1 = unlimited
    period_start TIMESTAMP,
    period_end TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, feature_type, period_start)
);

-- Payment history
CREATE TABLE IF NOT EXISTS payment_history (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    subscription_id INTEGER REFERENCES subscriptions(id) ON DELETE CASCADE,
    stripe_payment_intent_id VARCHAR(100),
    amount INTEGER NOT NULL, -- Amount in cents
    currency VARCHAR(3) DEFAULT 'usd',
    status VARCHAR(20) DEFAULT 'pending', -- pending, succeeded, failed, canceled
    payment_method VARCHAR(50),
    invoice_url VARCHAR(255),
    failure_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Feature access control
CREATE TABLE IF NOT EXISTS feature_access (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    feature_name VARCHAR(100) NOT NULL,
    access_level VARCHAR(20) DEFAULT 'none', -- none, read, write, admin
    granted_by INTEGER REFERENCES users(id), -- Who granted access
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, feature_name)
);

-- Premium content access
CREATE TABLE IF NOT EXISTS premium_content_access (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    content_type VARCHAR(50) NOT NULL, -- scenario, achievement, feature
    content_id VARCHAR(100) NOT NULL,
    access_granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    access_expires_at TIMESTAMP,
    granted_by_plan VARCHAR(50) REFERENCES subscription_plans(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, content_type, content_id)
);

-- Insert default subscription plans
INSERT INTO subscription_plans (id, name, description, price, currency, stripe_price_id, features) VALUES
('free', 'Free', 'Basic access to airport operations simulator', 0, 'usd', NULL,
 '{
   "basic_scenarios": 10,
   "roles_access": 3,
   "achievements": true,
   "leaderboards": true,
   "support": "community",
   "analytics": false,
   "custom_scenarios": false,
   "scenario_editor": false,
   "team_features": false,
   "api_access": false
 }'),

('premium', 'Premium', 'Enhanced access with advanced scenarios and analytics', 999, 'usd', 'price_premium_monthly',
 '{
   "basic_scenarios": -1,
   "advanced_scenarios": 25,
   "roles_access": 10,
   "achievements": true,
   "leaderboards": true,
   "analytics": true,
   "custom_scenarios": false,
   "scenario_editor": false,
   "team_features": false,
   "api_access": false,
   "support": "email"
 }'),

('pro', 'Pro', 'Professional access with expert scenarios and customization', 2999, 'usd', 'price_pro_monthly',
 '{
   "basic_scenarios": -1,
   "advanced_scenarios": -1,
   "expert_scenarios": 10,
   "roles_access": 15,
   "achievements": true,
   "leaderboards": true,
   "analytics": true,
   "custom_scenarios": true,
   "scenario_editor": true,
   "team_features": false,
   "api_access": false,
   "support": "priority"
 }'),

('institutional', 'Institutional', 'Enterprise access for organizations and training programs', 7999, 'usd', 'price_institutional_monthly',
 '{
   "basic_scenarios": -1,
   "advanced_scenarios": -1,
   "expert_scenarios": -1,
   "roles_access": 15,
   "achievements": true,
   "leaderboards": true,
   "analytics": true,
   "custom_scenarios": true,
   "scenario_editor": true,
   "team_features": true,
   "multi_user": 50,
   "api_access": true,
   "white_label": false,
   "support": "dedicated"
 }')

ON CONFLICT (id) DO NOTHING;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_subscriptions_user_id ON subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_status ON subscriptions(status);
CREATE INDEX IF NOT EXISTS idx_subscriptions_stripe_id ON subscriptions(stripe_subscription_id);
CREATE INDEX IF NOT EXISTS idx_subscription_usage_user ON subscription_usage(user_id);
CREATE INDEX IF NOT EXISTS idx_payment_history_user ON payment_history(user_id);
CREATE INDEX IF NOT EXISTS idx_feature_access_user ON feature_access(user_id);
CREATE INDEX IF NOT EXISTS idx_premium_content_user ON premium_content_access(user_id);

-- Add stripe_customer_id column to users table if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name = 'users'
                   AND column_name = 'stripe_customer_id') THEN
        ALTER TABLE users ADD COLUMN stripe_customer_id VARCHAR(100);
    END IF;
END $$;

-- Function to check subscription status
CREATE OR REPLACE FUNCTION check_subscription_status(user_id_param INTEGER)
RETURNS TABLE (
    plan_id VARCHAR(50),
    status VARCHAR(20),
    current_period_end TIMESTAMP,
    features JSONB
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        s.plan_id,
        s.status,
        s.current_period_end,
        sp.features
    FROM subscriptions s
    JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.user_id = user_id_param
    AND s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 1;
END;
$$ LANGUAGE plpgsql;

-- Function to get user's feature access
CREATE OR REPLACE FUNCTION get_user_feature_access(user_id_param INTEGER, feature_name_param VARCHAR(100))
RETURNS BOOLEAN AS $$
DECLARE
    has_access BOOLEAN := FALSE;
    user_plan_features JSONB;
BEGIN
    -- Get user's active subscription features
    SELECT sp.features INTO user_plan_features
    FROM subscriptions s
    JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.user_id = user_id_param
    AND s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 1;

    -- If no subscription, use free plan
    IF user_plan_features IS NULL THEN
        SELECT features INTO user_plan_features
        FROM subscription_plans
        WHERE id = 'free';
    END IF;

    -- Check if feature exists and is enabled
    IF user_plan_features IS NOT NULL THEN
        has_access := (user_plan_features->>feature_name_param)::BOOLEAN;
    END IF;

    RETURN COALESCE(has_access, FALSE);
END;
$$ LANGUAGE plpgsql;

-- Function to track feature usage
CREATE OR REPLACE FUNCTION track_feature_usage(
    user_id_param INTEGER,
    feature_type_param VARCHAR(50),
    usage_increment INTEGER DEFAULT 1
)
RETURNS VOID AS $$
DECLARE
    current_usage INTEGER;
    usage_limit INTEGER;
BEGIN
    -- Get current usage for this period
    SELECT usage_count, usage_limit INTO current_usage, usage_limit
    FROM subscription_usage
    WHERE user_id = user_id_param
    AND feature_type = feature_type_param
    AND period_start <= CURRENT_TIMESTAMP
    AND (period_end IS NULL OR period_end >= CURRENT_TIMESTAMP)
    ORDER BY created_at DESC
    LIMIT 1;

    -- If no record exists, create one
    IF current_usage IS NULL THEN
        INSERT INTO subscription_usage (
            user_id, feature_type, usage_count, usage_limit, period_start
        ) VALUES (
            user_id_param, feature_type_param, usage_increment, -1, CURRENT_TIMESTAMP
        );
    ELSE
        -- Update existing usage
        UPDATE subscription_usage
        SET usage_count = usage_count + usage_increment,
            updated_at = CURRENT_TIMESTAMP
        WHERE user_id = user_id_param
        AND feature_type = feature_type_param
        AND period_start <= CURRENT_TIMESTAMP
        AND (period_end IS NULL OR period_end >= CURRENT_TIMESTAMP);
    END IF;
END;
$$ LANGUAGE plpgsql;

-- Function to check if user has exceeded usage limits
CREATE OR REPLACE FUNCTION check_usage_limit(user_id_param INTEGER, feature_type_param VARCHAR(50))
RETURNS BOOLEAN AS $$
DECLARE
    current_usage INTEGER;
    usage_limit INTEGER;
BEGIN
    SELECT usage_count, usage_limit INTO current_usage, usage_limit
    FROM subscription_usage
    WHERE user_id = user_id_param
    AND feature_type = feature_type_param
    AND period_start <= CURRENT_TIMESTAMP
    AND (period_end IS NULL OR period_end >= CURRENT_TIMESTAMP)
    ORDER BY created_at DESC
    LIMIT 1;

    -- If no limit set (-1) or no usage record, allow access
    IF usage_limit = -1 OR current_usage IS NULL THEN
        RETURN TRUE;
    END IF;

    -- Check if usage is within limits
    RETURN current_usage < usage_limit;
END;
$$ LANGUAGE plpgsql;

-- Trigger to automatically grant premium content access when subscription is created
CREATE OR REPLACE FUNCTION grant_premium_content_access()
RETURNS TRIGGER AS $$
BEGIN
    -- Grant access to premium scenarios based on plan
    IF NEW.status = 'active' THEN
        -- Insert premium content access records based on plan features
        INSERT INTO premium_content_access (
            user_id, content_type, content_id, granted_by_plan
        )
        SELECT
            NEW.user_id,
            'scenario',
            s.scenario_id,
            NEW.plan_id
        FROM demo_scenarios s
        WHERE CASE
            WHEN NEW.plan_id = 'premium' THEN s.difficulty IN ('intermediate')
            WHEN NEW.plan_id = 'pro' THEN s.difficulty IN ('intermediate', 'advanced')
            WHEN NEW.plan_id = 'institutional' THEN s.difficulty IN ('intermediate', 'advanced', 'expert')
            ELSE FALSE
        END
        ON CONFLICT (user_id, content_type, content_id) DO NOTHING;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger for subscription creation
DROP TRIGGER IF EXISTS trigger_grant_premium_content ON subscriptions;
CREATE TRIGGER trigger_grant_premium_content
    AFTER INSERT OR UPDATE ON subscriptions
    FOR EACH ROW
    EXECUTE FUNCTION grant_premium_content_access();

-- Function to clean up expired premium content access
CREATE OR REPLACE FUNCTION cleanup_expired_premium_access()
RETURNS VOID AS $$
BEGIN
    -- Remove expired premium content access
    DELETE FROM premium_content_access
    WHERE access_expires_at IS NOT NULL
    AND access_expires_at < CURRENT_TIMESTAMP;

    -- Update subscription status for expired subscriptions
    UPDATE subscriptions
    SET status = 'expired', updated_at = CURRENT_TIMESTAMP
    WHERE current_period_end < CURRENT_TIMESTAMP
    AND status = 'active';
END;
$$ LANGUAGE plpgsql;
