#!/bin/bash

#############################################################################
# 2Checkout Sandbox Integration Test Script
#
# Acest script testează integrarea cu 2Checkout Sandbox API folosind
# test cards și scenarii oficiale din documentația 2Checkout.
#
# Test Cards (conform documentației oficiale):
#   VISA:       4111111111111111
#   MasterCard: 5555555555554444
#   AMEX:       378282246310005
#   Discover:   6011111111111117
#   JCB:        3566111111111113
#
# Test Cardholders:
#   John Doe  = Success scenario
#   Mona Doe  = Insufficient funds
#   Red Doe   = Stolen card
#   Mark Doe  = Try again later
#
# Documentație: https://verifone.cloud/docs/2checkout/
#############################################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "========================================"
echo "🧪 2CHECKOUT SANDBOX INTEGRATION TEST"
echo "========================================"
echo ""
echo "📂 Project root: $PROJECT_ROOT"
echo ""

# Verifică dacă directorul există
if [ ! -d "$PROJECT_ROOT" ]; then
    echo "❌ Eroare: Directorul proiect nu există: $PROJECT_ROOT"
    exit 1
fi

cd "$PROJECT_ROOT"

# Verifică dacă .env conține credentials 2Checkout
echo "🔍 Verificare credentials 2Checkout în .env..."
if grep -q "TWO_CHECKOUT_MERCHANT_CODE" .env; then
    echo "✅ TWO_CHECKOUT_MERCHANT_CODE găsit"
else
    echo "❌ TWO_CHECKOUT_MERCHANT_CODE lipsește din .env"
    echo "   Adaugă credentials din /var/www/new_ecom/.env.keys"
    exit 1
fi

if grep -q "TWO_CHECKOUT_SECRET_KEY" .env; then
    echo "✅ TWO_CHECKOUT_SECRET_KEY găsit"
else
    echo "❌ TWO_CHECKOUT_SECRET_KEY lipsește din .env"
    exit 1
fi

echo ""
echo "✅ Credentials 2Checkout configurate corect!"
echo ""

# Verifică dacă RabbitMQ rulează
echo "🔍 Verificare RabbitMQ..."
if systemctl is-active --quiet rabbitmq-server 2>/dev/null || pgrep -x rabbitmq-server > /dev/null; then
    echo "✅ RabbitMQ este activ"
else
    echo "⚠️  RabbitMQ nu rulează - mesajele async nu vor fi procesate"
    echo "   Pentru a porni RabbitMQ: sudo service rabbitmq-server start"
fi

echo ""
echo "========================================"
echo "🚀 RULARE TEST SUITE"
echo "========================================"
echo ""

# Rulează comanda de test
php bin/console payment:test-2checkout -vv

echo ""
echo "========================================"
echo "✅ TEST COMPLET!"
echo "========================================"
echo ""
echo "📊 Pentru verificări suplimentare:"
echo ""
echo "1. Verifică logs:"
echo "   tail -f var/log/dev.log | grep '2Checkout'"
echo ""
echo "2. Verifică baza de date:"
echo "   symfony console dbal:run-sql \"SELECT id, status, gateway, gateway_transaction_id, amount_in_cents FROM payments ORDER BY created_at DESC LIMIT 5;\""
echo ""
echo "3. Verifică 2Checkout Dashboard:"
echo "   https://sandbox.2checkout.com/sandbox/"
echo ""
echo "4. Verifică queue RabbitMQ (dacă rulează):"
echo "   sudo rabbitmqctl list_queues name messages"
echo ""
echo "📚 Documentație 2Checkout:"
echo "   https://verifone.cloud/docs/2checkout/"
echo ""
