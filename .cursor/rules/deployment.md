# Deployment Instructions for Carpe Tree Website

## Server Infrastructure
- **VPS Server**: deploy@82.25.84.43
- **SSH Key**: ~/.ssh/carpetree-vps-ci
- **Web Root**: /var/www/carpetree.com
- **Web User**: www-data:www-data
- **Old Hostinger** (deprecated): u230128646@46.202.182.11:65002

## Deployment Process (Two-Step)

### Step 1: Upload Files from Local Machine
```bash
# From your local machine in project directory
# Upload files to staging area on server
ssh -i ~/.ssh/carpetree-vps-ci deploy@82.25.84.43 'mkdir -p ~/staging'

# Upload frontend files
scp -i ~/.ssh/carpetree-vps-ci *.html *.js *.json deploy@82.25.84.43:~/staging/

# Upload server files
ssh -i ~/.ssh/carpetree-vps-ci deploy@82.25.84.43 'mkdir -p ~/staging/server/config ~/staging/server/api'
scp -i ~/.ssh/carpetree-vps-ci server/config/*.php deploy@82.25.84.43:~/staging/server/config/
scp -i ~/.ssh/carpetree-vps-ci server/api/*.php deploy@82.25.84.43:~/staging/server/api/

# Upload .env file (contains API keys)
scp -i ~/.ssh/carpetree-vps-ci .env deploy@82.25.84.43:~/staging/
```

### Step 2: Install on Server (Requires Sudo)
```bash
# SSH into server
ssh -i ~/.ssh/carpetree-vps-ci deploy@82.25.84.43

# Once on server, move files to web directory (needs sudo password)
sudo cp ~/staging/*.html /var/www/carpetree.com/
sudo cp ~/staging/*.js /var/www/carpetree.com/
sudo cp ~/staging/*.json /var/www/carpetree.com/
sudo cp ~/staging/.env /var/www/carpetree.com/
sudo cp ~/staging/server/config/* /var/www/carpetree.com/server/config/
sudo cp ~/staging/server/api/* /var/www/carpetree.com/server/api/

# Set permissions
sudo chown -R www-data:www-data /var/www/carpetree.com/
sudo chmod 600 /var/www/carpetree.com/.env

# Clean up
rm -rf ~/staging
```

## Important Files & Configurations

### API Keys (.env file)
- Never commit .env to git
- Contains GOOGLE_MAPS_API_KEY, GOOGLE_GEMINI_API_KEY
- Must be chmod 600 for security

### Database Configuration
- Located at: server/config/database-simple.php
- Database is on the VPS server locally

### Key URLs After Deployment
- Field PWA: https://carpetree.com/field-quote.html
- Customer Quote: https://carpetree.com/quote-simple.html
- Map Dashboard: https://carpetree.com/quote-map-dashboard.html
- Admin Dashboard: https://carpetree.com/customer-crm-dashboard.html

## Notes
- The deploy user needs sudo password to write to /var/www/carpetree.com
- Files must be owned by www-data:www-data for PHP to work
- Service worker enables offline functionality for field app
- Always test after deployment











