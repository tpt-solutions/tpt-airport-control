-- Commercial Operations Module Schema
-- Manages retail operations, advertising, VIP services, and revenue optimization

-- Retail outlets and concessions
CREATE TABLE IF NOT EXISTS retail_outlets (
    outlet_id SERIAL PRIMARY KEY,
    outlet_name VARCHAR(100) NOT NULL,
    outlet_type VARCHAR(50) NOT NULL, -- 'restaurant', 'shop', 'duty_free', 'lounge', 'pharmacy'
    location VARCHAR(255),
    terminal VARCHAR(10),
    gate VARCHAR(10),
    floor_level VARCHAR(10),
    operating_hours JSONB, -- opening and closing times by day
    contact_info JSONB, -- phone, email, website
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'maintenance', 'closed'
    capacity INTEGER,
    average_wait_time INTEGER, -- in minutes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Product catalog
CREATE TABLE IF NOT EXISTS products (
    product_id SERIAL PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    product_category VARCHAR(50) NOT NULL,
    product_type VARCHAR(50), -- 'food', 'beverage', 'tobacco', 'cosmetics', 'electronics'
    description TEXT,
    price DECIMAL(8,2) NOT NULL,
    cost DECIMAL(8,2),
    tax_rate DECIMAL(5,2) DEFAULT 0,
    barcode VARCHAR(100) UNIQUE,
    sku VARCHAR(100) UNIQUE,
    brand VARCHAR(100),
    supplier VARCHAR(100),
    stock_quantity INTEGER DEFAULT 0,
    min_stock_level INTEGER DEFAULT 0,
    max_stock_level INTEGER,
    is_available BOOLEAN DEFAULT TRUE,
    is_tax_free BOOLEAN DEFAULT FALSE,
    weight_kg DECIMAL(6,2),
    dimensions JSONB, -- length, width, height
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sales transactions
CREATE TABLE IF NOT EXISTS sales_transactions (
    transaction_id SERIAL PRIMARY KEY,
    outlet_id INTEGER REFERENCES retail_outlets(outlet_id),
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    customer_type VARCHAR(20) DEFAULT 'walk_in', -- 'walk_in', 'passenger', 'crew', 'vip'
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    payment_method VARCHAR(30), -- 'cash', 'card', 'mobile_wallet', 'boarding_pass'
    payment_reference VARCHAR(100),
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(8,2) DEFAULT 0,
    discount_amount DECIMAL(8,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    cashier_id INTEGER,
    status VARCHAR(20) DEFAULT 'completed', -- 'pending', 'completed', 'refunded', 'cancelled'
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transaction items
CREATE TABLE IF NOT EXISTS transaction_items (
    item_id SERIAL PRIMARY KEY,
    transaction_id INTEGER REFERENCES sales_transactions(transaction_id),
    product_id INTEGER REFERENCES products(product_id),
    quantity INTEGER NOT NULL,
    unit_price DECIMAL(8,2) NOT NULL,
    discount_amount DECIMAL(6,2) DEFAULT 0,
    tax_amount DECIMAL(6,2) DEFAULT 0,
    total_amount DECIMAL(8,2) NOT NULL,
    product_name VARCHAR(255), -- snapshot for historical accuracy
    product_category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Advertising spaces and campaigns
CREATE TABLE IF NOT EXISTS advertising_spaces (
    space_id SERIAL PRIMARY KEY,
    space_name VARCHAR(100) NOT NULL,
    space_type VARCHAR(50) NOT NULL, -- 'digital_signage', 'billboard', 'shelf_space', 'menu_board'
    location VARCHAR(255),
    terminal VARCHAR(10),
    dimensions VARCHAR(50), -- e.g., '1920x1080', '4x8 feet'
    display_type VARCHAR(30), -- 'LCD', 'LED', 'static'
    operating_hours JSONB,
    base_price_per_day DECIMAL(8,2),
    status VARCHAR(20) DEFAULT 'available', -- 'available', 'booked', 'maintenance'
    technical_specs JSONB, -- resolution, connectivity, power requirements
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Advertising campaigns
CREATE TABLE IF NOT EXISTS advertising_campaigns (
    campaign_id SERIAL PRIMARY KEY,
    campaign_name VARCHAR(255) NOT NULL,
    advertiser_name VARCHAR(255) NOT NULL,
    advertiser_contact JSONB, -- contact details
    campaign_type VARCHAR(50), -- 'seasonal', 'product_launch', 'brand_awareness', 'promotional'
    target_audience JSONB, -- demographics, passenger types, routes
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_budget DECIMAL(10,2),
    spaces_allocated JSONB, -- array of space_ids with pricing
    creative_assets JSONB, -- file paths, URLs, descriptions
    status VARCHAR(20) DEFAULT 'planned', -- 'planned', 'active', 'completed', 'cancelled'
    performance_metrics JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- VIP lounge services
CREATE TABLE IF NOT EXISTS vip_lounges (
    lounge_id SERIAL PRIMARY KEY,
    lounge_name VARCHAR(100) NOT NULL,
    lounge_type VARCHAR(30) NOT NULL, -- 'first_class', 'business_class', 'priority_pass', 'paid_access'
    location VARCHAR(255),
    terminal VARCHAR(10),
    capacity INTEGER,
    operating_hours JSONB,
    membership_required BOOLEAN DEFAULT FALSE,
    price_per_hour DECIMAL(6,2),
    price_per_day DECIMAL(8,2),
    amenities JSONB, -- spa, showers, food, drinks, wifi, etc.
    contact_info JSONB,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- VIP lounge visits
CREATE TABLE IF NOT EXISTS lounge_visits (
    visit_id SERIAL PRIMARY KEY,
    lounge_id INTEGER REFERENCES vip_lounges(lounge_id),
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    booking_id INTEGER REFERENCES bookings(booking_id),
    check_in_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    check_out_time TIMESTAMP,
    duration_minutes INTEGER,
    access_type VARCHAR(30), -- 'complimentary', 'paid', 'membership'
    amenities_used JSONB DEFAULT '[]',
    total_charged DECIMAL(8,2) DEFAULT 0,
    payment_status VARCHAR(20) DEFAULT 'pending',
    satisfaction_rating INTEGER CHECK (satisfaction_rating BETWEEN 1 AND 5),
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Revenue analytics
CREATE TABLE IF NOT EXISTS revenue_analytics (
    analytics_id SERIAL PRIMARY KEY,
    date DATE,
    outlet_id INTEGER REFERENCES retail_outlets(outlet_id),
    total_sales DECIMAL(10,2) DEFAULT 0,
    transaction_count INTEGER DEFAULT 0,
    average_transaction DECIMAL(8,2) DEFAULT 0,
    top_products JSONB DEFAULT '[]',
    peak_hours JSONB DEFAULT '[]',
    customer_demographics JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inventory management
CREATE TABLE IF NOT EXISTS inventory_movements (
    movement_id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(product_id),
    outlet_id INTEGER REFERENCES retail_outlets(outlet_id),
    movement_type VARCHAR(20) NOT NULL, -- 'stock_in', 'stock_out', 'adjustment', 'transfer'
    quantity INTEGER NOT NULL,
    reference_number VARCHAR(50),
    reason VARCHAR(100),
    unit_cost DECIMAL(8,2),
    total_value DECIMAL(10,2),
    performed_by INTEGER,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customer loyalty program
CREATE TABLE IF NOT EXISTS loyalty_program (
    loyalty_id SERIAL PRIMARY KEY,
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    membership_tier VARCHAR(20) DEFAULT 'bronze', -- 'bronze', 'silver', 'gold', 'platinum'
    points_balance INTEGER DEFAULT 0,
    total_points_earned INTEGER DEFAULT 0,
    total_points_redeemed INTEGER DEFAULT 0,
    join_date DATE DEFAULT CURRENT_DATE,
    last_activity DATE,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive', 'suspended'
    preferences JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Loyalty transactions
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    transaction_id SERIAL PRIMARY KEY,
    loyalty_id INTEGER REFERENCES loyalty_program(loyalty_id),
    transaction_type VARCHAR(30) NOT NULL, -- 'earn', 'redeem', 'bonus', 'adjustment'
    points INTEGER NOT NULL,
    reference_type VARCHAR(30), -- 'purchase', 'visit', 'survey', 'referral'
    reference_id INTEGER,
    description TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Concession agreements
CREATE TABLE IF NOT EXISTS concession_agreements (
    agreement_id SERIAL PRIMARY KEY,
    outlet_id INTEGER REFERENCES retail_outlets(outlet_id),
    concessionaire_name VARCHAR(255) NOT NULL,
    agreement_type VARCHAR(50), -- 'lease', 'franchise', 'management'
    start_date DATE NOT NULL,
    end_date DATE,
    monthly_rent DECIMAL(10,2),
    revenue_share_percentage DECIMAL(5,2),
    minimum_guarantee DECIMAL(10,2),
    terms_conditions TEXT,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'expired', 'terminated'
    contact_person VARCHAR(100),
    contact_details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Digital signage content
CREATE TABLE IF NOT EXISTS digital_content (
    content_id SERIAL PRIMARY KEY,
    content_name VARCHAR(255) NOT NULL,
    content_type VARCHAR(30) NOT NULL, -- 'image', 'video', 'text', 'interactive'
    file_path TEXT,
    file_url TEXT,
    dimensions VARCHAR(20), -- '1920x1080'
    duration_seconds INTEGER, -- for videos
    target_audience JSONB DEFAULT '{}',
    priority INTEGER DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    schedule JSONB DEFAULT '{}', -- display schedule
    analytics JSONB DEFAULT '{}', -- views, engagement
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Parking revenue (if applicable)
CREATE TABLE IF NOT EXISTS parking_revenue (
    parking_id SERIAL PRIMARY KEY,
    facility_name VARCHAR(100),
    vehicle_type VARCHAR(20), -- 'car', 'motorcycle', 'bus', 'truck'
    entry_time TIMESTAMP,
    exit_time TIMESTAMP,
    duration_hours DECIMAL(6,2),
    parking_fee DECIMAL(6,2),
    payment_method VARCHAR(30),
    payment_reference VARCHAR(50),
    passenger_id INTEGER REFERENCES passengers(passenger_id),
    flight_id INTEGER REFERENCES flights(flight_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_retail_outlets_location ON retail_outlets(location);
CREATE INDEX IF NOT EXISTS idx_products_category ON products(product_category);
CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode);
CREATE INDEX IF NOT EXISTS idx_sales_transactions_date ON sales_transactions(transaction_date);
CREATE INDEX IF NOT EXISTS idx_sales_transactions_outlet ON sales_transactions(outlet_id);
CREATE INDEX IF NOT EXISTS idx_transaction_items_transaction ON transaction_items(transaction_id);
CREATE INDEX IF NOT EXISTS idx_advertising_spaces_location ON advertising_spaces(location);
CREATE INDEX IF NOT EXISTS idx_campaigns_dates ON advertising_campaigns(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_lounge_visits_passenger ON lounge_visits(passenger_id);
CREATE INDEX IF NOT EXISTS idx_revenue_analytics_date ON revenue_analytics(date);
CREATE INDEX IF NOT EXISTS idx_inventory_product ON inventory_movements(product_id);
CREATE INDEX IF NOT EXISTS idx_loyalty_passenger ON loyalty_program(passenger_id);
CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_loyalty ON loyalty_transactions(loyalty_id);
CREATE INDEX IF NOT EXISTS idx_digital_content_active ON digital_content(is_active);

-- Insert sample retail outlets
INSERT INTO retail_outlets (outlet_name, outlet_type, location, terminal, operating_hours, status) VALUES
('Main Terminal Coffee Shop', 'restaurant', 'Main Terminal Food Court', 'A', '{"monday": {"open": "05:00", "close": "22:00"}, "tuesday": {"open": "05:00", "close": "22:00"}, "wednesday": {"open": "05:00", "close": "22:00"}, "thursday": {"open": "05:00", "close": "22:00"}, "friday": {"open": "05:00", "close": "23:00"}, "saturday": {"open": "05:00", "close": "23:00"}, "sunday": {"open": "05:00", "close": "22:00"}}', 'active'),
('Duty Free Shop', 'duty_free', 'International Departures', 'B', '{"monday": {"open": "06:00", "close": "21:00"}, "tuesday": {"open": "06:00", "close": "21:00"}, "wednesday": {"open": "06:00", "close": "21:00"}, "thursday": {"open": "06:00", "close": "21:00"}, "friday": {"open": "06:00", "close": "22:00"}, "saturday": {"open": "06:00", "close": "22:00"}, "sunday": {"open": "06:00", "close": "21:00"}}', 'active'),
('Business Class Lounge', 'lounge', 'Terminal A Upper Level', 'A', '{"monday": {"open": "05:00", "close": "23:00"}, "tuesday": {"open": "05:00", "close": "23:00"}, "wednesday": {"open": "05:00", "close": "23:00"}, "thursday": {"open": "05:00", "close": "23:00"}, "friday": {"open": "05:00", "close": "23:00"}, "saturday": {"open": "05:00", "close": "23:00"}, "sunday": {"open": "05:00", "close": "23:00"}}', 'active'),
('Pharmacy Plus', 'pharmacy', 'Main Terminal Level 1', 'A', '{"monday": {"open": "06:00", "close": "22:00"}, "tuesday": {"open": "06:00", "close": "22:00"}, "wednesday": {"open": "06:00", "close": "22:00"}, "thursday": {"open": "06:00", "close": "22:00"}, "friday": {"open": "06:00", "close": "22:00"}, "saturday": {"open": "06:00", "close": "22:00"}, "sunday": {"open": "07:00", "close": "21:00"}}', 'active')
ON CONFLICT DO NOTHING;

-- Insert sample products
INSERT INTO products (product_name, product_category, product_type, price, tax_rate, brand, stock_quantity) VALUES
('Premium Coffee', 'beverages', 'beverage', 4.50, 8.25, 'Starbucks', 150),
('Chocolate Bar', 'snacks', 'food', 3.25, 8.25, 'Hershey', 200),
('Perfume', 'cosmetics', 'cosmetics', 85.00, 0.00, 'Chanel', 25),
('Cigarettes', 'tobacco', 'tobacco', 12.50, 0.00, 'Marlboro', 100),
('Headphones', 'electronics', 'electronics', 199.99, 8.25, 'Sony', 15),
('Magazine', 'reading', 'reading', 6.99, 8.25, 'Time', 75)
ON CONFLICT DO NOTHING;

-- Insert sample advertising spaces
INSERT INTO advertising_spaces (space_name, space_type, location, terminal, dimensions, base_price_per_day, status) VALUES
('Main Terminal LCD Wall', 'digital_signage', 'Main Terminal Entrance', 'A', '3840x2160', 500.00, 'available'),
('Gate A12 Billboard', 'billboard', 'Gate A12 Waiting Area', 'A', '8x10 feet', 150.00, 'available'),
('Coffee Shop Menu Board', 'menu_board', 'Terminal A Food Court', 'A', '1920x1080', 75.00, 'booked'),
('Baggage Claim LED Strip', 'digital_signage', 'Baggage Claim Hall', 'A', '1920x108', 200.00, 'available')
ON CONFLICT DO NOTHING;

-- Insert sample VIP lounges
INSERT INTO vip_lounges (lounge_name, lounge_type, location, terminal, capacity, price_per_hour, amenities, status) VALUES
('Executive Business Lounge', 'business_class', 'Terminal A Upper Level', 'A', 50, 75.00, '["free_wifi", "snacks", "drinks", "newspapers", "showers", "workstations"]', 'active'),
('First Class Lounge', 'first_class', 'Terminal B Satellite', 'B', 30, 125.00, '["champagne", "gourmet_food", "massage_chairs", "private_rooms", "limo_service"]', 'active'),
('Priority Pass Lounge', 'priority_pass', 'Main Terminal Mezzanine', 'A', 75, 35.00, '["free_wifi", "snacks", "drinks", "newspapers", "workstations"]', 'active')
ON CONFLICT DO NOTHING;

-- Function to calculate daily revenue by outlet
CREATE OR REPLACE FUNCTION calculate_daily_revenue(p_date DATE, p_outlet_id INTEGER DEFAULT NULL)
RETURNS DECIMAL AS $$
DECLARE
    total_revenue DECIMAL := 0;
BEGIN
    SELECT COALESCE(SUM(total_amount), 0)
    INTO total_revenue
    FROM sales_transactions
    WHERE DATE(transaction_date) = p_date
    AND (p_outlet_id IS NULL OR outlet_id = p_outlet_id)
    AND status = 'completed';

    RETURN total_revenue;
END;
$$ LANGUAGE plpgsql;

-- Function to get top selling products
CREATE OR REPLACE FUNCTION get_top_products(p_date DATE, p_limit INTEGER DEFAULT 10)
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_agg(
        json_build_object(
            'product_name', product_name,
            'category', product_category,
            'quantity_sold', total_quantity,
            'revenue', total_revenue
        )
    )
    INTO result
    FROM (
        SELECT
            p.product_name,
            p.product_category,
            SUM(ti.quantity) as total_quantity,
            SUM(ti.total_amount) as total_revenue
        FROM transaction_items ti
        JOIN products p ON ti.product_id = p.product_id
        JOIN sales_transactions st ON ti.transaction_id = st.transaction_id
        WHERE DATE(st.transaction_date) = p_date
        AND st.status = 'completed'
        GROUP BY p.product_id, p.product_name, p.product_category
        ORDER BY total_revenue DESC
        LIMIT p_limit
    ) top_products;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Function to calculate loyalty points
CREATE OR REPLACE FUNCTION calculate_loyalty_points(p_transaction_amount DECIMAL)
RETURNS INTEGER AS $$
DECLARE
    points_per_dollar INTEGER := 1; -- 1 point per dollar spent
    bonus_multiplier DECIMAL := 1.0;
    total_points INTEGER;
BEGIN
    -- Apply bonus multipliers based on amount
    IF p_transaction_amount >= 100 THEN
        bonus_multiplier := 1.5; -- 50% bonus for $100+ purchases
    ELSIF p_transaction_amount >= 50 THEN
        bonus_multiplier := 1.25; -- 25% bonus for $50+ purchases
    END IF;

    total_points := FLOOR(p_transaction_amount * points_per_dollar * bonus_multiplier);

    RETURN total_points;
END;
$$ LANGUAGE plpgsql;

-- Function to award loyalty points
CREATE OR REPLACE FUNCTION award_loyalty_points(p_passenger_id INTEGER, p_transaction_id INTEGER, p_amount DECIMAL)
RETURNS INTEGER AS $$
DECLARE
    loyalty_record RECORD;
    points_awarded INTEGER;
BEGIN
    -- Get or create loyalty record
    SELECT * INTO loyalty_record
    FROM loyalty_program
    WHERE passenger_id = p_passenger_id;

    IF NOT FOUND THEN
        INSERT INTO loyalty_program (passenger_id)
        VALUES (p_passenger_id)
        RETURNING * INTO loyalty_record;
    END IF;

    -- Calculate points
    points_awarded := calculate_loyalty_points(p_amount);

    -- Award points
    UPDATE loyalty_program
    SET points_balance = points_balance + points_awarded,
        total_points_earned = total_points_earned + points_awarded,
        last_activity = CURRENT_DATE
    WHERE loyalty_id = loyalty_record.loyalty_id;

    -- Record transaction
    INSERT INTO loyalty_transactions (
        loyalty_id, transaction_type, points, reference_type, reference_id, description
    ) VALUES (
        loyalty_record.loyalty_id, 'earn', points_awarded, 'purchase', p_transaction_id,
        'Points earned from purchase of $' || p_amount
    );

    RETURN points_awarded;
END;
$$ LANGUAGE plpgsql;

-- Function to get commercial dashboard data
CREATE OR REPLACE FUNCTION get_commercial_dashboard()
RETURNS JSON AS $$
DECLARE
    result JSON;
    today DATE := CURRENT_DATE;
    yesterday DATE := CURRENT_DATE - INTERVAL '1 day';
BEGIN
    SELECT json_build_object(
        'today_revenue', calculate_daily_revenue(today),
        'yesterday_revenue', calculate_daily_revenue(yesterday),
        'revenue_change_percent', CASE
            WHEN calculate_daily_revenue(yesterday) > 0 THEN
                ROUND(
                    ((calculate_daily_revenue(today) - calculate_daily_revenue(yesterday)) /
                     calculate_daily_revenue(yesterday)) * 100, 2
                )
            ELSE 0
        END,
        'today_transactions', (
            SELECT COUNT(*)
            FROM sales_transactions
            WHERE DATE(transaction_date) = today
            AND status = 'completed'
        ),
        'active_campaigns', (
            SELECT COUNT(*)
            FROM advertising_campaigns
            WHERE status = 'active'
            AND CURRENT_DATE BETWEEN start_date AND end_date
        ),
        'lounge_occupancy', (
            SELECT json_agg(
                json_build_object(
                    'lounge_name', lounge_name,
                    'current_occupancy', (
                        SELECT COUNT(*)
                        FROM lounge_visits
                        WHERE lounge_id = vl.lounge_id
                        AND check_out_time IS NULL
                    ),
                    'capacity', capacity
                )
            )
            FROM vip_lounges vl
            WHERE status = 'active'
        ),
        'top_products_today', get_top_products(today, 5),
        'available_spaces', (
            SELECT COUNT(*)
            FROM advertising_spaces
            WHERE status = 'available'
        ),
        'low_stock_alerts', (
            SELECT COUNT(*)
            FROM products
            WHERE stock_quantity <= min_stock_level
            AND is_available = true
        )
    ) INTO result;

    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Trigger to automatically award loyalty points on purchase
CREATE OR REPLACE FUNCTION auto_award_loyalty_points()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'completed' AND NEW.customer_type = 'passenger' AND NEW.passenger_id IS NOT NULL THEN
        PERFORM award_loyalty_points(NEW.passenger_id, NEW.transaction_id, NEW.total_amount);
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create trigger for automatic loyalty points
CREATE TRIGGER auto_award_loyalty_points_trigger
    AFTER INSERT OR UPDATE ON sales_transactions
    FOR EACH ROW
    EXECUTE FUNCTION auto_award_loyalty_points();

-- Function to update product stock levels
CREATE OR REPLACE FUNCTION update_product_stock(p_product_id INTEGER, p_quantity_change INTEGER, p_movement_type VARCHAR)
RETURNS BOOLEAN AS $$
DECLARE
    current_stock INTEGER;
BEGIN
    -- Get current stock
    SELECT stock_quantity INTO current_stock
    FROM products
    WHERE product_id = p_product_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Product not found: %', p_product_id;
    END IF;

    -- Update stock
    UPDATE products
    SET stock_quantity = stock_quantity + p_quantity_change,
        updated_at = CURRENT_TIMESTAMP
    WHERE product_id = p_product_id;

    -- Record movement
    INSERT INTO inventory_movements (
        product_id, movement_type, quantity, unit_cost
    ) VALUES (
        p_product_id, p_movement_type, p_quantity_change,
        CASE WHEN p_movement_type = 'stock_in' THEN (SELECT cost FROM products WHERE product_id = p_product_id) ELSE NULL END
    );

    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;
