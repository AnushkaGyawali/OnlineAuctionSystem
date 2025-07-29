<?php
// public/watchlist.php - User Watchlist Page

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in
requireLogin();

$message = '';
$current_user_id = $_SESSION['user_id'];
$watched_items = [];

// Handle add/remove from watchlist action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id'] ?? 0);
    if ($item_id > 0) {
        if (isset($_POST['add_to_watchlist'])) {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO watchlists (user_id, item_id) VALUES (?, ?)");
                $stmt->execute([$current_user_id, $item_id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Item added to watchlist!</div>';
            } catch (PDOException $e) {
                error_log("Error adding to watchlist: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to add item to watchlist.</div>';
            }
        } elseif (isset($_POST['remove_from_watchlist'])) {
            try {
                $stmt = $pdo->prepare("DELETE FROM watchlists WHERE user_id = ? AND item_id = ?");
                $stmt->execute([$current_user_id, $item_id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Item removed from watchlist!</div>';
            } catch (PDOException $e) {
                error_log("Error removing from watchlist: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to remove item from watchlist.</div>';
            }
        }
    }
}

// Fetch watched items for the current user
try {
    $stmt = $pdo->prepare("
        SELECT
            i.id,
            i.title,
            i.description,
            i.current_bid,
            i.start_price,
            i.end_time,
            i.status,
            i.image_urls,
            u.username AS seller_username
        FROM
            watchlists w
        JOIN
            items i ON w.item_id = i.id
        JOIN
            users u ON i.seller_id = u.id
        WHERE
            w.user_id = ?
        ORDER BY
            i.end_time ASC
    ");
    $stmt->execute([$current_user_id]);
    $watched_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching watchlist: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading your watchlist. Please try again.</div>';
}

// Determine navigation links based on login status
$nav_links = '';
if (isLoggedIn()) {
    $nav_links = '
        <li><a href="index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Home</a></li>
        <li><a href="browse_auctions.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Browse Auctions</a></li>
        <li><a href="add_item.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Sell Item</a></li>
        <li><a href="dashboard.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Dashboard</a></li>
        <li><a href="profile.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Profile</a></li>
        <li><a href="watchlist.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Watchlist</a></li>
        <li><a href="notifications.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Notifications</a></li>
        <li><a href="messages.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Messages</a></li>
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
    <title>My Watchlist - Online Auction System</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-8">My Watchlist</h2>

            <?php echo $message; // Display messages ?>

            <?php if (empty($watched_items)): ?>
                <p class="text-center text-gray-600 text-lg">Your watchlist is empty. <a href="browse_auctions.php" class="text-moss-green hover:underline">Browse auctions</a> to add items!</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($watched_items as $item):
                        $image_urls = json_decode($item['image_urls'] ?? '[]', true);
                        $display_image = !empty($image_urls[0]) ? htmlspecialchars($image_urls[0]) : 'https://placehold.co/400x300/D0F0C0/6B8E23?text=No+Image';
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
                                    <p class="text-sm text-gray-600 mt-1">
                                        Status: <span class="font-medium px-2 py-1 rounded-full text-xs font-semibold
                                            <?php
                                            if ($item['status'] === 'active' && strtotime($item['end_time']) > time()) {
                                                echo 'bg-green-200 text-green-800';
                                            } elseif ($item['status'] === 'sold') {
                                                echo 'bg-blue-200 text-blue-800';
                                            } elseif (strtotime($item['end_time']) <= time() && $item['status'] === 'active') {
                                                echo 'bg-yellow-200 text-yellow-800';
                                            } else {
                                                echo 'bg-gray-200 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars(ucfirst($item['status'])); ?>
                                        </span>
                                    </p>
                                    <div class="flex flex-col sm:flex-row gap-2 mt-4">
                                        <a href="item_detail.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="block bg-moss-green text-white px-3 py-2 rounded-lg text-center text-sm hover:bg-reseda-green transition-colors duration-300 flex-grow">View Details</a>
                                        <form action="watchlist.php" method="POST" class="flex-grow">
                                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['id']); ?>">
                                            <button type="submit" name="remove_from_watchlist" class="w-full bg-red-500 text-white px-3 py-2 rounded-lg text-center text-sm hover:bg-red-600 transition-colors duration-300">Remove</button>
                                        </form>
                                    </div>
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
