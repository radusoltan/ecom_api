#!/bin/bash

echo "Running Stock Allocation Tests..."
echo "================================="
echo ""

echo "1. Running StartFulfillmentHandler Unit Tests..."
php vendor/bin/phpunit tests/Unit/Order/Application/Command/StartFulfillmentHandlerTest.php --colors=never
echo ""

echo "2. Running OrderPlacedFulfillmentSubscriber Unit Tests..."
php vendor/bin/phpunit tests/Unit/Order/Application/EventSubscriber/OrderPlacedFulfillmentSubscriberTest.php --colors=never
echo ""

echo "3. Running Stock Allocation Flow Integration Tests..."
php vendor/bin/phpunit tests/Integration/Order/StockAllocationFlowTest.php --colors=never
echo ""

echo "================================="
echo "All tests completed!"
