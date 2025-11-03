#!/bin/bash

# Comprehensive Rate Limiting Load Test
# Tests actual enforcement with concurrent requests

API_URL="http://api.ecom.local"
TENANT_ID="9efae4ea-94fc-4807-b1bc-5e495ee7858c"

echo "======================================"
echo "Rate Limiting Load Test"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Test: Public API - Should rate limit at 101st request
echo -e "${YELLOW}Load Test: Public API (100 req/min limit)${NC}"
echo "Sending 110 requests rapidly..."

success=0
rate_limited=0
first_rate_limit=0

for i in {1..110}; do
    http_code=$(curl -s -o /dev/null -w "%{http_code}" "$API_URL/api/products")

    if [ "$http_code" == "200" ] || [ "$http_code" == "401" ]; then
        ((success++))
    elif [ "$http_code" == "429" ]; then
        ((rate_limited++))
        if [ $first_rate_limit -eq 0 ]; then
            first_rate_limit=$i
        fi
    fi

    # Progress indicator
    if [ $((i % 10)) -eq 0 ]; then
        echo -n "."
    fi
done

echo ""
echo "Results:"
echo "  Success: $success"
echo "  Rate Limited: $rate_limited"
echo "  First rate limit at request: $first_rate_limit"

if [ $first_rate_limit -le 101 ] && [ $first_rate_limit -ge 99 ]; then
    echo -e "${GREEN}✓ Rate limiting working correctly (triggered around request 100)${NC}"
else
    echo -e "${RED}✗ Rate limiting issue (should trigger around request 100)${NC}"
fi

echo ""

# Verify headers on rate limited response
echo -e "${YELLOW}Verifying 429 Response Headers${NC}"
response=$(curl -s -i "$API_URL/api/products")

if echo "$response" | grep -q "429 Too Many Requests"; then
    echo -e "${GREEN}✓ Got 429 response${NC}"
    echo "$response" | grep -E "(Retry-After|X-RateLimit)" || echo -e "${RED}✗ Missing rate limit headers${NC}"
else
    echo "Waiting for rate limit to apply..."
    sleep 1
    # Try one more time
    response=$(curl -s -i "$API_URL/api/products")
    if echo "$response" | grep -q "429"; then
        echo -e "${GREEN}✓ Got 429 response${NC}"
        echo "$response" | grep -E "(Retry-After|X-RateLimit)"
    fi
fi

echo ""

# Test: Authenticated API - Much higher limit
echo -e "${YELLOW}Load Test: Authenticated API (1000 req/min limit)${NC}"
echo "Sending 50 requests with X-Tenant-ID header..."

auth_success=0
auth_rate_limited=0

for i in {1..50}; do
    http_code=$(curl -s -o /dev/null -w "%{http_code}" \
        -H "X-Tenant-ID: $TENANT_ID" \
        "$API_URL/api/products")

    if [ "$http_code" == "200" ] || [ "$http_code" == "401" ]; then
        ((auth_success++))
    elif [ "$http_code" == "429" ]; then
        ((auth_rate_limited++))
    fi

    if [ $((i % 10)) -eq 0 ]; then
        echo -n "."
    fi
done

echo ""
echo "Results:"
echo "  Success: $auth_success"
echo "  Rate Limited: $auth_rate_limited"

if [ $auth_rate_limited -eq 0 ]; then
    echo -e "${GREEN}✓ No rate limiting (expected, limit is 1000/min)${NC}"
else
    echo -e "${YELLOW}⚠ Unexpected rate limiting on authenticated API${NC}"
fi

echo ""

# Check Redis storage
echo -e "${YELLOW}Checking Redis Storage${NC}"
echo "Rate limit keys in Redis:"
redis-cli -n 3 KEYS "*" | head -10

echo ""
echo "Sample rate limit data:"
redis_key=$(redis-cli -n 3 KEYS "*" | head -1)
if [ -n "$redis_key" ]; then
    redis-cli -n 3 GET "$redis_key"
fi

echo ""
echo "======================================"
echo "Load Test Complete"
echo "======================================"
