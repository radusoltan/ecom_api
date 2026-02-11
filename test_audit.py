#!/usr/bin/env python3
"""
Comprehensive Test Coverage Audit for E-Commerce Platform
Analyzes test coverage by bounded context and test type
"""

import os
import re
from pathlib import Path
from collections import defaultdict
import json

# Base directories
BASE_DIR = Path("/var/www/new_ecom/backend")
SRC_DIR = BASE_DIR / "src"
TEST_DIR = BASE_DIR / "tests"

# Bounded contexts (from CLAUDE.md)
CONTEXTS = [
    "Tenant", "Catalog", "Order", "Inventory", "Pricing",
    "Customer", "Payment", "Tax", "Returns", "Notification",
    "Internationalization", "User", "Cart", "AuditLog", "Shared"
]

def count_files(directory, pattern="*.php"):
    """Count files matching pattern in directory"""
    if not directory.exists():
        return 0
    return len(list(directory.rglob(pattern)))

def count_test_methods(test_file):
    """Count test methods in a test file"""
    if not test_file.exists():
        return 0

    content = test_file.read_text()
    # Count "public function test" patterns
    return len(re.findall(r'public function test\w+', content))

def has_tenant_trait(test_file):
    """Check if test file uses TenantTestTrait"""
    if not test_file.exists():
        return False

    content = test_file.read_text()
    return "use TenantTestTrait" in content or "TenantTestTrait;" in content

def get_domain_models(context):
    """Get list of domain model files for a context"""
    models_dir = SRC_DIR / context / "Domain" / "Model"
    if not models_dir.exists():
        return []

    models = []
    for php_file in models_dir.glob("*.php"):
        # Skip interfaces and traits
        if "Interface" not in php_file.name and "Trait" not in php_file.name:
            models.append(php_file.stem)

    return models

def analyze_context_coverage(context):
    """Analyze test coverage for a specific context"""
    data = {
        "context": context,
        "source_files": 0,
        "test_files": {
            "unit": 0,
            "integration": 0,
            "functional": 0,
            "total": 0
        },
        "test_methods": 0,
        "domain_models": {
            "total": 0,
            "tested": 0,
            "untested": []
        },
        "missing_tenant_trait": []
    }

    # Count source files
    context_src = SRC_DIR / context
    if context_src.exists():
        data["source_files"] = count_files(context_src)

    # Count test files by type
    for test_type in ["Unit", "Integration", "Functional"]:
        test_path = TEST_DIR / test_type / context
        if test_path.exists():
            test_files = list(test_path.rglob("*Test.php"))
            count = len(test_files)
            data["test_files"][test_type.lower()] = count
            data["test_files"]["total"] += count

            # Count test methods
            for test_file in test_files:
                data["test_methods"] += count_test_methods(test_file)

            # Check TenantTestTrait for Integration/Functional
            if test_type in ["Integration", "Functional"]:
                for test_file in test_files:
                    if not has_tenant_trait(test_file):
                        data["missing_tenant_trait"].append(str(test_file.relative_to(BASE_DIR)))

    # Check domain model coverage
    domain_models = get_domain_models(context)
    data["domain_models"]["total"] = len(domain_models)

    for model in domain_models:
        test_file = TEST_DIR / "Unit" / context / "Domain" / "Model" / f"{model}Test.php"
        if test_file.exists():
            data["domain_models"]["tested"] += 1
        else:
            data["domain_models"]["untested"].append(model)

    return data

