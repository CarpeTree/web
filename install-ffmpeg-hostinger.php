<?php
// Install and test ffmpeg on Hostinger
header('Content-Type: text/plain');
echo "=== FFMPEG INSTALLATION ON HOSTINGER ===\n";

// 1. Check current environment
echo "1. ENVIRONMENT CHECK:\n";
echo "PHP Version: " . phpversion() . "\n";
echo "OS: " . php_uname() . "\n";
echo "User: " . get_current_user() . "\n";
echo "Current directory: " . getcwd() . "\n";
echo "Home directory: " . (getenv('HOME') ?: 'Not set') . "\n";

// 2. Check available functions
echo "\n2. FUNCTION AVAILABILITY:\n";
$functions = ['shell_exec', 'exec', 'system', 'passthru'];
foreach ($functions as $func) {
    $available = function_exists($func) && !in_array($func, explode(',', ini_get('disable_functions')));
    echo "{$func}: " . ($available ? "✅ Available" : "❌ Disabled") . "\n";
}

// 3. Check if ffmpeg already exists
echo "\n3. CHECKING EXISTING FFMPEG:\n";
$ffmpeg_paths = [
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg',
    '/opt/local/bin/ffmpeg',
    '/home/' . get_current_user() . '/bin/ffmpeg',
    getcwd() . '/ffmpeg'
];

foreach ($ffmpeg_paths as $path) {
    if (file_exists($path)) {
        echo "✅ Found ffmpeg at: {$path}\n";
        if (is_executable($path)) {
            echo "   ✅ Executable\n";
            if (function_exists('shell_exec')) {
                $version = shell_exec("{$path} -version 2>&1");
                if ($version) {
                    $first_line = strtok($version, "\n");
                    echo "   Version: {$first_line}\n";
                }
            }
        } else {
            echo "   ❌ Not executable\n";
        }
    } else {
        echo "❌ Not found: {$path}\n";
    }
}

// 4. Try to install ffmpeg in user directory
echo "\n4. FFMPEG INSTALLATION ATTEMPT:\n";

if (!function_exists('shell_exec')) {
    echo "❌ shell_exec() disabled - cannot install ffmpeg\n";
    exit;
}

$user_bin = '/home/' . get_current_user() . '/bin';
$ffmpeg_local = $user_bin . '/ffmpeg';

// Create user bin directory
if (!is_dir($user_bin)) {
    echo "Creating user bin directory: {$user_bin}\n";
    $result = shell_exec("mkdir -p {$user_bin} 2>&1");
    echo "mkdir result: {$result}\n";
}

// Check if we can download files
echo "\n5. DOWNLOAD ATTEMPT:\n";

// Try downloading static ffmpeg binary
$ffmpeg_url = "https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz";
$temp_file = "/tmp/ffmpeg-static.tar.xz";

echo "Attempting to download ffmpeg static binary...\n";
echo "URL: {$ffmpeg_url}\n";
echo "Temp file: {$temp_file}\n";

// Try wget first
$wget_result = shell_exec("wget --version 2>&1");
if (strpos($wget_result, 'GNU Wget') !== false) {
    echo "✅ wget available\n";
    echo "Downloading with wget...\n";
    $download_cmd = "wget -O {$temp_file} {$ffmpeg_url} 2>&1";
    $download_result = shell_exec($download_cmd);
    echo "Download result: {$download_result}\n";
} else {
    echo "❌ wget not available\n";
    
    // Try curl
    $curl_result = shell_exec("curl --version 2>&1");
    if (strpos($curl_result, 'curl') !== false) {
        echo "✅ curl available\n";
        echo "Downloading with curl...\n";
        $download_cmd = "curl -L -o {$temp_file} {$ffmpeg_url} 2>&1";
        $download_result = shell_exec($download_cmd);
        echo "Download result: {$download_result}\n";
    } else {
        echo "❌ curl not available\n";
        echo "❌ Cannot download ffmpeg - no download tools available\n";
        exit;
    }
}

// Check if download succeeded
if (file_exists($temp_file)) {
    $file_size = filesize($temp_file);
    echo "✅ Download successful - File size: " . round($file_size / 1024 / 1024, 1) . "MB\n";
    
    // Extract the archive
    echo "\n6. EXTRACTION:\n";
    $extract_dir = "/tmp/ffmpeg-extract";
    $extract_cmd = "cd /tmp && tar -xf {$temp_file} 2>&1";
    echo "Extraction command: {$extract_cmd}\n";
    $extract_result = shell_exec($extract_cmd);
    echo "Extract result: {$extract_result}\n";
    
    // Find the extracted ffmpeg binary
    $find_cmd = "find /tmp -name ffmpeg -type f 2>&1";
    $find_result = shell_exec($find_cmd);
    echo "Find ffmpeg: {$find_result}\n";
    
    $ffmpeg_lines = explode("\n", trim($find_result));
    foreach ($ffmpeg_lines as $line) {
        $line = trim($line);
        if (!empty($line) && file_exists($line) && is_executable($line)) {
            echo "✅ Found executable ffmpeg: {$line}\n";
            
            // Copy to user bin
            $copy_cmd = "cp {$line} {$ffmpeg_local} 2>&1";
            $copy_result = shell_exec($copy_cmd);
            echo "Copy result: {$copy_result}\n";
            
            if (file_exists($ffmpeg_local)) {
                echo "✅ ffmpeg installed at: {$ffmpeg_local}\n";
                
                // Make executable
                $chmod_result = shell_exec("chmod +x {$ffmpeg_local} 2>&1");
                echo "chmod result: {$chmod_result}\n";
                
                // Test it
                echo "\n7. TESTING INSTALLED FFMPEG:\n";
                $test_result = shell_exec("{$ffmpeg_local} -version 2>&1");
                echo "Version test: " . substr($test_result, 0, 200) . "...\n";
                
                if (strpos($test_result, 'ffmpeg version') !== false) {
                    echo "🎉 SUCCESS! ffmpeg is working!\n";
                    
                    // Clean up
                    shell_exec("rm -rf /tmp/ffmpeg-* 2>&1");
                    
                    echo "\n8. FINAL TEST WITH SAMPLE:\n";
                    echo "ffmpeg path for MediaPreprocessor: {$ffmpeg_local}\n";
                    echo "Add this path to your MediaPreprocessor paths array.\n";
                } else {
                    echo "❌ ffmpeg test failed\n";
                }
            } else {
                echo "❌ Failed to copy ffmpeg to user bin\n";
            }
            break;
        }
    }
    
} else {
    echo "❌ Download failed - temp file not found\n";
}

echo "\n=== INSTALLATION COMPLETE ===\n";
?>