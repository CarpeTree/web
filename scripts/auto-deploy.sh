#!/bin/bash
#
# Auto-Deploy Script for Carpe Tree Website
# Triggered by GitHub webhook after push to main branch
#
# This script:
# 1. Pulls latest code from GitHub
# 2. Sets correct permissions
# 3. Clears any caches
# 4. Optionally restarts PHP-FPM
#

set -e  # Exit on any error

# Configuration
WEBROOT="/var/www/carpetree.com"
LOGFILE="$WEBROOT/server/logs/deploy.log"
BRANCH="main"
WEBUSER="www-data"
WEBGROUP="www-data"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
    echo "$1"
}

log "=========================================="
log "AUTO-DEPLOY STARTED"
log "=========================================="

# Change to web root
cd "$WEBROOT" || { log "ERROR: Cannot cd to $WEBROOT"; exit 1; }

log "Working directory: $(pwd)"

# Check if it's a git repository
if [ ! -d ".git" ]; then
    log "ERROR: Not a git repository. Run initial setup first."
    exit 1
fi

# Fetch latest changes
log "Fetching from origin..."
git fetch origin "$BRANCH" 2>&1 | while read line; do log "  $line"; done

# Check current branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
log "Current branch: $CURRENT_BRANCH"

if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    log "WARNING: Not on $BRANCH branch, checking out..."
    git checkout "$BRANCH" 2>&1 | while read line; do log "  $line"; done
fi

# Get current and remote commit hashes
LOCAL_HASH=$(git rev-parse HEAD)
REMOTE_HASH=$(git rev-parse "origin/$BRANCH")

log "Local commit:  $LOCAL_HASH"
log "Remote commit: $REMOTE_HASH"

if [ "$LOCAL_HASH" = "$REMOTE_HASH" ]; then
    log "Already up to date. Nothing to deploy."
    log "=========================================="
    log "AUTO-DEPLOY COMPLETE (no changes)"
    log "=========================================="
    exit 0
fi

# Pull the latest code
log "Pulling latest changes..."
git pull origin "$BRANCH" 2>&1 | while read line; do log "  $line"; done

# Get the new commit info
NEW_HASH=$(git rev-parse HEAD)
COMMIT_MSG=$(git log -1 --pretty=format:"%s" HEAD)
COMMIT_AUTHOR=$(git log -1 --pretty=format:"%an" HEAD)

log "Deployed commit: $NEW_HASH"
log "Commit message: $COMMIT_MSG"
log "Author: $COMMIT_AUTHOR"

# Fix permissions (only if we have permission to do so)
log "Setting file permissions..."
if [ -w "$WEBROOT" ]; then
    # Make sure uploads directory is writable
    if [ -d "$WEBROOT/server/uploads" ]; then
        chmod -R 775 "$WEBROOT/server/uploads" 2>/dev/null || log "  (uploads chmod skipped - no permission)"
    fi
    
    # Make sure logs directory is writable
    if [ -d "$WEBROOT/server/logs" ]; then
        chmod -R 775 "$WEBROOT/server/logs" 2>/dev/null || log "  (logs chmod skipped - no permission)"
    fi
    
    log "  Permissions updated"
else
    log "  (skipped - no write permission to $WEBROOT)"
fi

# Clear OPcache if PHP-FPM is running
log "Clearing PHP OPcache..."
if command -v php &> /dev/null; then
    php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared'; } else { echo 'OPcache not available'; }" 2>/dev/null || log "  (OPcache clear skipped)"
fi

# Optional: Restart PHP-FPM (uncomment if you have sudo access)
# log "Restarting PHP-FPM..."
# sudo systemctl restart php8.1-fpm 2>/dev/null || log "  (PHP-FPM restart skipped)"

log "=========================================="
log "AUTO-DEPLOY COMPLETE"
log "Deployed: $NEW_HASH"
log "Message: $COMMIT_MSG"
log "=========================================="

# Send a simple notification (optional - create this file if you want Slack/email alerts)
if [ -f "$WEBROOT/scripts/notify-deploy.sh" ]; then
    bash "$WEBROOT/scripts/notify-deploy.sh" "$NEW_HASH" "$COMMIT_MSG" "$COMMIT_AUTHOR" &
fi

exit 0
