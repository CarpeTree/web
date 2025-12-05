# GitHub Webhook Deployment Setup Guide

This guide walks you through setting up automatic deployment from GitHub to your VPS.

## Overview

When you push code to GitHub, a webhook will automatically trigger a `git pull` on your VPS, deploying the latest changes without manual SSH access.

## Step 1: Generate a Webhook Secret

On your local machine, generate a strong random secret:

```bash
openssl rand -hex 32
```

Copy the output (it will look like: `a1b2c3d4e5f6...`). You'll need this in the next steps.

## Step 2: Configure the Secret on Your VPS

SSH into your VPS:

```bash
ssh root@82.25.84.43
```

Once connected, navigate to your web root:

```bash
cd /var/www/carpetree.com
```

### Option A: Add to .env file (Recommended)

If you have a `.env` file, add the secret:

```bash
echo "DEPLOY_WEBHOOK_SECRET=your_secret_here" >> .env
```

Replace `your_secret_here` with the secret you generated in Step 1.

### Option B: Set as environment variable

```bash
export DEPLOY_WEBHOOK_SECRET=your_secret_here
```

**Note:** This only lasts for the current session. For persistence, use Option A or add to your system's environment configuration.

## Step 3: Upload the Webhook Endpoint

The `deploy-webhook.php` file needs to be on your VPS. You can either:

### Option A: Push to GitHub and pull (Recommended)

```bash
# On your local machine
git add deploy-webhook.php
git commit -m "Add deployment webhook endpoint"
git push origin main

# Then on VPS (already SSH'd in)
git pull origin main
```

### Option B: Upload directly via SCP

```bash
# On your local machine
scp deploy-webhook.php root@82.25.84.43:/var/www/carpetree.com/
```

## Step 4: Set Permissions

On your VPS, ensure the webhook file is readable by the web server:

```bash
chmod 644 /var/www/carpetree.com/deploy-webhook.php
chown www-data:www-data /var/www/carpetree.com/deploy-webhook.php
```

## Step 5: Test the Webhook Endpoint

Test that the endpoint is accessible (from your local machine or browser):

```bash
curl https://carpetree.com/deploy-webhook.php?secret=your_secret_here
```

You should see a JSON response. If you get a 401 error, check that your secret matches.

## Step 6: Configure GitHub Webhook

1. Go to your GitHub repository
2. Click **Settings** → **Webhooks** → **Add webhook**
3. Configure the webhook:
   - **Payload URL**: `https://carpetree.com/deploy-webhook.php?secret=YOUR_SECRET_HERE`
     - Replace `YOUR_SECRET_HERE` with the secret you generated in Step 1
   - **Content type**: `application/json`
   - **Which events**: Select **Just the push event**
   - **Active**: ✅ Checked
4. Click **Add webhook**

## Step 7: Test the Deployment

1. Make a small change to any file in your repository
2. Commit and push:
   ```bash
   git add .
   git commit -m "Test webhook deployment"
   git push origin main
   ```
3. Check the webhook logs on your VPS:
   ```bash
   tail -f /var/www/carpetree.com/deploy-webhook.log
   ```
4. Visit your website to verify the changes are live

## Troubleshooting

### Webhook returns 401 Unauthorized
- Verify the secret in your `.env` file matches the secret in the GitHub webhook URL
- Check that the `.env` file is readable: `cat /var/www/carpetree.com/.env | grep DEPLOY_WEBHOOK_SECRET`

### Webhook returns 500 Error
- Check that `/var/www/carpetree.com` is a Git repository: `cd /var/www/carpetree.com && git status`
- Verify Git is installed: `which git`
- Check file permissions: `ls -la /var/www/carpetree.com/deploy-webhook.php`
- Check the webhook log: `tail -20 /var/www/carpetree.com/deploy-webhook.log`

### Changes not appearing after deployment
- Hard refresh your browser (Cmd+Shift+R or Ctrl+Shift+R)
- Check that the Git pull actually updated files: `cd /var/www/carpetree.com && git log -1`
- Verify file permissions: `ls -la /var/www/carpetree.com/`

### GitHub webhook shows "Failed" or "No response"
- Verify the URL is accessible: `curl https://carpetree.com/deploy-webhook.php?secret=YOUR_SECRET`
- Check your VPS firewall allows incoming HTTPS connections
- Verify SSL certificate is valid: `curl -I https://carpetree.com`

## Security Notes

- **Never commit your `.env` file** to GitHub
- **Use a strong, random secret** (at least 32 characters)
- **Only allow push events** in GitHub webhook settings
- **Monitor the webhook log** regularly for unauthorized access attempts
- The webhook only deploys from the `main` branch (or `master` if configured)

## Logs

Deployment logs are stored at:
```
/var/www/carpetree.com/deploy-webhook.log
```

View recent deployments:
```bash
tail -50 /var/www/carpetree.com/deploy-webhook.log
```

## Next Steps

Once the webhook is working:
1. ✅ Push code changes → automatically deploys
2. ✅ No more manual SSH pulls needed
3. ✅ Monitor deployments via the log file

