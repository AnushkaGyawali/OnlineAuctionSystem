<?php
// public/browse_auctions.php - Page to browse active auction items

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php'; // For isLoggedIn() and navigation

$message = '';
$active_items = [];

try {
    // Fetch active items, ordered by end_time (soonest ending first)
    // Also fetch seller's username for display
    $stmt = $pdo->prepare("
        SELECT
            i.id,
            i.title,
            i.description,
            i.current_bid,
            i.start_price,
            i.end_time,
            i.image_urls,
            u.username as seller_username
        FROM
            items i
        JOIN
            users u ON i.seller_id = u.id
        WHERE
            i.status = 'active' AND i.end_time > NOW()
        ORDER BY
            i.end_time ASC
    ");
    $stmt->execute();
    $active_items = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching active items: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading auction items. Please try again.</div>';
}

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
    <title>Browse Auctions - Online Auction System</title>
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

    <main class="flex-grow p-4">
        <div class="container mx-auto bg-white p-8 rounded-lg shadow-xl border border-olivine-2">
            <h2 class="text-3xl font-bold text-center text-moss-green mb-8">Active Auctions</h2>

            <?php echo $message; // Display messages ?>

            <?php if (empty($active_items)): ?>
                <p class="text-center text-gray-600 text-lg">No active auctions found at the moment. Check back later!</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($active_items as $item):
                        // Decode image_urls JSON, fallback to empty array if invalid or null
                        $image_urls = json_decode($item['image_urls'] ?? '[]', true);
                        $display_image = !empty($image_urls[0]) ? htmlspecialchars($image_urls[0]) : 'https://placehold.co/400x300/D0F0C0/6B8E23?text=No+Image'; // Placeholder image
                    ?>
                        <div class="bg-tea-green rounded-lg shadow-md overflow-hidden border border-olivine-2 flex flex-col">
                            <img src="<?php echo $display_image; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-48 object-cover">
                            <div class="p-4 flex flex-col flex-grow">
                                <h3 class="text-xl font-semibold text-moss-green mb-2 truncate"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <p class="text-sm text-gray-600 mb-2">Seller: <?php echo htmlspecialchars($item['seller_username']); ?></p>
                                <p class="text-gray-700 text-sm mb-3 line-clamp-3"><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="mt-auto pt-2 border-t border-olivine-2">
                                    <p class="text-lg font-bold text-reseda-green">
                                        Current Bid: $<?php echo number_format($item['current_bid'] ?? $item['start_price'], 2); ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Ends: <span class="font-medium"><?php echo date('M j, Y H:i', strtotime($item['end_time'])); ?></span>
                                    </p>
                                    <a href="item_detail.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="mt-4 block bg-moss-green text-white px-4 py-2 rounded-lg text-center hover:bg-reseda-green transition-colors duration-300">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
