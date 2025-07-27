<?php
// public/index.php - Main entry point for the Online Auction System

// Start session (needed for user authentication later)
session_start();

// Include database configuration
require_once __DIR__ . '/../config/db.php';
// Include authentication functions to check login status
require_once __DIR__ . '/../includes/auth_functions.php';

// Determine navigation links based on login status
$nav_links = '';
if (isLoggedIn()) {
    $nav_links = '
        <li><a href="index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Home</a></li>
        <li><a href="browse_auctions.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Browse Auctions</a></li>
        <li><a href="add_item.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Sell Item</a></li>
        <li><a href="dashboard.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Dashboard</a></li>
        <li><a href="dashboard.php?action=logout" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Logout</a></li>
    ';
} else {
    $nav_links = '
        <li><a href="index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Home</a></li>
        <li><a href="browse_auctions.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Browse Auctions</a></li>
        <li><a href="login.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Login</a></li>
        <li><a href="register.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Register</a></li>
    ';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Auction System</title>
    <!-- Link to the main stylesheet (compiled with Tailwind) -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="font-sans antialiased flex flex-col min-h-screen bg-gradient-to-br from-nyanza to-tea-green text-gray-800">
    <header class="bg-reseda-green text-white p-4 shadow-md">
        <nav class="container mx-auto flex justify-between items-center flex-wrap">
            <a href="index.php" class="text-2xl font-bold text-white py-2">AuctionHub</a>
            <ul class="flex space-x-6 flex-wrap justify-center mt-4 md:mt-0">
                <?php echo $nav_links; ?>
            </ul>
        </nav>
    </header>

    <main class="container mx-auto text-center py-16 flex-grow flex flex-col justify-center items-center">
        <h1 class="text-5xl text-moss-green mb-4 leading-tight">Welcome to the Online Auction System</h1>
        <p class="text-xl max-w-2xl mb-8">Your trusted marketplace for buying and selling unique items.</p>
        <div class="flex flex-wrap justify-center">
            <a href="browse_auctions.php" class="bg-moss-green text-white px-8 py-3 rounded-lg shadow-md hover:bg-reseda-green transform hover:-translate-y-1 transition-all duration-300 m-2">Explore Auctions</a>
            <a href="add_item.php" class="bg-olivine text-gray-800 border border-olivine-2 px-8 py-3 rounded-lg shadow-md hover:bg-olivine-2 transform hover:-translate-y-1 transition-all duration-300 m-2">List an Item</a>
        </div>
    </main>

    <footer class="bg-reseda-green text-white text-center p-6 shadow-md mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date("Y"); ?> Online Auction System. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>
