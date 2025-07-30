<?php
// Simple PHPMailer installer for live server
header('Content-Type: application/json');

try {
    $composer_exists = file_exists(__DIR__ . '/server/composer.json');
    
    if (!$composer_exists) {
        throw new Exception('composer.json not found');
    }
    
    // Read composer.json
    $composer_content = file_get_contents(__DIR__ . '/server/composer.json');
    $composer_data = json_decode($composer_content, true);
    
    if (!$composer_data) {
        throw new Exception('Invalid composer.json');
    }
    
    // Create vendor directory structure manually for PHPMailer
    $vendor_dir = __DIR__ . '/server/vendor';
    $phpmailer_dir = $vendor_dir . '/phpmailer/phpmailer/src';
    
    // Create directories
    if (!is_dir($vendor_dir)) {
        mkdir($vendor_dir, 0755, true);
    }
    if (!is_dir($phpmailer_dir)) {
        mkdir($phpmailer_dir, 0755, true);
    }
    
    // Download PHPMailer files directly (simple approach)
    $phpmailer_files = [
        'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
        'SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
        'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
    ];
    
    $downloaded = 0;
    foreach ($phpmailer_files as $filename => $url) {
        $target = $phpmailer_dir . '/' . $filename;
        if (!file_exists($target)) {
            $content = @file_get_contents($url);
            if ($content) {
                file_put_contents($target, $content);
                $downloaded++;
            }
        }
    }
    
    // Create autoload.php
    $autoload_content = '<?php
// Simple autoloader for PHPMailer
spl_autoload_register(function ($class) {
    $prefix = \'PHPMailer\\\\PHPMailer\\\\\';
    $base_dir = __DIR__ . \'/phpmailer/phpmailer/src/\';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace(\'\\\\\', \'/\', $relative_class) . \'.php\';
    
    if (file_exists($file)) {
        require $file;
    }
});
';
    
    file_put_contents($vendor_dir . '/autoload.php', $autoload_content);
    
    echo json_encode([
        'success' => true,
        'message' => 'PHPMailer installed successfully',
        'files_downloaded' => $downloaded,
        'autoload_created' => file_exists($vendor_dir . '/autoload.php')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>