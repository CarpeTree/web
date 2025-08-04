<?php
// Fix email configuration issues

echo "🔧 Fixing email configuration issues...\n";

// Check current environment variables
echo "📋 Current email environment variables:\n";
echo "SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'NOT SET') . "\n";
echo "SMTP_USER: " . (getenv('SMTP_USER') ?: 'NOT SET') . "\n"; 
echo "SMTP_FROM: " . (getenv('SMTP_FROM') ?: 'NOT SET') . "\n";
echo "ADMIN_EMAIL: " . (getenv('ADMIN_EMAIL') ?: 'NOT SET') . "\n";

// Check for .env file
$env_file = '.env';
if (file_exists($env_file)) {
    echo "\n✅ .env file exists\n";
    $content = file_get_contents($env_file);
    echo "Content preview:\n" . substr($content, 0, 200) . "...\n";
} else {
    echo "\n❌ .env file missing\n";
    echo "📝 Creating basic .env file...\n";
    
    $env_content = "# Carpe Tree Email Configuration
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USER=sapport@carpetree.com
SMTP_PASS=your_email_password_here
SMTP_FROM=sapport@carpetree.com
ADMIN_EMAIL=sapport@carpetree.com

# Database (update with your actual values)
DB_HOST=localhost
DB_NAME=u230128646_carpetree
DB_USER=u230128646_carpetree
DB_PASS=your_database_password_here

# API Keys
OPENAI_API_KEY=your_openai_key_here
GOOGLE_GEMINI_API_KEY=your_gemini_key_here
";
    
    file_put_contents($env_file, $env_content);
    echo "✅ Created .env template\n";
}

// Check for typos in files
echo "\n🔍 Checking for email typos...\n";
$files_with_typos = [
    'server/config/config.example.php',
    'server/api/admin-notification-simple.php', 
    'server/utils/mailer.php'
];

foreach ($files_with_typos as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'sapport@carpetree.com') !== false) {
            echo "❌ Found typo in: $file\n";
        } else {
            echo "✅ No typos in: $file\n";
        }
    }
}

echo "\n📋 To fix email issues:\n";
echo "1. Update .env file with real email credentials\n";
echo "2. Fix typos: sapport → support\n";
echo "3. Configure SMTP settings in hosting panel\n";
echo "4. Test email delivery\n";
?>