#!/bin/bash

# Test Rate Limiting Implementation
# Tests various API endpoint types to ensure rate limits are applied correctly

API_URL="http://api.ecom.local"
TENANT_ID="9efae4ea-94fc-4807-b1bc-5e495ee7858c"

echo "======================================"
echo "Rate Limiting Test Suite"
echo "======================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Public API Rate Limit (100 req/min per IP)
echo -e "${YELLOW}Test 1: Public API Rate Limit (100 req/min)${NC}"
echo "Sending 105 requests to public endpoint..."
success_count=0
rate_limited_count=0

for i in {1..105}; do
    response=$(curl -s -o /dev/null -w "%{http_code}" "$API_URL/api/products?page=1&limit=10")

    if [ "$response" == "200" ] || [ "$response" == "401" ]; then
        ((success_count++))
    elif [ "$response" == "429" ]; then
        ((rate_limited_count++))
        if [ $rate_limited_count -eq 1 ]; then
            echo -e "${GREEN}✓ Rate limit triggered at request #$i${NC}"
            # Get full response with headers
            curl -s -i "$API_URL/api/products?page=1&limit=10" | head -20
        fi
    fi

    # Small delay to avoid overwhelming the server
    sleep 0.01
done

echo "Success count: $success_count"
echo "Rate limited count: $rate_limited_count"
echo ""

# Wait for rate limit to reset
echo "Waiting 5 seconds for rate limit window to slide..."
sleep 5

# Test 2: Authenticated API Rate Limit (1000 req/min per tenant)
echo -e "${YELLOW}Test 2: Authenticated API with X-Tenant-ID header${NC}"
echo "Sending 10 requests with tenant header..."

for i in {1..10}; do
    response=$(curl -s -w "\nHTTP Status: %{http_code}\n" \
        -H "X-Tenant-ID: $TENANT_ID" \
        -H "Accept: application/json" \
        "$API_URL/api/products?page=1&limit=5")

    http_code=$(echo "$response" | grep "HTTP Status:" | cut -d' ' -f3)

    if [ "$http_code" == "200" ] || [ "$http_code" == "401" ]; then
        echo -e "${GREEN}✓ Request $i: Success (HTTP $http_code)${NC}"
    elif [ "$http_code" == "429" ]; then
        echo -e "${RED}✗ Request $i: Rate limited (HTTP 429)${NC}"
        echo "$response" | head -10
        break
    fi
done
echo ""

# Test 3: Search API Rate Limit (200 req/min per tenant)
echo -e "${YELLOW}Test 3: Search API Rate Limit (200 req/min per tenant)${NC}"
echo "Testing search endpoint..."

for i in {1..5}; do
    response=$(curl -s -w "\nHTTP Status: %{http_code}\n" \
        -H "X-Tenant-ID: $TENANT_ID" \
        -H "Accept: application/json" \
        "$API_URL/api/search?q=laptop&locale=en")

    http_code=$(echo "$response" | grep "HTTP Status:" | cut -d' ' -f3)

    if [ "$http_code" == "200" ] || [ "$http_code" == "401" ] || [ "$http_code" == "404" ]; then
        echo -e "${GREEN}✓ Search request $i: HTTP $http_code${NC}"
    elif [ "$http_code" == "429" ]; then
        echo -e "${RED}✗ Search request $i: Rate limited${NC}"
        echo "$response" | head -10
        break
    fi
done
echo ""

# Test 4: Checkout API Rate Limit (50 req/min per session)
echo -e "${YELLOW}Test 4: Checkout API Rate Limit (50 req/min per session)${NC}"
echo "Testing cart/checkout endpoints..."

# Create a session by using cookies
COOKIE_JAR=$(mktemp)

for i in {1..5}; do
    response=$(curl -s -w "\nHTTP Status: %{http_code}\n" \
        -H "X-Tenant-ID: $TENANT_ID" \
        -H "Accept: application/json" \
        -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
        "$API_URL/api/cart")

    http_code=$(echo "$response" | grep "HTTP Status:" | cut -d' ' -f3)

    if [ "$http_code" == "200" ] || [ "$http_code" == "401" ] || [ "$http_code" == "404" ]; then
        echo -e "${GREEN}✓ Cart request $i: HTTP $http_code${NC}"
    elif [ "$http_code" == "429" ]; then
        echo -e "${RED}✗ Cart request $i: Rate limited${NC}"
        # Show rate limit headers
        curl -s -i \
            -H "X-Tenant-ID: $TENANT_ID" \
            -b "$COOKIE_JAR" \
            "$API_URL/api/cart" | grep -E "(HTTP|RateLimit|Retry-After)"
        break
    fi
done

rm -f "$COOKIE_JAR"
echo ""

# Test 5: Check Rate Limit Headers
echo -e "${YELLOW}Test 5: Verify Rate Limit Headers${NC}"
echo "Checking if X-RateLimit headers are present..."

headers=$(curl -s -i \
    -H "X-Tenant-ID: $TENANT_ID" \
    "$API_URL/api/products?page=1&limit=5" | head -20)

echo "$headers"

if echo "$headers" | grep -q "X-RateLimit-Limit"; then
    echo -e "${GREEN}✓ X-RateLimit-Limit header present${NC}"
else
    echo -e "${RED}✗ X-RateLimit-Limit header missing${NC}"
fi

if echo "$headers" | grep -q "X-RateLimit-Remaining"; then
    echo -e "${GREEN}✓ X-RateLimit-Remaining header present${NC}"
else
    echo -e "${RED}✗ X-RateLimit-Remaining header missing${NC}"
fi

if echo "$headers" | grep -q "X-RateLimit-Reset"; then
    echo -e "${GREEN}✓ X-RateLimit-Reset header present${NC}"
else
    echo -e "${RED}✗ X-RateLimit-Reset header missing${NC}"
fi

echo ""
echo "======================================"
echo "Rate Limiting Test Complete"
echo "======================================"
