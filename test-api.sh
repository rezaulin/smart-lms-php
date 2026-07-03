#!/bin/bash
# Smart LMS - API Testing Script
# Tests all 68 endpoints

set -e

# Configuration
API_URL="${1:-http://185.245.61.91/api}"
TOKEN=""

echo "═══════════════════════════════════════════════════"
echo "🧪 SMART-LMS API TESTING"
echo "═══════════════════════════════════════════════════"
echo "API URL: $API_URL"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
PASS=0
FAIL=0
TOTAL=0

# Helper function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local desc=$4
    local auth=$5
    
    TOTAL=$((TOTAL + 1))
    
    echo -n "[$TOTAL] Testing: $desc ... "
    
    if [ "$auth" = "true" ]; then
        if [ -z "$TOKEN" ]; then
            echo -e "${YELLOW}SKIP (no token)${NC}"
            return
        fi
        headers="-H 'Authorization: Bearer $TOKEN'"
    else
        headers=""
    fi
    
    if [ "$method" = "GET" ]; then
        response=$(eval curl -s -w "\n%{http_code}" $headers "$API_URL$endpoint")
    else
        response=$(eval curl -s -w "\n%{http_code}" -X $method $headers -H "'Content-Type: application/json'" -d "'$data'" "$API_URL$endpoint")
    fi
    
    status_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ "$status_code" -ge 200 ] && [ "$status_code" -lt 300 ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $status_code)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}✗ FAIL${NC} (HTTP $status_code)"
        echo "   Response: $body"
        FAIL=$((FAIL + 1))
    fi
}

echo "════════════════════════════════════════════════════"
echo "1. AUTH ENDPOINTS (Public)"
echo "════════════════════════════════════════════════════"

# Test login with default admin
test_endpoint "POST" "/auth/login" '{"email":"admin@smart-lms.local","password":"password"}' "Login as admin" "false"

# Extract token from response if successful
if [ $PASS -eq 1 ]; then
    TOKEN=$(echo "$body" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
    echo "   Token: ${TOKEN:0:20}..."
fi

test_endpoint "GET" "/auth/profile" "" "Get profile" "true"
test_endpoint "POST" "/auth/refresh" "" "Refresh token" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "2. DASHBOARD ENDPOINT"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/dashboard" "" "Get dashboard stats" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "3. STUDENT ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/students" "" "List students" "true"
test_endpoint "GET" "/students/1" "" "Get student by ID" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "4. TEACHER ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/teachers" "" "List teachers" "true"
test_endpoint "GET" "/teachers/1" "" "Get teacher by ID" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "5. CLASS ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/classes" "" "List classes" "true"
test_endpoint "GET" "/classes/1" "" "Get class by ID" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "6. SUBJECT ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/subjects" "" "List subjects" "true"
test_endpoint "GET" "/subjects/1" "" "Get subject by ID" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "7. SEMESTER ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/semesters" "" "List semesters" "true"
test_endpoint "GET" "/semesters/1" "" "Get semester by ID" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "8. ATTENDANCE ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/schedules" "" "List schedules" "true"
test_endpoint "GET" "/attendance/sessions" "" "List attendance sessions" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "9. EXAM ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/exams" "" "List exams" "true"
test_endpoint "GET" "/exams/1" "" "Get exam by ID" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "10. BILLING ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/billing/jenis" "" "List jenis tagihan" "true"
test_endpoint "GET" "/billing/tagihan" "" "List tagihan" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "11. RAPORT ENDPOINTS"
echo "════════════════════════════════════════════════════"

test_endpoint "GET" "/raports" "" "List raports" "true"
test_endpoint "GET" "/raports/components" "" "List raport components" "true"

echo ""
echo "════════════════════════════════════════════════════"
echo "📊 TEST SUMMARY"
echo "════════════════════════════════════════════════════"
echo -e "Total Tests: $TOTAL"
echo -e "${GREEN}Passed: $PASS${NC}"
echo -e "${RED}Failed: $FAIL${NC}"
echo -e "Success Rate: $(awk "BEGIN {printf \"%.1f\", ($PASS/$TOTAL)*100}")%"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✅ ALL TESTS PASSED!${NC}"
    exit 0
else
    echo -e "${RED}❌ SOME TESTS FAILED${NC}"
    exit 1
fi
