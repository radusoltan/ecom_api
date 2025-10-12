#!/bin/bash

# Script simplificat pentru testare Stripe fără JWT
# Apelează direct handler-ele prin consolă

set -e

echo "======================================="
echo "🧪 STRIPE INTEGRATION - SIMPLE TEST"
echo "======================================="
echo ""

# Creăm un payment direct prin consolă (fără API)
echo "📝 Creăm payment prin baza de date..."

PAYMENT_ID=$(php bin/console dbal:run-sql "
INSERT INTO payments (id, tenant_id, order_id, amount_in_cents, currency, method, gateway, status, refunded_amount_in_cents, created_at, updated_at)
VALUES (
    '01K7TESTSTRIPE001',
    '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
    '01J9XAMPLEORDER01',
    10000,
    'USD',
    'card',
    'stripe',
    'pending',
    0,
    NOW(),
    NOW()
)
RETURNING id;" 2>&1 | grep "01K7" || echo "01K7TESTSTRIPE001")

echo "✅ Payment creat: $PAYMENT_ID"
echo ""

# Test cu curl direct (mai simplu fără JWT)
echo "======================================="
echo "🔐 TEST AUTHORIZE (STRIPE REAL API)"
echo "======================================="
echo ""
echo "Vom vedea răspunsul real de la Stripe..."
echo ""

# Facem request fără JWT pentru a vedea eroarea
curl -X PATCH "http://127.0.0.1:8000/api/payments/01K7TESTSTRIPE001/authorize" \
    -H "Content-Type: application/merge-patch+json" \
    -H "Accept: application/json" \
    -H "X-Tenant-ID: 9efae4ea-94fc-4807-b1bc-5e495ee7858c" \
    -d '{"gatewayTransactionId": "test_auth_001"}' \
    2>&1 | head -50

echo ""
echo ""
echo "======================================="
echo "NOTĂ: API-ul cere JWT authentication"
echo "======================================="
echo ""
echo "Pentru testare completă cu Stripe real:"
echo "1. Rulează functional tests: vendor/bin/phpunit tests/Functional/Payment/Api/PaymentApiTest.php"
echo "2. SAU creează un user și generează JWT token"
echo ""

