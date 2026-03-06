#!/bin/bash

# Multi-Tab Rate Limiting Test Script
# Opens multiple browser tabs and simulates concurrent refresh attempts

echo "🧪 JWT Token Refresh Rate Limiting Test"
echo "========================================"
echo ""

# Configuration
URL="http://localhost:5678/horde/test-token-rate-limiting.html"
NUM_TABS=4

echo "Test URL: $URL"
echo "Number of tabs: $NUM_TABS"
echo ""

# Check if URL is accessible
if ! curl -s -o /dev/null -w "%{http_code}" "$URL" | grep -q "200\|302"; then
    echo "❌ Error: Cannot access $URL"
    echo "   Make sure Horde is running on localhost:5678"
    exit 1
fi

echo "✓ URL is accessible"
echo ""

# Detect browser
if command -v firefox &> /dev/null; then
    BROWSER="firefox"
elif command -v google-chrome &> /dev/null; then
    BROWSER="google-chrome"
elif command -v chromium &> /dev/null; then
    BROWSER="chromium"
else
    echo "❌ Error: No supported browser found (firefox, chrome, chromium)"
    exit 1
fi

echo "Using browser: $BROWSER"
echo ""

# Open tabs
echo "Opening $NUM_TABS tabs..."
for i in $(seq 1 $NUM_TABS); do
    if [ "$BROWSER" = "firefox" ]; then
        firefox --new-tab "$URL" &
    else
        $BROWSER --new-tab "$URL" &
    fi
    echo "  Tab $i opened"
    sleep 0.5
done

echo ""
echo "✅ All tabs opened!"
echo ""
echo "📋 Manual Test Steps:"
echo "1. In each tab, click 'Force Refresh Token' simultaneously"
echo "2. Check the logs - only ONE tab should make actual API call"
echo "3. Other tabs should show 'Refresh already in progress' or 'Too soon'"
echo ""
echo "Or click 'Simulate Multi-Tab Refresh (x5)' in any tab to automate."
echo ""
echo "Expected Results:"
echo "  ✓ Only 1 refresh API call made"
echo "  ✓ Other tabs skip due to rate limiting"
echo "  ✓ All tabs end up with valid token"
