<!DOCTYPE html>
<html>
<head>
    <title>Carpe Tree'em Setup Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🌳 Carpe Tree'em Setup Test</h1>
    
    <h2>System Requirements Check</h2>
    
    <?php
    echo "<p><strong>PHP Version:</strong> " . phpversion();
    if (version_compare(phpversion(), '7.4.0', '>=')) {
        echo " <span class='success'>✅ Good</span></p>";
    } else {
        echo " <span class='error'>❌ Need PHP 7.4+</span></p>";
    }
    
    echo "<p><strong>PDO Extension:</strong> ";
    if (extension_loaded('pdo')) {
        echo "<span class='success'>✅ Available</span></p>";
    } else {
        echo "<span class='error'>❌ Missing</span></p>";
    }
    
    echo "<p><strong>MySQL PDO:</strong> ";
    if (extension_loaded('pdo_mysql')) {
        echo "<span class='success'>✅ Available</span></p>";
    } else {
        echo "<span class='error'>❌ Missing</span></p>";
    }
    
    echo "<p><strong>File Uploads:</strong> ";
    if (ini_get('file_uploads')) {
        echo "<span class='success'>✅ Enabled</span></p>";
        echo "<p><strong>Max Upload Size:</strong> " . ini_get('upload_max_filesize') . "</p>";
        echo "<p><strong>Max POST Size:</strong> " . ini_get('post_max_size') . "</p>";
    } else {
        echo "<span class='error'>❌ Disabled</span></p>";
    }
    ?>
    
    <h2>Directory Permissions</h2>
    <?php
    $dirs = ['uploads', 'server'];
    foreach ($dirs as $dir) {
        echo "<p><strong>$dir/:</strong> ";
        if (is_dir($dir)) {
            if (is_writable($dir)) {
                echo "<span class='success'>✅ Writable</span></p>";
            } else {
                echo "<span class='error'>❌ Not writable</span></p>";
            }
        } else {
            echo "<span class='error'>❌ Missing</span></p>";
        }
    }
    ?>
    
    <h2>Database Connection Test</h2>
    <?php
    try {
        include 'server/config/database-simple.php';
        echo "<p class='success'>✅ Database connection successful!</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
        echo "<div class='info'>";
        echo "<strong>To fix this:</strong><br>";
        echo "1. Make sure MySQL is running<br>";
        echo "2. Create database 'carpe_tree_quotes'<br>";
        echo "3. Update credentials in server/config/database-simple.php<br>";
        echo "</div>";
    }
    ?>
    
    <div class='info'>
        <h3>Next Steps:</h3>
        <ol>
            <li><strong>Install PHP & Composer:</strong>
                <pre>brew install php composer</pre>
            </li>
            <li><strong>Install dependencies:</strong>
                <pre>cd server && composer require phpmailer/phpmailer tecnickcom/tcpdf vlucas/phpdotenv</pre>
            </li>
            <li><strong>Create database:</strong>
                <pre>mysql -u root -p -e "CREATE DATABASE carpe_tree_quotes;"</pre>
            </li>
            <li><strong>Import schema:</strong>
                <pre>mysql -u root -p carpe_tree_quotes < server/database/schema.sql</pre>
            </li>
            <li><strong>Test quote form:</strong> <a href="quote.html">quote.html</a></li>
        </ol>
    </div>
</body>
</html> 