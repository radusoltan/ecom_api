#!/bin/bash

# Script de testare manuală pentru integrarea Stripe
# Testează toate operațiile: CREATE → AUTHORIZE → CAPTURE → REFUND și CANCEL
# Folosește API-ul real Stripe (test mode)

set -e  # Exit on error

BASE_URL="http://127.0.0.1:8000"
TENANT_ID="9efae4ea-94fc-4807-b1bc-5e495ee7858c"  # Tenant existent din fixtures

echo "======================================"
echo "🧪 STRIPE INTEGRATION TEST SUITE"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verifică dacă serverul rulează
echo "📡 Verificăm dacă serverul Symfony rulează..."
if ! curl -s "$BASE_URL/api" > /dev/null 2>&1; then
    echo -e "${RED}❌ Serverul nu rulează pe $BASE_URL${NC}"
    echo "Pornește serverul cu: symfony server:start -d"
    exit 1
fi
echo -e "${GREEN}✅ Serverul rulează${NC}"
echo ""

# Funcție pentru afișare JSON formatat
print_json() {
    if command -v jq &> /dev/null; then
        echo "$1" | jq '.'
    else
        echo "$1" | python3 -m json.tool 2>/dev/null || echo "$1"
    fi
}

echo "======================================"
echo "📝 TEST 1: CREATE PAYMENT"
echo "======================================"
echo "Creăm un payment nou (fără apel Stripe)..."
echo ""

CREATE_RESPONSE=$(curl -s -X POST "$BASE_URL/api/payments" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "X-Tenant-ID: $TENANT_ID" \
    -d '{
        "orderId": "01J9XAMPLE123456789",
        "amountInCents": 10000,
        "currency": "USD",
        "method": "card",
        "gateway": "stripe"
    }')

echo "📥 Răspuns de la server:"
print_json "$CREATE_RESPONSE"
echo ""

# Extragem payment ID
PAYMENT_ID=$(echo "$CREATE_RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

if [ -z "$PAYMENT_ID" ]; then
    echo -e "${RED}❌ Nu am putut extrage Payment ID din răspuns${NC}"
    echo "Răspuns complet:"
    echo "$CREATE_RESPONSE"
    exit 1
fi

echo -e "${GREEN}✅ Payment creat cu succes!${NC}"
echo "🆔 Payment ID: $PAYMENT_ID"
echo ""

sleep 1

echo "======================================"
echo "🔐 TEST 2: AUTHORIZE PAYMENT (STRIPE)"
echo "======================================"
echo "Autorizăm plata prin Stripe API..."
echo ""
echo "⚠️  Această operație face un apel REAL la Stripe!"
echo ""

AUTHORIZE_RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/payments/$PAYMENT_ID/authorize" \
    -H "Content-Type: application/merge-patch+json" \
    -H "Accept: application/json" \
    -H "X-Tenant-ID: $TENANT_ID" \
    -d '{
        "gatewayTransactionId": "dummy_for_testing"
    }')

echo "📥 Răspuns de la Stripe (via backend):"
print_json "$AUTHORIZE_RESPONSE"
echo ""

# Verificăm dacă autorizarea a reușit
if echo "$AUTHORIZE_RESPONSE" | grep -q '"status":"authorized"'; then
    echo -e "${GREEN}✅ Payment autorizat cu succes prin Stripe!${NC}"

    # Extragem transaction ID de la Stripe
    STRIPE_TRANSACTION_ID=$(echo "$AUTHORIZE_RESPONSE" | grep -o '"gatewayTransactionId":"[^"]*"' | cut -d'"' -f4)
    echo "🎫 Stripe Transaction ID: $STRIPE_TRANSACTION_ID"
elif echo "$AUTHORIZE_RESPONSE" | grep -q '"status":500'; then
    echo -e "${RED}❌ Eroare la autorizare!${NC}"
    echo "Posibile cauze:"
    echo "  1. Cheile Stripe din .env.local nu sunt valide"
    echo "  2. API-ul Stripe nu este accesibil"
    echo "  3. Eroare de configurare"
    echo ""
    echo "Detalii eroare:"
    print_json "$AUTHORIZE_RESPONSE"
    exit 1
else
    echo -e "${YELLOW}⚠️  Autorizare parțial reușită sau status neașteptat${NC}"
fi
echo ""

sleep 1

echo "======================================"
echo "💰 TEST 3: CAPTURE PAYMENT (STRIPE)"
echo "======================================"
echo "Capturăm plata autorizată..."
echo ""

CAPTURE_RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/payments/$PAYMENT_ID/capture" \
    -H "Content-Type: application/merge-patch+json" \
    -H "Accept: application/json" \
    -H "X-Tenant-ID: $TENANT_ID" \
    -d '{}')

echo "📥 Răspuns de la Stripe (via backend):"
print_json "$CAPTURE_RESPONSE"
echo ""

if echo "$CAPTURE_RESPONSE" | grep -q '"status":"captured"'; then
    echo -e "${GREEN}✅ Payment capturat cu succes prin Stripe!${NC}"
