<?php
// Simple Composer installation with detailed output
header('Content-Type: text/plain');
set_time_limit(300); // 5 minutes timeout

echo "=== SIMPLE COMPOSER INSTALL ===\n\n";

$project_root = __DIR__;
echo "Working directory: $project_root\n\n";

// Step 1: Check if composer.json exists
if (!file_exists($project_root . '/composer.json')) {
    echo "❌ composer.json not found!\n";
    echo "Creating basic composer.json...\n";
    
    $composer_content = '{
    "require": {
        "phpmailer/phpmailer": "^6.8"
    }
}';
    file_put_contents($project_root . '/composer.json', $composer_content);
    echo "✅ composer.json created\n\n";
}

// Step 2: Download Composer if needed
if (!file_exists($project_root . '/composer.phar')) {
    echo "📥 Downloading Composer...\n";
    
    $installer_url = 'https://getcomposer.org/installer';
    $installer = file_get_contents($installer_url);
    
    if ($installer) {
        file_put_contents($project_root . '/composer-setup.php', $installer);
        echo "✅ Composer installer downloaded\n";
        
        // Run installer
        echo "🚀 Installing Composer...\n";
        $install_cmd = "cd $project_root && php composer-setup.php --quiet";
        $output = shell_exec($install_cmd . ' 2>&1');
        echo $output . "\n";
        
        // Clean up
        if (file_exists($project_root . '/composer-setup.php')) {
            unlink($project_root . '/composer-setup.php');
        }
        
        if (file_exists($project_root . '/composer.phar')) {
            echo "✅ Composer installed successfully\n\n";
        } else {
            echo "❌ Composer installation failed\n";
            echo "Trying direct download...\n";
            
            $composer_phar = file_get_contents('https://getcomposer.org/composer.phar');
            if ($composer_phar) {
                file_put_contents($project_root . '/composer.phar', $composer_phar);
                echo "✅ Composer downloaded directly\n\n";
            } else {
                echo "❌ Could not download Composer\n";
                exit;
            }
        }
    } else {
        echo "❌ Could not download Composer installer\n";
        exit;
    }
} else {
    echo "✅ Composer already available\n\n";
}

// Step 3: Install dependencies
echo "📦 Installing PHPMailer...\n";
$install_cmd = "cd $project_root && php composer.phar install --no-dev 2>&1";
echo "Command: $install_cmd\n\n";

$install_output = shell_exec($install_cmd);
echo "Installation output:\n";
echo $install_output . "\n";

// Step 4: Verify installation
echo "\n=== VERIFICATION ===\n";

if (is_dir($project_root . '/vendor')) {
    echo "✅ Vendor directory created\n";
    
    if (file_exists($project_root . '/vendor/autoload.php')) {
        echo "✅ Autoloader available\n";
        
        // Test autoload
        require_once $project_root . '/vendor/autoload.php';
        
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "✅ PHPMailer installed and working!\n";
            
            // Test creating PHPMailer instance
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                echo "✅ PHPMailer instance created successfully\n";
            } catch (Exception $e) {
                echo "⚠️  PHPMailer class exists but error creating instance: " . $e->getMessage() . "\n";
            }
            
        } else {
            echo "❌ PHPMailer class not found\n";
        }
    } else {
        echo "❌ Autoloader not found\n";
    }
    
    // List vendor contents
    echo "\nVendor directory contents:\n";
    $vendor_files = scandir($project_root . '/vendor');
    foreach ($vendor_files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "  - $file\n";
        }
    }
    
} else {
    echo "❌ Vendor directory not created\n";
    echo "Installation failed. Raw output above.\n";
}

echo "\n=== INSTALLATION COMPLETE ===\n";
?> 