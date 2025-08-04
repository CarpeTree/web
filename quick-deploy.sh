#!/bin/bash
# Quick deploy just the progress bar files to Hostinger

echo "🚀 Quick deploying progress bar files to Hostinger..."

# Hostinger SSH details
HOST="46.202.182.11"
PORT="65002"
USER="u230128646"
REMOTE_PATH="/public_html"

echo "📡 Connecting to Hostinger ($HOST:$PORT)..."

# Test connection first
echo "🔍 Testing SSH connection..."
ssh -i ~/.ssh/carpe-tree-hostinger -p "$PORT" "$USER@$HOST" "pwd" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✅ SSH connection successful"
else
    echo "❌ SSH connection failed. Check:"
    echo "   - SSH key is in ~/.ssh/carpe-tree-hostinger"
    echo "   - Key is added to Hostinger SSH access"
    echo "   - Hostinger SSH is enabled"
    exit 1
fi

# Deploy critical files
echo "📤 Uploading quote.html with progress bar..."
scp -i ~/.ssh/carpe-tree-hostinger -P "$PORT" "quote.html" "$USER@$HOST:$REMOTE_PATH/"

echo "📤 Uploading debug-simple.html for testing..."
scp -i ~/.ssh/carpe-tree-hostinger -P "$PORT" "debug-simple.html" "$USER@$HOST:$REMOTE_PATH/"

echo "🔍 Testing deployment..."
echo "✅ Deployment complete! Test these URLs:"
echo "   🧪 https://carpetree.com/debug-simple.html (should show red page)"
echo "   🎯 https://carpetree.com/quote.html (progress bar on Step 3)"

echo ""
echo "💡 To test progress bar:"
echo "   1. Go to carpetree.com/quote.html"
echo "   2. Navigate to Step 3: Contact Information"
echo "   3. Look for red/yellow debug boxes at bottom"
echo "   4. Click orange 'Test Progress Bar' button"