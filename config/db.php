<?php
// config/db.php - Database connection using PDO for the Online Auction System

// Define database connection constants
// IMPORTANT: For production environments, these should be stored securely
// (e.g., environment variables) and not directly in the code.
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Default XAMPP MySQL user
define('DB_PASS', '');     // Default XAMPP MySQL password (empty)
define('DB_NAME', 'online_auction_db'); // Name of your database

// Establish a database connection using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Disable emulation for better security and performance
    ];

    // Create a new PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // the line below IS FOR  debugging to confirm connection IF NOT NEEDED CAN BE COMMEMTED 
    // echo "Database connection successful!";

} catch (PDOException $e) {
    // If connection fails, log the error and terminate the script
    // In a production environment, you would log this to a file and show a generic error message to the user
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// The $pdo object is now available for use in other PHP files that include db.php
?>
