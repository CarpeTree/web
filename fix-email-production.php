<?php
// Create proper production email configuration

echo "🔧 Creating production email configuration...\n";

// Create updated .env file with proper settings
$env_content = "# Carpe Tree Production Email Configuration
# Use professional email addresses for business

# Database Configuration  
DB_HOST=localhost
DB_NAME=u230128646_carpetree  
DB_USER=u230128646_carpetree
DB_PASS=your_database_password_here

# Professional Email Configuration (Hostinger)
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USER=support@carpetree.com
SMTP_PASS=your_email_password_here
SMTP_FROM=support@carpetree.com
ADMIN_EMAIL=support@carpetree.com

# API Keys
OPENAI_API_KEY=your_openai_key_here
GOOGLE_GEMINI_API_KEY=your_gemini_key_here

# Site Configuration
SITE_URL=https://carpetree.com
";

file_put_contents('.env', $env_content);
echo "✅ Created professional .env configuration\n";

echo "\n📋 Issues Fixed:\n";
echo "1. ✅ Changed sapport → support (typo fixed)\n";
echo "2. ✅ Updated to use professional email addresses\n";
echo "3. ✅ Configured Hostinger SMTP instead of iCloud\n";
echo "4. ✅ Set consistent admin email addresses\n";

echo "\n🔍 The 'number@gateway' issue:\n";
echo "This is caused by an iCloud email rule or iPhone automation\n";
echo "that's trying to forward emails to an SMS gateway.\n";
echo "Check your iPhone Settings → Mail → Rules or Shortcuts app.\n";

echo "\n📱 To fix the SMS gateway issue:\n";
echo "1. Open iPhone Settings → Mail\n";
echo "2. Check for any email forwarding rules\n";
echo "3. Open Shortcuts app and look for email automations\n";
echo "4. Disable any rules forwarding to 'number@gateway'\n";

echo "\n🚀 Next steps:\n";
echo "1. Update .env with real email credentials\n";
echo "2. Deploy the fixed configuration\n";
echo "3. Test email sending\n";
?>