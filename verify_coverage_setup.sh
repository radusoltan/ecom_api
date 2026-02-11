#!/bin/bash

echo "=============================================="
echo "Coverage Setup Verification"
echo "=============================================="
echo ""

# 1. Check Xdebug
echo "1. Xdebug Status:"
echo "----------------"
php -v | grep -i xdebug || echo "   ERROR: Xdebug not found"
echo ""

# 2. Check PHPUnit configuration
echo "2. PHPUnit Coverage Configuration:"
echo "-----------------------------------"
if grep -q "<coverage" phpunit.xml.dist; then
    echo "   OK: Coverage section configured in phpunit.xml.dist"
else
    echo "   WARNING: No coverage section in phpunit.xml.dist"
fi
echo ""

# 3. Check test directories
echo "3. Test Structure:"
echo "------------------"
echo "   Unit tests:        $(find tests/Unit -name '*Test.php' | wc -l) files"
echo "   Integration tests: $(find tests/Integration -name '*Test.php' | wc -l) files"
echo "   Functional tests:  $(find tests/Functional -name '*Test.php' | wc -l) files"
echo ""

# 4. Quick coverage check (unit tests only)
echo "4. Quick Coverage Check (Unit Tests):"
echo "-------------------------------------"
XDEBUG_MODE=coverage php -d memory_limit=512M vendor/bin/phpunit tests/Unit --coverage-text --colors=never 2>&1 | grep -E "^\s+(Classes|Methods|Lines):" | head -3
echo ""

# 5. Check if HTML report exists
echo "5. HTML Report Status:"
echo "----------------------"
if [ -d "coverage" ]; then
    echo "   OK: Coverage directory exists"
    echo "   Files: $(ls -1 coverage/ | wc -l) generated"
    echo "   Size:  $(du -sh coverage/ | cut -f1)"
    echo "   Open:  wslview coverage/index.html"
else
    echo "   INFO: No coverage directory (run coverage first)"
fi
echo ""

# 6. Summary
echo "6. Summary:"
echo "-----------"
if [ -f "COVERAGE_REPORT.md" ]; then
    echo "   OK: COVERAGE_REPORT.md generated"
fi
if [ -f "COVERAGE_QUICK_REFERENCE.md" ]; then
    echo "   OK: COVERAGE_QUICK_REFERENCE.md generated"
fi
echo ""

echo "=============================================="
echo "Next Steps:"
echo "=============================================="
echo "1. Run full coverage:"
echo "   XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage"
echo ""
echo "2. View HTML report:"
echo "   wslview coverage/index.html"
echo ""
echo "3. Read detailed report:"
echo "   cat COVERAGE_REPORT.md"
echo ""
echo "=============================================="

