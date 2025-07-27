<?php
// public/dashboard.php - User Dashboard Page for Online Auction System

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in, otherwise redirect to login page
requireLogin();

// Fetch user data from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$is_admin = $_SESSION['is_admin'];

// Logout logic
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
    header('Location: index.php'); // Redirect to home page after logout
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Online Auction System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="font-sans antialiased flex flex-col min-h-screen bg-gradient-to-br from-nyanza to-tea-green text-gray-800">
    <header class="bg-reseda-green text-white p-4 shadow-md">
        <nav class="container mx-auto flex justify-between items-center flex-wrap">
            <a href="index.php" class="text-2xl font-bold text-white py-2">AuctionHub</a>
            <ul class="flex space-x-6 flex-wrap justify-center mt-4 md:mt-0">
                <li><a href="index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Home</a></li>
                <li><a href="browse_auctions.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Browse Auctions</a></li>
                <li><a href="add_item.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Sell Item</a></li>
                <li><a href="dashboard.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Dashboard</a></li>
                <li><a href="dashboard.php?action=logout" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="flex-grow p-4">
        <div class="container mx-auto bg-white p-8 rounded-lg shadow-xl border border-olivine-2">
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Welcome to Your Dashboard, <?php echo htmlspecialchars($username); ?>!</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Dashboard Card: Active Listings -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Your Active Listings</h3>
                    <p class="text-gray-700">You currently have 0 items listed for auction.</p>
                    <a href="add_item.php" class="text-reseda-green hover:underline mt-2 block">List a New Item</a>
                </div>

                <!-- Dashboard Card: Your Bids -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Your Active Bids</h3>
                    <p class="text-gray-700">You are currently bidding on 0 items.</p>
                    <a href="browse_auctions.php" class="text-reseda-green hover:underline mt-2 block">Browse Auctions to Bid</a>
                </div>

                <!-- Dashboard Card: Profile Settings -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Profile Settings</h3>
                    <p class="text-gray-700">Manage your account details and preferences.</p>
                    <a href="#" class="text-reseda-green hover:underline mt-2 block">Edit Profile</a>
                </div>

                <?php if ($is_admin): ?>
                <!-- Dashboard Card: Admin Panel Access (only for admins) -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 col-span-full">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Admin Panel</h3>
                    <p class="text-gray-700">Access administrative controls for users, items, and categories.</p>
                    <a href="#" class="text-reseda-green hover:underline mt-2 block">Go to Admin Panel</a>
                </div>
                <?php endif; ?>
            </div>
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
