# Media Migration System Setup Guide

This system automatically moves media files (images, videos, audio) from the VPS to Hostinger static storage after AI processing completes.

## Benefits
- **Free up VPS disk space** - Videos and images move to Hostinger's unlimited storage
- **Faster page loads** - Static files served from dedicated hosting
- **Automatic** - Runs after each AI analysis completes
- **Fallback** - Files remain accessible even if migration fails

## Architecture

```
┌─────────────────┐    AI Processing    ┌─────────────────┐
│  Field Capture  │ ────────────────▶   │       VPS       │
│  (Customer)     │     Upload          │  carpetree.com  │
└─────────────────┘                     └────────┬────────┘
                                                 │
                                        After AI Analysis
                                                 │
                                                 ▼
                                        ┌─────────────────┐
                                        │   Hostinger     │
                                        │ media.carpetree │
                                        │   .com/media/   │
                                        └─────────────────┘
```

## Setup Instructions

### Step 1: Deploy Receiver to Hostinger

1. Log into Hostinger File Manager
2. Create directory: `public_html/media-api/`
3. Create directory: `public_html/media/`
4. Upload `hostinger/media-receiver.php` to `public_html/media-api/receive.php`
5. Set permissions:
   ```
   media-api/receive.php: 644
   media/: 755 (writable by PHP)
   ```

### Step 2: Generate Auth Token

On your local machine or VPS:
```bash
openssl rand -hex 32
```

Save this token - you'll need it in both places.

### Step 3: Configure Hostinger Receiver

Edit `public_html/media-api/receive.php` on Hostinger:
```php
$authToken = 'YOUR_TOKEN_FROM_STEP_2';
```

Or set environment variable in Hostinger (if supported):
```
MEDIA_AUTH_TOKEN=your_token_here
```

### Step 4: Configure VPS

Add to `/var/www/carpetree.com/.env`:
```env
MEDIA_MODE=remote
MEDIA_REMOTE_BASE_URL=https://your-hostinger-domain.com/media
MEDIA_REMOTE_UPLOAD_URL=https://your-hostinger-domain.com/media-api/receive.php
MEDIA_REMOTE_AUTH_TOKEN=your_token_from_step_2
MEDIA_DELETE_LOCAL_AFTER_UPLOAD=true
```

### Step 5: Test the Connection

```bash
# From VPS, test the endpoint
curl -X POST https://your-hostinger-domain.com/media-api/receive.php \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "quote_id=1" \
  -F "filename=test.txt" \
  -F "file=@/tmp/test.txt"
```

### Step 6: Migrate Existing Files (Optional)

Via API:
```bash
# Check status
curl https://carpetree.com/server/api/migrate-media.php?action=status

# Migrate a single quote
curl -X POST "https://carpetree.com/server/api/migrate-media.php?action=quote&quote_id=76"

# Batch migrate (up to 50 quotes)
curl -X POST "https://carpetree.com/server/api/migrate-media.php?action=batch&limit=50"
```

## API Reference

### GET /server/api/migrate-media.php?action=config
Check configuration status.

### GET /server/api/migrate-media.php?action=status
Get overall migration status.

### GET /server/api/migrate-media.php?action=status&quote_id=123
Get migration status for a specific quote.

### POST /server/api/migrate-media.php?action=quote&quote_id=123
Migrate all media for a single quote.

### POST /server/api/migrate-media.php?action=batch&limit=50
Migrate pending files in batch.

## Database Changes

The system adds a `remote_url` column to the `media` table:
```sql
ALTER TABLE media ADD COLUMN IF NOT EXISTS remote_url VARCHAR(500) DEFAULT NULL;
```

This is done automatically on first migration.

## File Storage Structure

### On VPS (temporary)
```
/server/uploads/quote_123/
  ├── upload_0_timestamp_filename.mov
  ├── frame_1.jpg
  └── audio_extract.mp3
```

### On Hostinger (permanent)
```
/media/quote_123/
  ├── upload_0_timestamp_filename.mov
  ├── frame_1.jpg
  └── audio_extract.mp3
```

## Logs

Migration logs are written to:
- VPS: `/var/www/carpetree.com/server/logs/media_migration.log`
- Hostinger: `/media/upload.log`

## Troubleshooting

### "Remote storage not configured"
Check that all MEDIA_* environment variables are set in `.env`

### Files not deleting locally
Check `MEDIA_DELETE_LOCAL_AFTER_UPLOAD=true` is set

### Upload fails with 401
Verify auth tokens match between VPS and Hostinger

### Large files timing out
Increase `MEDIA_REMOTE_TIMEOUT_SECS` in `.env`

### Hostinger disk full
Contact Hostinger support or upgrade plan

## Cron Job (Optional)

To run batch migration automatically:
```bash
# Add to crontab (runs every 6 hours)
0 */6 * * * curl -X POST "https://carpetree.com/server/api/migrate-media.php?action=batch&limit=20" > /dev/null 2>&1
```
