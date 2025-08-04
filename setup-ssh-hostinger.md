# SSH Deployment Setup for Hostinger

## Your SSH Key
You provided: `ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIH4zy5MAGz7OK2pqoyY4Gs40xSxMVIBXn3raJ0FaoC4K carpe-tree-hostinger`

## Setup Steps

### 1. Add SSH Key to Hostinger
1. Log into your Hostinger control panel
2. Go to **Advanced** → **SSH Access** 
3. Add your public key (the one you provided above)
4. Note your SSH connection details (hostname, username)

### 2. Test SSH Connection
```bash
ssh -i ~/.ssh/carpe-tree-hostinger your-username@your-server.hostinger.com
```

### 3. Update Deploy Script
Edit `deploy-to-hostinger.sh` with your actual:
- `HOST="your-actual-server.hostinger.com"`
- `USER="your-actual-username"`  
- `REMOTE_PATH="/public_html"` (or your website directory)

### 4. Deploy Website
```bash
chmod +x deploy-to-hostinger.sh
./deploy-to-hostinger.sh
```

## Alternative: Direct SCP Commands

If you prefer manual upload:
```bash
# Upload main files
scp -i ~/.ssh/carpe-tree-hostinger quote.html your-username@your-server.hostinger.com:/public_html/
scp -i ~/.ssh/carpe-tree-hostinger debug-simple.html your-username@your-server.hostinger.com:/public_html/

# Test the upload
curl -I https://carpetree.com/debug-simple.html
```

## What You Need to Provide
1. Your actual Hostinger SSH hostname
2. Your SSH username
3. Your website directory path (usually `/public_html`)

Once you provide these details, I can update the script with the correct connection info!