def generate_report():
    """Generate comprehensive test coverage report"""
    print("=" * 80)
    print("TEST COVERAGE AUDIT - E-Commerce Platform")
    print("Date: 2025-12-05")
    print("=" * 80)
    print()

    # Overall statistics
    total_test_files = count_files(TEST_DIR, "*Test.php")
    unit_tests = count_files(TEST_DIR / "Unit", "*Test.php")
    integration_tests = count_files(TEST_DIR / "Integration", "*Test.php")
    functional_tests = count_files(TEST_DIR / "Functional", "*Test.php")

    print("1. OVERALL TEST STATISTICS")
    print("-" * 80)
    print(f"Total test files: {total_test_files}")
    print(f"  - Unit tests: {unit_tests} ({unit_tests/total_test_files*100:.1f}%)")
    print(f"  - Integration tests: {integration_tests} ({integration_tests/total_test_files*100:.1f}%)")
    print(f"  - Functional tests: {functional_tests} ({functional_tests/total_test_files*100:.1f}%)")
    print()

    # Test pyramid assessment
    print("Test Pyramid Assessment (PRD Appendix E):")
    if unit_tests > integration_tests > functional_tests:
        print("  ✅ GOOD - Follows test pyramid (Unit > Integration > Functional)")
    else:
        print("  ⚠️  WARNING - Does not follow test pyramid pattern")
    print()

    # Context-by-context analysis
    print("2. COVERAGE BY BOUNDED CONTEXT")
    print("-" * 80)

    all_contexts_data = []
    for context in CONTEXTS:
        data = analyze_context_coverage(context)
        all_contexts_data.append(data)

        if data["test_files"]["total"] > 0 or data["source_files"] > 0:
            ratio = data["test_files"]["total"] / data["source_files"] if data["source_files"] > 0 else 0

            print(f"\n{context}:")
            print(f"  Source files: {data['source_files']}")
            print(f"  Test files: {data['test_files']['total']} (Unit: {data['test_files']['unit']}, "
                  f"Integration: {data['test_files']['integration']}, Functional: {data['test_files']['functional']})")
            print(f"  Test methods: {data['test_methods']}")
            print(f"  Test ratio: {ratio:.2f}:1 ", end="")

            if ratio >= 1.0:
                print("✅ EXCELLENT")
            elif ratio >= 0.5:
                print("✅ GOOD")
            elif ratio >= 0.3:
                print("⚠️  MODERATE")
            else:
                print("❌ LOW")

            # Domain model coverage
            if data["domain_models"]["total"] > 0:
                coverage_pct = (data["domain_models"]["tested"] / data["domain_models"]["total"]) * 100
                print(f"  Domain models: {data['domain_models']['tested']}/{data['domain_models']['total']} "
                      f"tested ({coverage_pct:.0f}%)")

                if data["domain_models"]["untested"]:
                    print(f"    ⚠️  Untested models: {', '.join(data['domain_models']['untested'][:5])}", end="")
                    if len(data['domain_models']['untested']) > 5:
                        print(f" ... +{len(data['domain_models']['untested'])-5} more")
                    else:
                        print()

    print("\n")

    # Contexts with no tests
    print("3. CONTEXTS WITH MISSING/LOW TEST COVERAGE")
    print("-" * 80)
    for data in all_contexts_data:
        if data["test_files"]["total"] == 0 and data["source_files"] > 0:
            print(f"❌ {data['context']}: NO TESTS ({data['source_files']} source files)")
        elif data["test_files"]["total"] < 3 and data["source_files"] > 10:
            print(f"⚠️  {data['context']}: Only {data['test_files']['total']} tests for {data['source_files']} source files")
    print()

    # Multi-tenancy compliance
    print("4. MULTI-TENANCY TEST COMPLIANCE")
    print("-" * 80)
    missing_trait_count = sum(len(d["missing_tenant_trait"]) for d in all_contexts_data)
    if missing_trait_count > 0:
        print(f"⚠️  {missing_trait_count} Integration/Functional tests missing TenantTestTrait:")
        for data in all_contexts_data:
            if data["missing_tenant_trait"]:
                for test_file in data["missing_tenant_trait"][:3]:
                    print(f"    - {test_file}")
    else:
        print("✅ All Integration/Functional tests use TenantTestTrait")
    print()

    # Critical paths assessment
    print("5. CRITICAL PATHS COVERAGE")
    print("-" * 80)
    critical_paths = {
        "Order Placement": ["Order", "Pricing", "Inventory"],
        "Payment Processing": ["Payment", "Order"],
        "Stock Allocation": ["Inventory", "Order"],
        "Returns Workflow": ["Returns", "Order", "Inventory"]
    }

    for path_name, required_contexts in critical_paths.items():
        total_tests = sum(
            next((d["test_files"]["total"] for d in all_contexts_data if d["context"] == ctx), 0)
            for ctx in required_contexts
        )

        status = "✅ GOOD" if total_tests > 30 else "⚠️  LOW" if total_tests > 10 else "❌ CRITICAL"
        print(f"{path_name}: {total_tests} tests {status}")
        print(f"  Contexts: {', '.join(required_contexts)}")
    print()

    # Overall health score
    print("6. OVERALL TEST HEALTH SCORE")
    print("-" * 80)

    # Calculate score components
    test_pyramid_score = 25 if unit_tests > integration_tests > functional_tests else 10

    contexts_with_tests = sum(1 for d in all_contexts_data if d["test_files"]["total"] > 0)
    coverage_breadth_score = (contexts_with_tests / len(CONTEXTS)) * 25

    avg_ratio = sum(
        d["test_files"]["total"] / d["source_files"] if d["source_files"] > 0 else 0
        for d in all_contexts_data
    ) / len([d for d in all_contexts_data if d["source_files"] > 0])
    test_density_score = min(avg_ratio * 25, 25)

    tenant_compliance = 25 if missing_trait_count == 0 else max(0, 25 - missing_trait_count * 2)

    total_score = test_pyramid_score + coverage_breadth_score + test_density_score + tenant_compliance

    print(f"Test Pyramid: {test_pyramid_score}/25")
    print(f"Coverage Breadth: {coverage_breadth_score:.1f}/25")
    print(f"Test Density: {test_density_score:.1f}/25")
    print(f"Multi-Tenant Compliance: {tenant_compliance}/25")
    print()
    print(f"TOTAL SCORE: {total_score:.0f}/100")

    if total_score >= 80:
        grade = "✅ EXCELLENT (PRD Target Met)"
    elif total_score >= 70:
        grade = "✅ GOOD"
    elif total_score >= 60:
        grade = "⚠️  MODERATE (Below PRD Target)"
    else:
        grade = "❌ NEEDS IMPROVEMENT"

    print(f"GRADE: {grade}")
    print()

    # Recommendations
    print("7. RECOMMENDATIONS")
    print("-" * 80)

    recommendations = []

    # Priority recommendations based on gaps
    no_test_contexts = [d["context"] for d in all_contexts_data
                        if d["test_files"]["total"] == 0 and d["source_files"] > 0]
    if no_test_contexts:
        recommendations.append(
            f"P0 (CRITICAL): Add tests for contexts with ZERO coverage: {', '.join(no_test_contexts)}"
        )

    low_coverage = [d["context"] for d in all_contexts_data
                    if d["source_files"] > 10 and d["test_files"]["total"] < 5]
    if low_coverage:
        recommendations.append(
            f"P1 (HIGH): Increase test coverage for: {', '.join(low_coverage)}"
        )

    untested_models = [(d["context"], d["domain_models"]["untested"])
                       for d in all_contexts_data if d["domain_models"]["untested"]]
    if untested_models:
        recommendations.append(
            f"P1 (HIGH): Add domain model tests for {sum(len(m[1]) for m in untested_models)} untested models"
        )

    if missing_trait_count > 0:
        recommendations.append(
            f"P2 (MEDIUM): Fix multi-tenancy compliance - add TenantTestTrait to {missing_trait_count} tests"
        )

    if not (unit_tests > integration_tests > functional_tests):
        recommendations.append(
            "P2 (MEDIUM): Rebalance test pyramid - add more unit tests relative to integration/functional"
        )

    if total_score < 80:
        missing_points = 80 - total_score
        recommendations.append(
            f"P1 (HIGH): Add ~{int(missing_points * 2)} more tests to reach PRD target of 80% coverage"
        )

    for i, rec in enumerate(recommendations, 1):
        print(f"{i}. {rec}")

    if not recommendations:
        print("✅ No critical recommendations - test coverage meets PRD requirements!")

    print()
    print("=" * 80)
    print("AUDIT COMPLETE")
    print("=" * 80)

if __name__ == "__main__":
    generate_report()
