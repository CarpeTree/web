#!/usr/bin/env bash
set -euo pipefail

# Unified deploy to VPS (frontend + server)
# Env overrides supported:
#   DEPLOY_HOST (default deploy@82.25.84.43)
#   DEPLOY_KEY  (default ~/.ssh/carpetree-vps-ci)
#   WEB_ROOT    (default /var/www/carpetree.com)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
HOST="${DEPLOY_HOST:-deploy@82.25.84.43}"
KEY="${DEPLOY_KEY:-$HOME/.ssh/carpetree-vps-ci}"
WEB_ROOT="${WEB_ROOT:-/var/www/carpetree.com}"
SSH_OPTS="-i $KEY -o BatchMode=yes"
REMOTE_TMP="/tmp/ct_site_$$"

echo "Deploying to $HOST ($WEB_ROOT)"

# Prepare remote temp workspace
ssh $SSH_OPTS "$HOST" "rm -rf $REMOTE_TMP && mkdir -p $REMOTE_TMP/site $REMOTE_TMP/server/api $REMOTE_TMP/server/utils $REMOTE_TMP/server/templates $REMOTE_TMP/ai"

# Sync server-side code
rsync -az --delete -e "ssh $SSH_OPTS" "$ROOT/server/api/"       "$HOST:$REMOTE_TMP/server/api/"
rsync -az --delete -e "ssh $SSH_OPTS" "$ROOT/server/utils/"     "$HOST:$REMOTE_TMP/server/utils/"
rsync -az --delete -e "ssh $SSH_OPTS" "$ROOT/server/templates/" "$HOST:$REMOTE_TMP/server/templates/"
rsync -az --delete -e "ssh $SSH_OPTS" "$ROOT/ai/"               "$HOST:$REMOTE_TMP/ai/"

# Sync key frontend assets (add more as needed)
rsync -az -e "ssh $SSH_OPTS" "$ROOT/index.html"      "$HOST:$REMOTE_TMP/site/" || true
rsync -az -e "ssh $SSH_OPTS" "$ROOT/services.html"   "$HOST:$REMOTE_TMP/site/" || true
rsync -az -e "ssh $SSH_OPTS" "$ROOT/quote.html"      "$HOST:$REMOTE_TMP/site/" || true
rsync -az -e "ssh $SSH_OPTS" "$ROOT/lets-talk.html"  "$HOST:$REMOTE_TMP/site/" || true
rsync -az -e "ssh $SSH_OPTS" "$ROOT/style.css"       "$HOST:$REMOTE_TMP/site/" || true

# Move into place with sudo and fix ownership
ssh $SSH_OPTS "$HOST" "rsync -a $REMOTE_TMP/server/api/ /var/www/carpetree.com/server/api/ && \
  rsync -a $REMOTE_TMP/server/utils/ /var/www/carpetree.com/server/utils/ && \
  rsync -a $REMOTE_TMP/server/templates/ /var/www/carpetree.com/server/templates/ && \
  rsync -a $REMOTE_TMP/ai/ /var/www/carpetree.com/ai/ && \
  rsync -a $REMOTE_TMP/site/ $WEB_ROOT/ && \
  rm -rf $REMOTE_TMP"

echo deploy_ok


