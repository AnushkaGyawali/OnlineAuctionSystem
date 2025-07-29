<?php
// public/admin/index.php - Admin Dashboard for Online Auction System

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Ensure user is logged in AND is an admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    // If not logged in or not admin, redirect to login page
    header('Location: ../login.php');
    exit();
}

// Fetch some basic stats for the dashboard
$total_users = 0;
$total_active_auctions = 0;
$total_sold_items = 0;

try {
    // Total Users
    $stmt_users = $pdo->query("SELECT COUNT(id) FROM users");
    $total_users = $stmt_users->fetchColumn();

    // Total Active Auctions
    $stmt_active_auctions = $pdo->query("SELECT COUNT(id) FROM items WHERE status = 'active' AND end_time > NOW()");
    $total_active_auctions = $stmt_active_auctions->fetchColumn();

    // Total Sold Items
    $stmt_sold_items = $pdo->query("SELECT COUNT(id) FROM sold_items");
    $total_sold_items = $stmt_sold_items->fetchColumn();

} catch (PDOException $e) {
    error_log("Error fetching admin dashboard stats: " . $e->getMessage());
    // Display a user-friendly message, but log the detailed error
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading dashboard statistics.</div>';
}

// Determine navigation links (consistent for admin pages)
$nav_links = '
    <li><a href="../index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Home</a></li>
    <li><a href="../browse_auctions.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Browse Auctions</a></li>
    <li><a href="../add_item.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Sell Item</a></li>
    <li><a href="../dashboard.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">User Dashboard</a></li>
    <li><a href="index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Admin Panel</a></li>
    <li><a href="../dashboard.php?action=logout" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Logout</a></li>
';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online Auction System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="font-sans antialiased flex flex-col min-h-screen bg-gradient-to-br from-nyanza to-tea-green text-gray-800">
    <header class="bg-reseda-green text-white p-4 shadow-md">
        <nav class="container mx-auto flex justify-between items-center flex-wrap">
            <a href="../index.php" class="text-2xl font-bold text-white py-2">AuctionHub</a>
            <ul class="flex space-x-6 flex-wrap justify-center mt-4 md:mt-0">
                <?php echo $nav_links; ?>
            </ul>
        </nav>
    </header>

    <main class="flex-grow p-4">
        <div class="container mx-auto bg-white p-8 rounded-lg shadow-xl border border-olivine-2">
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Admin Dashboard</h2>

            <?php echo $message ?? ''; // Display messages ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Stat Card: Total Users -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 text-center">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Total Users</h3>
                    <p class="text-4xl font-bold text-reseda-green"><?php echo $total_users; ?></p>
                    <a href="manage_users.php" class="text-moss-green hover:underline mt-2 block">Manage Users</a>
                </div>

                <!-- Stat Card: Active Auctions -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 text-center">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Active Auctions</h3>
                    <p class="text-4xl font-bold text-reseda-green"><?php echo $total_active_auctions; ?></p>
                    <a href="manage_items.php" class="text-moss-green hover:underline mt-2 block">Manage Auctions</a>
                </div>

                <!-- Stat Card: Sold Items -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 text-center">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Sold Items</h3>
                    <p class="text-4xl font-bold text-reseda-green"><?php echo $total_sold_items; ?></p>
                    <a href="reports.php" class="text-moss-green hover:underline mt-2 block">View Sales</a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Admin Action Card: Categories -->
                <div class="bg-nyanza p-6 rounded-lg shadow-md border border-olivine">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Category Management</h3>
                    <p class="text-gray-700 mb-4">Add, edit, or delete auction categories.</p>
                    <a href="manage_categories.php" class="bg-olivine-2 hover:bg-moss-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">Go to Categories</a>
                </div>

                <!-- Admin Action Card: Reports -->
                <div class="bg-nyanza p-6 rounded-lg shadow-md border border-olivine">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Generate Reports</h3>
                    <p class="text-gray-700 mb-4">Access detailed reports on platform activity.</p>
                    <a href="reports.php" class="bg-olivine-2 hover:bg-moss-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">View Reports</a>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-reseda-green text-white text-center p-6 shadow-md mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date(format: "Y"); ?> Online Auction System. All rights reserved.</p>
        </div>
    </footer>

    <script src="../../assets/js/script.js"></script>
</body>
</html>
