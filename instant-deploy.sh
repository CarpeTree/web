#!/bin/bash
# Instant deployment with SSH key (no password required)

echo "⚡ Instant deploying to Hostinger (no password needed)..."

# Hostinger connection details
HOST="46.202.182.11"
PORT="65002"
USER="u230128646"
KEY_FILE="~/.ssh/carpe-tree-hostinger"
REMOTE_PATH="domains/carpetree.com/public_html"

# Test SSH key connection first
echo "🔍 Testing SSH key connection..."
ssh -o ConnectTimeout=10 -o BatchMode=yes -i "$KEY_FILE" -p "$PORT" "$USER@$HOST" "echo 'SSH key working!'" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✅ SSH key authentication successful!"
else
    echo "❌ SSH key authentication failed!"
    echo "💡 Run ./generate-ssh-keys.sh to create new keys"
    echo "   Then add the public key to Hostinger SSH settings"
    exit 1
fi

# Deploy files instantly
echo "⚡ Deploying files..."
scp -i "$KEY_FILE" -P "$PORT" "quote.html" "$USER@$HOST:$REMOTE_PATH/" && echo "✅ quote.html deployed"
scp -i "$KEY_FILE" -P "$PORT" "debug-simple.html" "$USER@$HOST:$REMOTE_PATH/" && echo "✅ debug-simple.html deployed"

echo ""
echo "🎉 Instant deployment complete! No password needed!"
echo "🧪 Test: https://carpetree.com/debug-simple.html"
echo "🎯 Progress bar: https://carpetree.com/quote.html"