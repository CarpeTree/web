#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
KEY="${DEPLOY_KEY:-$HOME/.ssh/carpetree-vps-ci}"
HOST="${DEPLOY_HOST:-deploy@82.25.84.43}"
SSH_OPTS="-i $KEY -o BatchMode=yes"
REMOTE_TMP="/tmp/ct_sync_$$"
QID="${1:-}"

# Prepare remote temp dir
ssh $SSH_OPTS "$HOST" "rm -rf $REMOTE_TMP && mkdir -p $REMOTE_TMP/server/api $REMOTE_TMP/server/utils $REMOTE_TMP/server/templates $REMOTE_TMP/ai"

# Rsync local source to remote temp (minimal flags for macOS rsync)
rsync -az --delete -e "ssh $SSH_OPTS" "$ROOT/server/api/"       "$HOST:$REMOTE_TMP/server/api/"
rsync -az --delete -e "ssh $SSH_OPTS" "$ROOT/server/utils/"     "$HOST:$REMOTE_TMP/server/utils/"
rsync -az --delete -e "ssh $SSH_OPTS" "$ROOT/server/templates/" "$HOST:$REMOTE_TMP/server/templates/"
rsync -az --delete -e "ssh $SSH_OPTS" "$ROOT/ai/"               "$HOST:$REMOTE_TMP/ai/"

# Move into place with sudo and fix ownership
ssh $SSH_OPTS "$HOST" "sudo -n rsync -a $REMOTE_TMP/server/api/ /var/www/carpetree.com/server/api/ && \
  sudo -n rsync -a $REMOTE_TMP/server/utils/ /var/www/carpetree.com/server/utils/ && \
  sudo -n rsync -a $REMOTE_TMP/server/templates/ /var/www/carpetree.com/server/templates/ && \
  sudo -n rsync -a $REMOTE_TMP/ai/ /var/www/carpetree.com/ai/ && \
  sudo -n chown -R www-data:www-data /var/www/carpetree.com/server /var/www/carpetree.com/ai && \
  rm -rf $REMOTE_TMP"

# Run maintenance and optional GPT-5
if [[ -n "$QID" ]]; then
  ssh $SSH_OPTS "$HOST" "sudo -n /usr/local/bin/carpetree-maint $QID"
else
  ssh $SSH_OPTS "$HOST" "sudo -n /usr/local/bin/carpetree-maint"
fi

echo deploy_ok
