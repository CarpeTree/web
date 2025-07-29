<?php
// Install Composer dependencies
header('Content-Type: text/plain');

echo "=== INSTALLING DEPENDENCIES ===\n\n";

try {
    $project_root = __DIR__;
    echo "Project root: $project_root\n";
    
    // Check if Composer is available
    $composer_check = shell_exec('which composer 2>/dev/null');
    if (empty($composer_check)) {
        echo "❌ Composer not found in PATH\n";
        echo "Trying alternative methods...\n";
        
        // Try composer.phar
        if (file_exists($project_root . '/composer.phar')) {
            $composer_cmd = 'php composer.phar';
            echo "✅ Found local composer.phar\n";
        } else {
            echo "📥 Downloading Composer...\n";
            
            // Download Composer
            $installer = file_get_contents('https://getcomposer.org/installer');
            if ($installer) {
                file_put_contents($project_root . '/composer-setup.php', $installer);
                
                // Install Composer
                $install_output = shell_exec("cd $project_root && php composer-setup.php 2>&1");
                echo $install_output . "\n";
                
                if (file_exists($project_root . '/composer.phar')) {
                    $composer_cmd = 'php composer.phar';
                    echo "✅ Composer installed locally\n";
                    
                    // Clean up
                    unlink($project_root . '/composer-setup.php');
                } else {
                    throw new Exception("Failed to install Composer");
                }
            } else {
                throw new Exception("Could not download Composer installer");
            }
        }
    } else {
        $composer_cmd = 'composer';
        echo "✅ Composer found: " . trim($composer_check) . "\n";
    }
    
    echo "\n📦 Installing dependencies...\n";
    
    // Run composer install
    $install_command = "cd $project_root && $composer_cmd install --no-dev --optimize-autoloader 2>&1";
    echo "Command: $install_command\n\n";
    
    $output = shell_exec($install_command);
    echo $output;
    
    // Check if vendor directory was created
    if (is_dir($project_root . '/vendor')) {
        echo "\n✅ Dependencies installed successfully!\n";
        echo "Vendor directory: " . $project_root . "/vendor\n";
        
        // Check PHPMailer specifically
        if (file_exists($project_root . '/vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
            echo "✅ PHPMailer installed correctly\n";
        } else {
            echo "❌ PHPMailer not found\n";
        }
        
        // Check autoload
        if (file_exists($project_root . '/vendor/autoload.php')) {
            echo "✅ Autoloader available\n";
            
            // Test autoload
            require_once $project_root . '/vendor/autoload.php';
            
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                echo "✅ PHPMailer class can be loaded\n";
            } else {
                echo "❌ PHPMailer class not accessible\n";
            }
        } else {
            echo "❌ Autoloader not found\n";
        }
        
    } else {
        echo "❌ Installation failed - vendor directory not created\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== INSTALLATION COMPLETE ===\n";
?> 