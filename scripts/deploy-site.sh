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
ssh $SSH_OPTS "$HOST" "rm -rf $REMOTE_TMP && mkdir -p $REMOTE_TMP/site $REMOTE_TMP/server $REMOTE_TMP/ai"

# Sync server-side code (no perms/owner/group/times to avoid chown/chgrp issues)
# Sync the entire server folder to preserve paths used by require/include
rsync -r --delete --no-perms --no-owner --no-group --omit-dir-times -e "ssh $SSH_OPTS" "$ROOT/server/"            "$HOST:$REMOTE_TMP/server/"
rsync -r --delete --no-perms --no-owner --no-group --omit-dir-times -e "ssh $SSH_OPTS" "$ROOT/ai/"               "$HOST:$REMOTE_TMP/ai/"

# Sync key frontend assets (add more as needed)
rsync -r -e "ssh $SSH_OPTS" "$ROOT/index.html"      "$HOST:$REMOTE_TMP/site/" || true
rsync -r -e "ssh $SSH_OPTS" "$ROOT/services.html"   "$HOST:$REMOTE_TMP/site/" || true
rsync -r -e "ssh $SSH_OPTS" "$ROOT/quote.html"      "$HOST:$REMOTE_TMP/site/" || true
rsync -r -e "ssh $SSH_OPTS" "$ROOT/lets-talk.html"  "$HOST:$REMOTE_TMP/site/" || true
rsync -r -e "ssh $SSH_OPTS" "$ROOT/style.css"       "$HOST:$REMOTE_TMP/site/" || true
rsync -r -e "ssh $SSH_OPTS" "$ROOT/images/"         "$HOST:$REMOTE_TMP/site/images/" || true

# Move into place with sudo and fix ownership
ssh $SSH_OPTS "$HOST" "mkdir -p $WEB_ROOT/server $WEB_ROOT/ai && \
  rsync -r --delete --no-perms --no-owner --no-group --omit-dir-times $REMOTE_TMP/server/ $WEB_ROOT/server/ && \
  rsync -r --delete --no-perms --no-owner --no-group --omit-dir-times $REMOTE_TMP/ai/ $WEB_ROOT/ai/ && \
  rsync -r --delete --no-perms --no-owner --no-group --omit-dir-times $REMOTE_TMP/site/ $WEB_ROOT/ && \
  rm -rf $REMOTE_TMP"

echo deploy_ok


