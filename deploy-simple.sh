#!/bin/bash
# Simple deployment using password authentication (no SSH key needed)

echo "🚀 Deploying progress bar files to Hostinger..."
echo "💡 You'll need to enter your password a few times"

HOST="46.202.182.11"
PORT="65002"
USER="u230128646"

# First, let's find the correct website directory
echo "🔍 Finding website directory..."
ssh -p "$PORT" "$USER@$HOST" "find domains -name '*.html' | head -5"

echo ""
echo "📤 Uploading quote.html..."
scp -P "$PORT" "quote.html" "$USER@$HOST:domains/carpetree.com/public_html/"

echo "📤 Uploading debug-simple.html..."
scp -P "$PORT" "debug-simple.html" "$USER@$HOST:domains/carpetree.com/public_html/"

echo ""
echo "✅ Upload complete! Test these URLs:"
echo "   🧪 https://carpetree.com/debug-simple.html"
echo "   🎯 https://carpetree.com/quote.html (Step 3 for progress bar)"