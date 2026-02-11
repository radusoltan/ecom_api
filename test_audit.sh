#!/bin/bash
# Comprehensive Test Coverage Audit Script
# Date: 2025-12-05

echo "=================================================="
echo "TEST COVERAGE AUDIT - E-Commerce Platform"
echo "=================================================="
echo ""

cd /var/www/new_ecom/backend

# 1. Count tests by type
echo "1. TEST COUNTS BY TYPE"
echo "----------------------"
total_tests=$(find tests -type f -name "*Test.php" 2>/dev/null | wc -l)
unit_tests=$(find tests/Unit -type f -name "*Test.php" 2>/dev/null | wc -l)
integration_tests=$(find tests/Integration -type f -name "*Test.php" 2>/dev/null | wc -l)
functional_tests=$(find tests/Functional -type f -name "*Test.php" 2>/dev/null | wc -l)

echo "Total test files: $total_tests"
echo "Unit tests: $unit_tests"
echo "Integration tests: $integration_tests"
echo "Functional tests: $functional_tests"
echo ""

# 2. Count tests by bounded context
echo "2. TEST COUNTS BY BOUNDED CONTEXT"
echo "----------------------------------"

contexts=(
    "Catalog"
    "Order"
    "Inventory"
    "Pricing"
    "Customer"
    "Payment"
    "Tax"
    "Returns"
    "Tenant"
    "Notification"
    "Internationalization"
    "User"
    "Cart"
    "AuditLog"
    "Shared"
)

for context in "${contexts[@]}"; do
    count=$(find tests -type f -path "*/$context/*Test.php" 2>/dev/null | wc -l)
    if [ $count -gt 0 ]; then
        echo "$context: $count tests"

        # Breakdown by test type
        unit=$(find tests/Unit -type f -path "*/$context/*Test.php" 2>/dev/null | wc -l)
        integration=$(find tests/Integration -type f -path "*/$context/*Test.php" 2>/dev/null | wc -l)
        functional=$(find tests/Functional -type f -path "*/$context/*Test.php" 2>/dev/null | wc -l)

        echo "  - Unit: $unit, Integration: $integration, Functional: $functional"
    fi
done
echo ""

# 3. Find contexts with no tests
echo "3. CONTEXTS WITH NO/FEW TESTS"
echo "------------------------------"
for context in "${contexts[@]}"; do
    count=$(find tests -type f -path "*/$context/*Test.php" 2>/dev/null | wc -l)
    if [ $count -eq 0 ]; then
        echo "❌ $context: NO TESTS"
    elif [ $count -lt 3 ]; then
        echo "⚠️  $context: ONLY $count tests (low coverage)"
    fi
done
echo ""

# 4. Check for domain models without tests
echo "4. DOMAIN MODELS WITHOUT TESTS"
echo "-------------------------------"
for context_dir in src/*/Domain/Model; do
    if [ -d "$context_dir" ]; then
        context=$(echo $context_dir | cut -d'/' -f2)

        # Find all .php files that look like domain models (not interfaces, not traits)
        models=$(find "$context_dir" -type f -name "*.php" ! -name "*Interface.php" ! -name "*Trait.php" 2>/dev/null)

        if [ -n "$models" ]; then
            while IFS= read -r model; do
                model_name=$(basename "$model" .php)
                test_file="tests/Unit/$context/Domain/Model/${model_name}Test.php"

                if [ ! -f "$test_file" ]; then
                    echo "❌ $context/$model_name - NO TEST"
                fi
            done <<< "$models"
        fi
    fi
done
echo ""

# 5. Check for API endpoints without tests
echo "5. API ENDPOINTS (ENTITIES) WITHOUT FUNCTIONAL TESTS"
echo "-----------------------------------------------------"
for entity_file in src/*/Infrastructure/Persistence/Doctrine/Entity/*Entity.php; do
    if [ -f "$entity_file" ]; then
        context=$(echo $entity_file | cut -d'/' -f2)
        entity_name=$(basename "$entity_file" Entity.php)

        # Check if there's a functional API test
        api_test="tests/Functional/$context/Api/${entity_name}ApiTest.php"
        api_test2="tests/Functional/Api/${entity_name}ApiTest.php"

        if [ ! -f "$api_test" ] && [ ! -f "$api_test2" ]; then
            echo "❌ $context/$entity_name - NO API TEST"
        fi
    fi
done
echo ""

# 6. Test files with very few tests (potential quality issue)
echo "6. TEST FILES WITH LOW TEST COUNTS"
echo "-----------------------------------"
for test_file in tests/**/*Test.php; do
    if [ -f "$test_file" ]; then
        # Count test methods (lines starting with "public function test")
        test_count=$(grep -c "public function test" "$test_file" 2>/dev/null || echo "0")

        if [ "$test_count" -eq 1 ] || [ "$test_count" -eq 2 ]; then
            echo "⚠️  $(basename $test_file): only $test_count test(s)"
        fi
    fi
done
echo ""

# 7. Check for TenantTestTrait usage in Integration/Functional tests
echo "7. MULTI-TENANCY TEST COMPLIANCE"
echo "---------------------------------"
echo "Integration tests:"
for test_file in tests/Integration/**/*Test.php; do
    if [ -f "$test_file" ]; then
        if ! grep -q "use TenantTestTrait" "$test_file" 2>/dev/null; then
            echo "⚠️  $(basename $test_file) - Missing TenantTestTrait"
        fi
    fi
done

echo ""
echo "Functional tests:"
for test_file in tests/Functional/**/*Test.php; do
    if [ -f "$test_file" ]; then
        if ! grep -q "use TenantTestTrait" "$test_file" 2>/dev/null; then
            echo "⚠️  $(basename $test_file) - Missing TenantTestTrait"
        fi
    fi
done
echo ""

# 8. Contexts by source file count vs test file count
echo "8. SOURCE vs TEST FILE RATIOS"
echo "------------------------------"
for context in "${contexts[@]}"; do
    src_count=$(find src/$context -type f -name "*.php" 2>/dev/null | wc -l)
    test_count=$(find tests -type f -path "*/$context/*Test.php" 2>/dev/null | wc -l)

    if [ $src_count -gt 0 ]; then
        ratio=$(echo "scale=2; $test_count / $src_count" | bc 2>/dev/null || echo "0")

        if (( $(echo "$ratio < 0.5" | bc -l) )); then
            status="⚠️  LOW"
        elif (( $(echo "$ratio < 1.0" | bc -l) )); then
            status="✅ OK"
        else
            status="✅ GOOD"
        fi

        echo "$context: $test_count tests / $src_count source files = ${ratio}:1 $status"
    fi
done
echo ""

echo "=================================================="
echo "AUDIT COMPLETE"
echo "=================================================="
