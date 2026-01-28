#!/bin/bash
#
# VPS Setup Script for GitHub Webhook Deployment
# Run this ONCE on your VPS to configure automated deployments
#
# Usage: bash setup-webhook-vps.sh
#

set -e

echo "=============================================="
echo "GitHub Webhook Deployment Setup for VPS"
echo "=============================================="
echo ""

WEBROOT="/var/www/carpetree.com"
GITHUB_REPO="https://github.com/CarpeTree/web.git"

# Step 1: Generate a webhook secret
echo "Step 1: Generating webhook secret..."
WEBHOOK_SECRET=$(openssl rand -hex 32)
echo "$WEBHOOK_SECRET" > "$WEBROOT/.webhook-secret"
chmod 600 "$WEBROOT/.webhook-secret"
echo "  Secret saved to $WEBROOT/.webhook-secret"
echo ""
echo "  ╔════════════════════════════════════════════════════════════════╗"
echo "  ║  SAVE THIS SECRET - You'll need it for GitHub:                 ║"
echo "  ║  $WEBHOOK_SECRET  ║"
echo "  ╚════════════════════════════════════════════════════════════════╝"
echo ""

# Step 2: Create required directories
echo "Step 2: Creating directories..."
mkdir -p "$WEBROOT/server/logs"
mkdir -p "$WEBROOT/scripts"
chmod 775 "$WEBROOT/server/logs"
echo "  Created $WEBROOT/server/logs"
echo "  Created $WEBROOT/scripts"
echo ""

# Step 3: Initialize git if needed
echo "Step 3: Checking git repository..."
cd "$WEBROOT"

if [ ! -d ".git" ]; then
    echo "  Initializing git repository..."
    git init
    git remote add origin "$GITHUB_REPO"
    git fetch origin main
    git checkout -b main
    git reset --hard origin/main
    echo "  Git repository initialized and synced with origin/main"
else
    echo "  Git repository already exists"
    git remote -v
fi
echo ""

# Step 4: Set up deploy script
echo "Step 4: Setting up deploy script..."
if [ -f "$WEBROOT/scripts/auto-deploy.sh" ]; then
    chmod +x "$WEBROOT/scripts/auto-deploy.sh"
    echo "  Made auto-deploy.sh executable"
else
    echo "  WARNING: auto-deploy.sh not found. Push your code first, then re-run."
fi
echo ""

# Step 5: Test the webhook endpoint
echo "Step 5: Testing webhook endpoint..."
if [ -f "$WEBROOT/server/api/github-webhook.php" ]; then
    echo "  Webhook endpoint exists at /server/api/github-webhook.php"
    echo "  URL: https://carpetree.com/server/api/github-webhook.php"
else
    echo "  WARNING: github-webhook.php not found. Push your code first."
fi
echo ""

# Step 6: Instructions
echo "=============================================="
echo "SETUP COMPLETE"
echo "=============================================="
echo ""
echo "Next steps:"
echo ""
echo "1. Go to your GitHub repository settings:"
echo "   https://github.com/CarpeTree/web/settings/hooks/new"
echo ""
echo "2. Configure the webhook:"
echo "   • Payload URL: https://carpetree.com/server/api/github-webhook.php"
echo "   • Content type: application/json"
echo "   • Secret: $WEBHOOK_SECRET"
echo "   • Events: Just the push event"
echo "   • Active: ✓ checked"
echo ""
echo "3. Click 'Add webhook'"
echo ""
echo "4. Test by pushing a commit to your main branch"
echo ""
echo "5. Check deploy logs at:"
echo "   $WEBROOT/server/logs/deploy.log"
echo ""
echo "=============================================="
