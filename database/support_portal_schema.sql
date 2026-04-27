-- Customer Support Portal Schema
-- Manages help desk tickets, knowledge base, and customer support operations

-- Support Tickets table
CREATE TABLE IF NOT EXISTS support_tickets (
    id VARCHAR(50) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    priority VARCHAR(20) DEFAULT 'normal',
    status VARCHAR(20) DEFAULT 'open', -- open, in_progress, pending, closed
    assigned_to INTEGER REFERENCES users(id),
    closed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ticket Comments table
CREATE TABLE IF NOT EXISTS support_ticket_comments (
    id SERIAL PRIMARY KEY,
    ticket_id VARCHAR(50) REFERENCES support_tickets(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    comment TEXT NOT NULL,
    is_staff BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Feedback table
CREATE TABLE IF NOT EXISTS user_feedback (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_support_tickets_user ON support_tickets(user_id);
CREATE INDEX IF NOT EXISTS idx_support_tickets_status ON support_tickets(status);
CREATE INDEX IF NOT EXISTS idx_support_tickets_category ON support_tickets(category);
CREATE INDEX IF NOT EXISTS idx_support_ticket_comments_ticket ON support_ticket_comments(ticket_id);
CREATE INDEX IF NOT EXISTS idx_user_feedback_user ON user_feedback(user_id);

-- Insert default knowledge base articles
INSERT INTO knowledge_base_articles (id, title, category, content, url, is_published, created_at) VALUES
('kb_getting_started', 'Getting Started Guide', 'Getting Started', 'Learn how to get started with the Flight Control System.', '/docs/getting-started', true, CURRENT_TIMESTAMP),
('kb_subscription', 'Subscription Management', 'Billing', 'How to manage your subscription, upgrade or cancel.', '/docs/subscription', true, CURRENT_TIMESTAMP),
('kb_api_documentation', 'API Documentation', 'Developer', 'Complete API reference documentation.', '/docs/api', true, CURRENT_TIMESTAMP),
('kb_scenario_editor', 'Scenario Editor Guide', 'Features', 'How to create and customize your own scenarios.', '/docs/scenario-editor', true, CURRENT_TIMESTAMP),
('kb_security', 'Security & Privacy', 'Security', 'Information about our security practices and data protection.', '/docs/security', true, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;