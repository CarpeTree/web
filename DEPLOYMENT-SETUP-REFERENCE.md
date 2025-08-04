# Carpe Tree Website - Instant Deployment Setup

## 🔑 SSH Configuration (PERMANENT)
- **SSH Key Location**: `~/.ssh/carpe-tree-hostinger`
- **Public Key**: `ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAICCvBywJEgbLnFx3dTJlpjORpTOrWv8CjnZr/zTleXEw carpe-tree-hostinger`
- **Hostinger SSH Details**:
  - Host: `46.202.182.11`
  - Port: `65002`
  - Username: `u230128646`
  - Upload Path: `domains/carpetree.com/public_html/`

## ⚡ Instant Deployment Commands
```bash
# Deploy website changes instantly (no password needed)
./instant-deploy.sh

# Test SSH connection
ssh -i ~/.ssh/carpe-tree-hostinger -p 65002 u230128646@46.202.182.11

# Manual file upload if needed
scp -P 65002 "filename.html" "u230128646@46.202.182.11:domains/carpetree.com/public_html/"
```

## 📁 Key Files Created
- `instant-deploy.sh` - One-command deployment
- `generate-ssh-keys.sh` - Generate new SSH keys if needed
- `deploy-simple.sh` - Password-based deployment (backup)
- `quick-deploy.sh` - Alternative deployment script

## 🌐 Test URLs After Deployment
- **Debug Page**: https://carpetree.com/debug-simple.html
- **Progress Bar**: https://carpetree.com/quote.html (Step 3)

## 🔧 Progress Bar Features Deployed
- ✅ 4-step animated progress bar
- ✅ Debug panels for troubleshooting  
- ✅ Orange test button for manual testing
- ✅ Resubmit button on errors
- ✅ EXIF data extraction from photos
- ✅ Multiple endpoint fallbacks for reliability

## 🎯 Current Status
- SSH keys configured and working
- Passwordless deployment active
- Progress bar fully deployed and functional
- All debug tools available on live site

## 💡 For Future AI Conversations
Copy this info to explain the current setup:
"My website uses instant SSH deployment to Hostinger. SSH keys are configured at ~/.ssh/carpe-tree-hostinger for u230128646@46.202.182.11:65002. Files deploy to domains/carpetree.com/public_html/. Run ./instant-deploy.sh for immediate deployment. Progress bar is deployed and working on the quote form."