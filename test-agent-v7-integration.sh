#!/bin/bash
# ==============================================================================
# EZStream Agent v7.0 Integration Test Script
# ==============================================================================
# Test tất cả components của Agent v7.0 để đảm bảo tương thích

echo "🧪 EZStream Agent v7.0 Integration Tests"
echo "========================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Test function
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    echo -e "${BLUE}🔍 Testing: ${test_name}${NC}"
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    
    if eval "$test_command"; then
        echo -e "${GREEN}✅ PASS: ${test_name}${NC}"
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        echo -e "${RED}❌ FAIL: ${test_name}${NC}"
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
    echo ""
}

# Test 1: Agent files exist
run_test "Agent files exist" "test -f storage/app/ezstream-agent/agent.py && test -f storage/app/ezstream-agent/simple_stream_manager.py"

# Test 2: Agent version check
run_test "Agent v7.0 version" "grep -q 'EZStream Agent v7.0' storage/app/ezstream-agent/agent.py"

# Test 3: Config version check
run_test "Config v7.0 version" "grep -q '7.0-simple-ffmpeg' storage/app/ezstream-agent/config.py"

# Test 4: Provision script version
run_test "Provision script v7.0" "grep -q 'SCRIPT v7.0' storage/app/ezstream-agent/provision-vps.sh"

# Test 5: Simple stream manager exists
run_test "Simple stream manager exists" "test -f storage/app/ezstream-agent/simple_stream_manager.py"

# Test 6: PHP compatibility test
run_test "PHP compatibility test" "php test-agent-v7-compatibility.php"

# Test 7: Agent package command
run_test "Agent package command" "php artisan agent:deploy --help > /dev/null"

# Test 8: Agent info command
run_test "Agent info command" "php artisan agent:info > /dev/null"

# Test 9: Check for SRS references (should not exist)
if grep -r "SRS-Only" app/Jobs/ > /dev/null 2>&1; then
    echo -e "${YELLOW}⚠️ WARNING: Found SRS-Only references in Jobs${NC}"
    grep -r "SRS-Only" app/Jobs/ || true
    echo ""
else
    echo -e "${GREEN}✅ PASS: No SRS-Only references found in Jobs${NC}"
    TESTS_PASSED=$((TESTS_PASSED + 1))
    echo ""
fi
TESTS_TOTAL=$((TESTS_TOTAL + 1))

# Test 10: Check systemd service descriptions
if grep -r "v6.0" app/Jobs/ > /dev/null 2>&1; then
    echo -e "${YELLOW}⚠️ WARNING: Found v6.0 references in Jobs${NC}"
    grep -r "v6.0" app/Jobs/ || true
    echo ""
else
    echo -e "${GREEN}✅ PASS: No v6.0 references found in Jobs${NC}"
    TESTS_PASSED=$((TESTS_PASSED + 1))
    echo ""
fi
TESTS_TOTAL=$((TESTS_TOTAL + 1))

# Test 11: Python syntax check
run_test "Python syntax check - agent.py" "python -m py_compile storage/app/ezstream-agent/agent.py"
run_test "Python syntax check - config.py" "python -m py_compile storage/app/ezstream-agent/config.py"
run_test "Python syntax check - simple_stream_manager.py" "python -m py_compile storage/app/ezstream-agent/simple_stream_manager.py"

# Test 12: Requirements.txt check
run_test "Requirements.txt exists" "test -f storage/app/ezstream-agent/requirements.txt"

# Display final results
echo "========================================"
echo -e "${BLUE}🧪 TEST SUMMARY${NC}"
echo "========================================"
echo -e "Total Tests: ${TESTS_TOTAL}"
echo -e "${GREEN}Passed: ${TESTS_PASSED}${NC}"
echo -e "${RED}Failed: ${TESTS_FAILED}${NC}"

if [ $TESTS_FAILED -eq 0 ]; then
    echo ""
    echo -e "${GREEN}🎉 ALL TESTS PASSED!${NC}"
    echo -e "${GREEN}✅ EZStream Agent v7.0 is ready for deployment!${NC}"
    echo ""
    echo "📋 Next steps:"
    echo "1. Deploy agent: php artisan agent:deploy"
    echo "2. Update VPS: php artisan vps:bulk-update"
    echo "3. Monitor: php artisan agent:listen"
    exit 0
else
    echo ""
    echo -e "${RED}❌ TESTS FAILED!${NC}"
    echo -e "${RED}Please fix the issues above before deploying.${NC}"
    exit 1
fi
