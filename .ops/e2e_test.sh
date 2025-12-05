#!/bin/bash
set -euo pipefail
ID=$(date +%s)
URL='https://carpetree.com/server/api/submitQuote-working.php'
IMG1='/var/www/carpetree.com/images/before.jpeg'
IMG2='/var/www/carpetree.com/images/sprinkler.webp'
IMG=''
if [ -f "$IMG1" ]; then IMG="$IMG1"; elif [ -f "$IMG2" ]; then IMG="$IMG2"; fi
printf 'Posting to %s ID=%s with image=%s\n' "$URL" "$ID" "${IMG:-none}"
CURL_ARGS=(-sS -k -X POST \
  -F "email=e2e+$ID@carpetree.com" \
  -F "name=E2E Test" \
  -F "phone=555-0100" \
  -F "address=Victoria, BC" \
  -F 'selectedServices=["pruning"]' \
  -F "notes=E2E Automated test ID=$ID" \
)
if [ -n "$IMG" ]; then
  CURL_ARGS+=(-F "files[]=@$IMG")
fi
RESP=$(curl "${CURL_ARGS[@]}" "$URL" || true)
echo "$RESP"
echo '---'
QUOTE_ID=$(echo "$RESP" | sed -n 's/.*"quote_id"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -n1)
echo "quote_id_from_resp: ${QUOTE_ID:-none}"
if [ -z "${QUOTE_ID:-}" ]; then
  QUOTE_ID=$(mysql -N -s -e "SELECT id FROM carpetree.quotes WHERE notes LIKE 'E2E Automated test ID=$ID%' ORDER BY id DESC LIMIT 1" || true)
  echo "quote_id_in_db: ${QUOTE_ID:-none}"
fi
CUST=$(mysql -N -s -e "SELECT id FROM carpetree.customers WHERE email = 'e2e+$ID@carpetree.com' ORDER BY id DESC LIMIT 1" || true)
echo "customer_id: ${CUST:-none}"
if [ -n "${QUOTE_ID:-}" ]; then
  MEDIA=$(mysql -N -s -e "SELECT COUNT(*) FROM carpetree.media WHERE quote_id = $QUOTE_ID" || true)
  echo "media_files_for_quote: ${MEDIA:-0}"
  UPLOAD_DIR="/var/www/carpetree.com/server/uploads/quote_${QUOTE_ID}"
  if [ -d "$UPLOAD_DIR" ]; then
    echo "upload_dir_exists: yes"
    ls -lh "$UPLOAD_DIR" | tail -n +1
  else
    echo "upload_dir_exists: no"
  fi
fi