elif echo "$CAPTURE_RESPONSE" | grep -q '"status":500'; then
    echo -e "${RED}❌ Eroare la capturare!${NC}"
    echo "Detalii:"
    print_json "$CAPTURE_RESPONSE"

    # Continuăm totuși pentru a testa și cancel
    echo ""
    echo "Vom testa CANCEL în loc de REFUND..."
else
    echo -e "${YELLOW}⚠️  Capturare parțial reușită sau status neașteptat${NC}"
fi
echo ""

sleep 1

echo "======================================"
echo "💸 TEST 4: REFUND PAYMENT (STRIPE)"
echo "======================================"
echo "Refundăm parțial plata capturată..."
echo ""

REFUND_RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/payments/$PAYMENT_ID/refund" \
    -H "Content-Type: application/merge-patch+json" \
    -H "Accept: application/json" \
    -H "X-Tenant-ID: $TENANT_ID" \
    -d '{
        "refundedAmountInCents": 5000,
        "errorMessage": "Customer requested refund - test"
    }')

echo "📥 Răspuns de la Stripe (via backend):"
print_json "$REFUND_RESPONSE"
echo ""

if echo "$REFUND_RESPONSE" | grep -q '"status":"refunded"'; then
    echo -e "${GREEN}✅ Payment refundat cu succes prin Stripe!${NC}"
    echo "💵 Sumă refundată: 50.00 USD (5000 cents)"
elif echo "$REFUND_RESPONSE" | grep -q '"status":500'; then
    echo -e "${RED}❌ Eroare la refund!${NC}"
    echo "Detalii:"
    print_json "$REFUND_RESPONSE"
else
    echo -e "${YELLOW}⚠️  Refund parțial reușit sau status neașteptat${NC}"
fi
echo ""

sleep 1

echo "======================================"
echo "🧪 TEST 5: CANCEL PAYMENT (STRIPE)"
echo "======================================"
echo "Creăm un payment nou pentru test cancel..."
echo ""

# Creăm alt payment pentru cancel test
CREATE_RESPONSE_2=$(curl -s -X POST "$BASE_URL/api/payments" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "X-Tenant-ID: $TENANT_ID" \
    -d '{
        "orderId": "01J9XAMPLE123456790",
        "amountInCents": 2500,
        "currency": "USD",
        "method": "card",
        "gateway": "stripe"
    }')

PAYMENT_ID_2=$(echo "$CREATE_RESPONSE_2" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

if [ -n "$PAYMENT_ID_2" ]; then
    echo "🆔 Payment ID pentru cancel: $PAYMENT_ID_2"
    echo ""

    # Autorizăm
    echo "Autorizăm payment-ul..."
    curl -s -X PATCH "$BASE_URL/api/payments/$PAYMENT_ID_2/authorize" \
        -H "Content-Type: application/merge-patch+json" \
        -H "X-Tenant-ID: $TENANT_ID" \
        -d '{"gatewayTransactionId": "dummy_2"}' > /dev/null

    sleep 1

    # Anulăm
    echo "Anulăm payment-ul autorizat..."
    CANCEL_RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/payments/$PAYMENT_ID_2/cancel" \
        -H "Content-Type: application/merge-patch+json" \
        -H "Accept: application/json" \
        -H "X-Tenant-ID: $TENANT_ID" \
        -d '{
            "errorMessage": "Customer cancelled order - test"
        }')

    echo "📥 Răspuns de la Stripe (via backend):"
    print_json "$CANCEL_RESPONSE"
    echo ""

    if echo "$CANCEL_RESPONSE" | grep -q '"status":"cancelled"'; then
        echo -e "${GREEN}✅ Payment anulat cu succes prin Stripe!${NC}"
    elif echo "$CANCEL_RESPONSE" | grep -q '"status":500'; then
        echo -e "${RED}❌ Eroare la anulare!${NC}"
        echo "Detalii:"
        print_json "$CANCEL_RESPONSE"
    else
        echo -e "${YELLOW}⚠️  Anulare parțial reușită sau status neașteptat${NC}"
    fi
else
    echo -e "${RED}❌ Nu am putut crea al doilea payment pentru test cancel${NC}"
fi
echo ""

echo "======================================"
echo "📊 REZUMAT FINAL"
echo "======================================"
echo ""
echo "Payment ID principal: $PAYMENT_ID"
echo "Payment ID pentru cancel: $PAYMENT_ID_2"
echo ""
echo "Poți verifica în Stripe Dashboard:"
echo "https://dashboard.stripe.com/test/payments"
echo ""
echo "Sau verifică în baza de date:"
echo "  SELECT id, status, gateway_transaction_id, amount_in_cents, refunded_amount_in_cents"
echo "  FROM payments"
echo "  WHERE id IN ('$PAYMENT_ID', '$PAYMENT_ID_2');"
echo ""
echo -e "${GREEN}✅ Test suite complet executat!${NC}"
echo ""
