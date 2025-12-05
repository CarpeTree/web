# Carpe Tree'em Codebase Cleanup Manifest

## CRITICAL SECURITY ISSUES TO FIX

### 1. terminal-exec.php - REMOVE IMMEDIATELY
- **Location:** `server/api/terminal-exec.php`
- **Risk:** Remote Code Execution (RCE) - Anyone with the hardcoded token can execute arbitrary commands
- **Action:** DELETE from production server

### 2. Hardcoded Credentials
- **Files with hardcoded tokens/passwords should use .env**
- **Action:** Move all secrets to .env, ensure .env is in .gitignore

### 3. Exposed .env Files
- `.env`, `.env.backup_*`, `server/config/.env`
- **Action:** Ensure these are not accessible via web (add to .htaccess deny rules)

---

## FILES TO DELETE (One-time scripts, not needed in production)

### Fix Scripts (66 files)
```
fix-*.php
```

### Debug Scripts (9 files)
```
debug-*.php
```

### Deploy Scripts (20 files)
```
deploy-*.php (keep deploy-webhook.php)
deploy-*.sh
```

### Create Scripts (19 files)
```
create-*.php
```

### Check Scripts (11 files)
```
check-*.php
```

### Restore Scripts (6 files)
```
restore-*.php
```

### Old Admin Dashboards
```
admin-dashboard-complete.html
admin-dashboard-fixed.html
admin-dashboard-inline-ai.html
admin-dashboard-local.html
admin-dashboard.html (replaced by admin-v2.html)
analytics-dashboard.html
correct-4column-admin-dashboard.html
gemini-25-pro-dashboard.html
gemini-dashboard.html
model-comparison-dashboard.html
o3-pro-dashboard.html
o4-mini-dashboard.html
openai-o3-pro-dashboard.html
openai-o4-mini-dashboard.html
quote-map-dashboard.html
test-admin-dashboard.html
dashboard-test.html
fixed-admin-dashboard-real-models.html
react-admin-working.html
redesign-admin-dashboard-4column.html
```

### Miscellaneous One-time Files
```
add-*.php
apply-*.php
build-*.php
capture-*.php
complete-*.php
emergency-*.php
enhance-*.php
enrich-*.php
export-*.php
final-*.php
find-*.php
force-*.php
full-*.php
get-*.php
hostinger-*.php
implement-*.php
import-*.php
install-*.php
integrate-*.php
manual-*.txt
normalize-*.php
populate-*.php
preserve-*.php
preview-*.php
quick-*.php
resend-*.php
reset-*.php
resubmit-*.php
save-*.php
send-*.php (except server/api/send-estimate.php)
test-*.html
trigger-*.php (except server/api/trigger-all-analyses.php)
update-*.sql
verify-*.sh
```

### Old/Duplicate HTML Pages
```
admin-quote.html
admin-setup.html
comprehensive_admin_email_preview.html
customer-quote.html
debug-simple.html
enhanced-email-preview.html
functionality-checklist.html
lets-talk.html
llm-form.html
quote-new.html
quote-old-backup.html
quote-simple.html
review-request.html
test-*.html
upload-more.html
video_quote.html
ai-model-comparison-with-costs.html
```

### Archive Files
```
Archive.zip
admin.tar.gz
*.sql (backup files)
*.pdf (estimates)
IMG_0374.MOV (test video)
```

---

## FILES TO KEEP

### Core HTML Pages
- `index.html` - Homepage
- `quote.html` - Quote form
- `philosophy.html` - Philosophy page
- `services.html` - Services page
- `root-knowledge.html` - Root knowledge page
- `field-capture.html` - Field capture form
- `admin-v2.html` - Admin dashboard (rename to admin.html)
- `customer-crm-dashboard.html` - CRM dashboard
- `bronze-birch-borer.html` - Educational content
- `firesmart.html` - FireSmart content
- `typing-*.html` - Typing games (if needed)

### Core Directories
- `server/` - API endpoints (clean up duplicates inside)
- `images/` - Assets
- `services/` - Service detail pages
- `uploads/` - User uploads (consider moving to Hostinger)

### Config/Style Files
- `style.css`
- `liquid-glass-theme.css`
- `manifest.json`
- `service-worker.js`
- `.env` (keep but secure)
- `composer.json`
- `package.json`

### Documentation (keep minimal)
- `README.md`
- `.cursor/rules/` (development rules)

---

## SERVER API CLEANUP

### Keep These APIs
```
server/api/
├── admin-quotes.php
├── submitQuote-reliable.php (rename to submitQuote.php)
├── openai-o4-mini-analysis.php (rename to gpt-analysis.php)
├── gemini-2-5-pro-analysis.php (rename to gemini-analysis.php)
├── whisper-transcription.php
├── send-estimate.php
├── save-estimate-draft.php
├── save-quote-context.php
├── save-prompts.php
├── get-system-prompts.php
├── comprehensive-distance-calculator.php
├── trigger-all-analyses.php
├── deploy-webhook.php
```

### Delete These APIs (duplicates/unused)
```
server/api/
├── terminal-exec.php (SECURITY RISK)
├── submitQuote-*.php (keep only reliable version)
├── admin-notification*.php (consolidate)
├── *-local.php
├── *-working.php
├── *-debug.php
├── google-gemini-analysis.php (duplicate)
├── aiQuote.php (old)
└── Many others...
```

---

## ESTIMATED CLEANUP RESULTS

- **Before:** ~258 PHP files, ~55 HTML files, 1.1GB
- **After:** ~30 PHP files, ~15 HTML files, ~200MB
- **Removed:** ~230 unnecessary files

---

## POST-CLEANUP TASKS

1. Rename `admin-v2.html` to `admin.html`
2. Update all internal links
3. Test all core functionality
4. Set up proper .htaccess security rules
5. Move uploads to Hostinger CDN
6. Set up proper backup system

