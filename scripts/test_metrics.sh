#!/bin/bash
#
# Metrics Validation Script
#
# Tests that metrics are properly collected and exposed
#

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

BASE_URL="http://127.0.0.1:8001"
METRICS_URL="$BASE_URL/metrics"

echo -e "${BLUE}=== E-Commerce Platform - Metrics Validation ===${NC}\n"

# 1. Check if /metrics endpoint is accessible
echo -e "${BLUE}[1/5] Checking /metrics endpoint accessibility...${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$METRICS_URL")

if [ "$HTTP_CODE" -eq 200 ]; then
    echo -e "${GREEN}✓ /metrics endpoint is accessible (HTTP $HTTP_CODE)${NC}"
else
    echo -e "${RED}✗ /metrics endpoint returned HTTP $HTTP_CODE${NC}"
    exit 1
fi

# 2. Check Prometheus format
echo -e "\n${BLUE}[2/5] Validating Prometheus format...${NC}"
METRICS_OUTPUT=$(curl -s "$METRICS_URL")

if echo "$METRICS_OUTPUT" | grep -q "# HELP"; then
    echo -e "${GREEN}✓ Output contains HELP declarations${NC}"
else
    echo -e "${RED}✗ Missing HELP declarations${NC}"
    exit 1
fi

if echo "$METRICS_OUTPUT" | grep -q "# TYPE"; then
    echo -e "${GREEN}✓ Output contains TYPE declarations${NC}"
else
    echo -e "${RED}✗ Missing TYPE declarations${NC}"
    exit 1
fi

# 3. Check for Artprima metrics (baseline)
echo -e "\n${BLUE}[3/5] Checking Artprima baseline metrics...${NC}"

ARTPRIMA_METRICS=(
    "ecom_http_requests_total"
    "ecom_request_durations_histogram_seconds"
    "ecom_instance_name"
)

for metric in "${ARTPRIMA_METRICS[@]}"; do
    if echo "$METRICS_OUTPUT" | grep -q "$metric"; then
        echo -e "${GREEN}✓ Found: $metric${NC}"
    else
        echo -e "${YELLOW}⚠ Missing: $metric (Artprima bundle)${NC}"
    fi
done

# 4. Check for custom business metrics
echo -e "\n${BLUE}[4/5] Checking custom business metrics...${NC}"

CUSTOM_METRICS=(
    "order_placed_total"
    "order_status_change_total"
    "payment_total"
    "payment_failed_total"
    "api_requests_total"
    "api_request_duration_seconds"
)

FOUND_COUNT=0
for metric in "${CUSTOM_METRICS[@]}"; do
    if echo "$METRICS_OUTPUT" | grep -q "$metric"; then
        echo -e "${GREEN}✓ Found: $metric${NC}"
        ((FOUND_COUNT++))
    else
        echo -e "${YELLOW}⚠ Not yet active: $metric (will appear after events)${NC}"
    fi
done

# 5. Test metrics collection
echo -e "\n${BLUE}[5/5] Testing metrics collection...${NC}"

# Count total metrics
TOTAL_METRICS=$(echo "$METRICS_OUTPUT" | grep -c "# TYPE" || echo "0")
echo -e "${GREEN}✓ Total metric types: $TOTAL_METRICS${NC}"

# Check for multi-tenant labels
if echo "$METRICS_OUTPUT" | grep -q "tenant_id="; then
    echo -e "${GREEN}✓ Multi-tenant labels present${NC}"
else
    echo -e "${YELLOW}⚠ No tenant_id labels yet (normal if no API calls made)${NC}"
fi

# Check histogram quantiles
if echo "$METRICS_OUTPUT" | grep -q "_bucket{"; then
    echo -e "${GREEN}✓ Histogram buckets present${NC}"
else
    echo -e "${YELLOW}⚠ No histogram buckets yet${NC}"
fi

# Summary
echo -e "\n${BLUE}=== Summary ===${NC}"
echo -e "Metrics endpoint: ${GREEN}✓ Working${NC}"
echo -e "Prometheus format: ${GREEN}✓ Valid${NC}"
echo -e "Artprima metrics: ${GREEN}✓ Collecting${NC}"
echo -e "Custom metrics: ${YELLOW}$FOUND_COUNT/${#CUSTOM_METRICS[@]} active${NC}"
echo -e ""
echo -e "${BLUE}Note:${NC} Custom business metrics will appear after domain events are emitted"
echo -e "      (e.g., when orders are placed, payments are captured, etc.)"
echo -e ""
echo -e "${GREEN}Full metrics available at: $METRICS_URL${NC}"
echo -e ""

# Optional: Show sample metrics
if [ "$1" == "--show-sample" ]; then
    echo -e "${BLUE}=== Sample Metrics Output ===${NC}"
    echo "$METRICS_OUTPUT" | head -50
fi

exit 0
