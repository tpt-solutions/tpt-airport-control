-- Add password reset tokens table for secure token-based password reset flow.
-- Tokens are stored as SHA-256 hashes; the raw token is only ever sent by email.

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(64)  UNIQUE NOT NULL,  -- SHA-256 hex of the raw token
    expires_at TIMESTAMP NOT NULL,
    used_at    TIMESTAMP,                     -- set when the token is consumed
    ip_address INET,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE INDEX idx_prt_token_hash ON password_reset_tokens(token_hash);
CREATE INDEX idx_prt_user_id    ON password_reset_tokens(user_id);
CREATE INDEX idx_prt_expires_at ON password_reset_tokens(expires_at);
