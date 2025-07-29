<?php
// public/admin/reports.php - Admin Analytics & Reports Page

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Ensure user is logged in AND is an admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$report_data = [
    'total_users' => 0,
    'new_users_last_30_days' => 0,
    'total_items' => 0,
    'active_auctions' => 0,
    'closed_auctions' => 0,
    'sold_auctions' => 0,
    'total_revenue' => 0.00, // From sold items
    'top_categories_by_items' => [],
    'top_sellers_by_items' => [],
    'top_bidders_by_bids' => []
];

try {
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(id) FROM users");
    $report_data['total_users'] = $stmt->fetchColumn();

    // New Users Last 30 Days
    $stmt = $pdo->query("SELECT COUNT(id) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $report_data['new_users_last_30_days'] = $stmt->fetchColumn();

    // Total Items
    $stmt = $pdo->query("SELECT COUNT(id) FROM items");
    $report_data['total_items'] = $stmt->fetchColumn();

    // Active Auctions
    $stmt = $pdo->query("SELECT COUNT(id) FROM items WHERE status = 'active' AND end_time > NOW()");
    $report_data['active_auctions'] = $stmt->fetchColumn();

    // Closed Auctions (ended, regardless of sold status)
    $stmt = $pdo->query("SELECT COUNT(id) FROM items WHERE end_time <= NOW()");
    $report_data['closed_auctions'] = $stmt->fetchColumn();

    // Sold Auctions
    $stmt = $pdo->query("SELECT COUNT(id) FROM sold_items");
    $report_data['sold_auctions'] = $stmt->fetchColumn();

    // Total Revenue from Sold Items
    $stmt = $pdo->query("SELECT SUM(final_price) FROM sold_items WHERE payment_status = 'paid'");
    $report_data['total_revenue'] = $stmt->fetchColumn() ?? 0.00;

    // Top Categories by Number of Items
    $stmt = $pdo->query("
        SELECT c.name, COUNT(i.id) AS item_count
        FROM categories c
        JOIN items i ON c.id = i.category_id
        GROUP BY c.name
        ORDER BY item_count DESC
        LIMIT 5
    ");
    $report_data['top_categories_by_items'] = $stmt->fetchAll();

    // Top Sellers by Number of Items Listed
    $stmt = $pdo->query("
        SELECT u.username, COUNT(i.id) AS item_count
        FROM users u
        JOIN items i ON u.id = i.seller_id
        GROUP BY u.username
        ORDER BY item_count DESC
        LIMIT 5
    ");
    $report_data['top_sellers_by_items'] = $stmt->fetchAll();

    // Top Bidders by Number of Bids Placed
    $stmt = $pdo->query("
        SELECT u.username, COUNT(b.id) AS bid_count
        FROM users u
        JOIN bids b ON u.id = b.bidder_id
        GROUP BY u.username
        ORDER BY bid_count DESC
        LIMIT 5
    ");
    $report_data['top_bidders_by_bids'] = $stmt->fetchAll();


} catch (PDOException $e) {
    error_log("Error fetching admin reports: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading report data. Please try again.</div>';
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
    <title>Analytics & Reports - Admin Panel</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Analytics & Reports</h2>

            <?php echo $message; // Display messages ?>

            <div class="mb-6 flex justify-between items-center">
                <a href="index.php" class="bg-olivine-2 hover:bg-moss-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">
                    &larr; Back to Admin Dashboard
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- General Stats -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 text-center">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Total Users</h3>
                    <p class="text-4xl font-bold text-reseda-green"><?php echo $report_data['total_users']; ?></p>
                    <p class="text-sm text-gray-600 mt-2">New in last 30 days: <?php echo $report_data['new_users_last_30_days']; ?></p>
                </div>
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 text-center">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Total Items</h3>
                    <p class="text-4xl font-bold text-reseda-green"><?php echo $report_data['total_items']; ?></p>
                    <p class="text-sm text-gray-600 mt-2">Active: <?php echo $report_data['active_auctions']; ?> | Closed: <?php echo $report_data['closed_auctions']; ?></p>
                </div>
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 text-center">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Total Sold Items</h3>
                    <p class="text-4xl font-bold text-reseda-green"><?php echo $report_data['sold_auctions']; ?></p>
                    <p class="text-sm text-gray-600 mt-2">Revenue: $<?php echo number_format($report_data['total_revenue'], 2); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Top Categories -->
                <div class="bg-nyanza p-6 rounded-lg shadow-md border border-olivine">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Top 5 Categories (by Items)</h3>
                    <?php if (empty($report_data['top_categories_by_items'])): ?>
                        <p class="text-gray-600">No data available.</p>
                    <?php else: ?>
                        <ul class="list-disc pl-5 text-gray-700">
                            <?php foreach ($report_data['top_categories_by_items'] as $category): ?>
                                <li class="mb-1"><?php echo htmlspecialchars($category['name']); ?>: <span class="font-semibold"><?php echo htmlspecialchars($category['item_count']); ?> items</span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Top Sellers -->
                <div class="bg-nyanza p-6 rounded-lg shadow-md border border-olivine">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Top 5 Sellers (by Items Listed)</h3>
                    <?php if (empty($report_data['top_sellers_by_items'])): ?>
                        <p class="text-gray-600">No data available.</p>
                    <?php else: ?>
                        <ul class="list-disc pl-5 text-gray-700">
                            <?php foreach ($report_data['top_sellers_by_items'] as $seller): ?>
                                <li class="mb-1"><?php echo htmlspecialchars($seller['username']); ?>: <span class="font-semibold"><?php echo htmlspecialchars($seller['item_count']); ?> items</span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Top Bidders -->
                <div class="bg-nyanza p-6 rounded-lg shadow-md border border-olivine md:col-span-2">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Top 5 Bidders (by Bids Placed)</h3>
                    <?php if (empty($report_data['top_bidders_by_bids'])): ?>
                        <p class="text-gray-600">No data available.</p>
                    <?php else: ?>
                        <ul class="list-disc pl-5 text-gray-700">
                            <?php foreach ($report_data['top_bidders_by_bids'] as $bidder): ?>
                                <li class="mb-1"><?php echo htmlspecialchars($bidder['username']); ?>: <span class="font-semibold"><?php echo htmlspecialchars($bidder['bid_count']); ?> bids</span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-reseda-green text-white text-center p-6 shadow-md mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date("Y"); ?> Online Auction System. All rights reserved.</p>
        </div>
    </footer>

    <script src="../../assets/js/script.js"></script>
</body>
</html>
