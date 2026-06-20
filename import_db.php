<?php
/**
 * import_db.php
 * Automatically imports the database.sql schema and dummy data into MySQL.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Import Utility</h2>";

// Database parameters without database name (to create it first if needed)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // 1. Connect without db name to ensure we can create it
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // 2. Read SQL file contents
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("database.sql file not found at: " . $sqlFile);
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 3. Execute the SQL queries
    echo "Importing database schema from database.sql...<br>";
    $pdo->exec($sql);
    
    echo "✅ <strong>Success!</strong> Database and tables created and seeded successfully.<br>";
    echo "<a href='login.php'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "❌ <strong>Import Failed:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
}
?>
