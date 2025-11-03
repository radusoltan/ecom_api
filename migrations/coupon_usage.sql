-- Add usage tracking fields to promotions table
ALTER TABLE promotions 
ADD COLUMN max_uses INTEGER DEFAULT NULL,
ADD COLUMN max_uses_per_user INTEGER DEFAULT 1,
ADD COLUMN usage_count INTEGER DEFAULT 0;

-- Create coupon_usage tracking table
CREATE TABLE coupon_usage (
    id VARCHAR(36) PRIMARY KEY,
    promotion_id VARCHAR(36) NOT NULL,
    tenant_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) DEFAULT NULL,
    session_id VARCHAR(255) DEFAULT NULL,
    order_id VARCHAR(36) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    used_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    CONSTRAINT fk_coupon_usage_promotion FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
    CONSTRAINT chk_user_or_session CHECK (user_id IS NOT NULL OR session_id IS NOT NULL)
);

-- Indexes for performance
CREATE INDEX idx_coupon_usage_promotion ON coupon_usage(promotion_id);
CREATE INDEX idx_coupon_usage_user ON coupon_usage(user_id);
CREATE INDEX idx_coupon_usage_session ON coupon_usage(session_id);
CREATE INDEX idx_coupon_usage_tenant ON coupon_usage(tenant_id);
CREATE UNIQUE INDEX idx_coupon_usage_unique_user ON coupon_usage(promotion_id, user_id) WHERE user_id IS NOT NULL;
CREATE UNIQUE INDEX idx_coupon_usage_unique_session ON coupon_usage(promotion_id, session_id) WHERE session_id IS NOT NULL AND order_id IS NULL;

COMMENT ON TABLE coupon_usage IS 'Tracks coupon code usage to prevent reuse';
COMMENT ON COLUMN promotions.max_uses IS 'Maximum number of times this coupon can be used globally (NULL = unlimited)';
COMMENT ON COLUMN promotions.max_uses_per_user IS 'Maximum number of times a single user can use this coupon (default 1)';
COMMENT ON COLUMN promotions.usage_count IS 'Current number of times this coupon has been used';
