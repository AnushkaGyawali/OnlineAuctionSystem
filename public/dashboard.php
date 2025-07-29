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

$user_listings = [];
$user_bids = [];
$feedback_opportunities = [];
$won_items = []; // New: To display won items for payment/shipping
$message = '';

// Logout logic
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
    header('Location: index.php'); // Redirect to home page after logout
    exit();
}

// Display success message if redirected from Buy Now or Profile
if (isset($_SESSION['success_message'])) {
    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']); // Clear the message after displaying
}


// --- Fetch User's Active Listings ---
try {
    $stmt_listings = $pdo->prepare("
        SELECT
            id,
            title,
            current_bid,
            start_price,
            end_time,
            status
        FROM
            items
        WHERE
            seller_id = ?
        ORDER BY
            end_time DESC
    ");
    $stmt_listings->execute([$user_id]);
    $user_listings = $stmt_listings->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching user listings: " . $e->getMessage());
    $message .= '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading your listings.</div>';
}

// --- Fetch User's Active Bids ---
try {
    // Select distinct items the user has bid on, and their highest bid on each
    $stmt_bids = $pdo->prepare("
        SELECT
            i.id,
            i.title,
            i.current_bid,
            i.end_time,
            i.highest_bidder_id,
            MAX(b.bid_amount) AS user_highest_bid_on_item,
            u.username AS seller_username
        FROM
            bids b
        JOIN
            items i ON b.item_id = i.id
        JOIN
            users u ON i.seller_id = u.id
        WHERE
            b.bidder_id = ? AND i.status = 'active' AND i.end_time > NOW()
        GROUP BY
            i.id, i.title, i.current_bid, i.end_time, i.highest_bidder_id, u.username
        ORDER BY
            i.end_time ASC
    ");
    $stmt_bids->execute([$user_id]);
    $user_bids = $stmt_bids->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching user bids: " . $e->getMessage());
    $message .= '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading your bids.</div>';
}

// --- Fetch Won Items for Payment/Shipping ---
try {
    $stmt_won_items = $pdo->prepare("
        SELECT
            si.item_id,
            i.title,
            si.final_price,
            si.payment_status,
            si.shipping_status,
            u.username AS seller_username
        FROM
            sold_items si
        JOIN
            items i ON si.item_id = i.id
        JOIN
            users u ON i.seller_id = u.id
        WHERE
            si.buyer_id = ?
        ORDER BY
            si.transaction_date DESC
    ");
    $stmt_won_items->execute([$user_id]);
    $won_items = $stmt_won_items->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching won items: " . $e->getMessage());
    $message .= '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading your won items.</div>';
}


// --- Fetch Feedback Opportunities ---
try {
    // Opportunities where current user was buyer and needs to rate seller
    $stmt_buyer_feedback = $pdo->prepare("
        SELECT
            si.item_id,
            i.title,
            si.seller_id AS partner_id,
            u.username AS partner_username,
            'buyer_to_seller' AS feedback_type
        FROM
            sold_items si
        JOIN
            items i ON si.item_id = i.id
        JOIN
            users u ON si.seller_id = u.id
        WHERE
            si.buyer_id = ?
            AND NOT EXISTS (SELECT 1 FROM feedback_profiles WHERE item_id = si.item_id AND rater_id = ? AND user_id = si.seller_id AND feedback_type = 'buyer_to_seller')
    ");
    $stmt_buyer_feedback->execute([$user_id, $user_id]);
    $feedback_opportunities = array_merge($feedback_opportunities, $stmt_buyer_feedback->fetchAll());

    // Opportunities where current user was seller and needs to rate buyer
    $stmt_seller_feedback = $pdo->prepare("
        SELECT
            si.item_id,
            i.title,
            si.buyer_id AS partner_id,
            u.username AS partner_username,
            'seller_to_buyer' AS feedback_type
        FROM
            sold_items si
        JOIN
            items i ON si.item_id = i.id
        JOIN
            users u ON si.buyer_id = u.id
        WHERE
            si.seller_id = ?
            AND si.buyer_id IS NOT NULL -- Only if there was a buyer
            AND NOT EXISTS (SELECT 1 FROM feedback_profiles WHERE item_id = si.item_id AND rater_id = ? AND user_id = si.buyer_id AND feedback_type = 'seller_to_buyer')
    ");
    $stmt_seller_feedback->execute([$user_id, $user_id]);
    $feedback_opportunities = array_merge($feedback_opportunities, $stmt_seller_feedback->fetchAll());

} catch (PDOException $e) {
    error_log("Error fetching feedback opportunities: " . $e->getMessage());
    $message .= '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading feedback opportunities.</div>';
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
    <title>Dashboard - Online Auction System</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Welcome to Your Dashboard, <?php echo htmlspecialchars($username); ?>!</h2>

            <?php echo $message; // Display messages ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Dashboard Card: Active Listings -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Your Active Listings (<?php echo count($user_listings); ?>)</h3>
                    <?php if (empty($user_listings)): ?>
                        <p class="text-gray-700">You currently have no items listed for auction.</p>
                        <a href="add_item.php" class="text-reseda-green hover:underline mt-2 block">List a New Item</a>
                    <?php else: ?>
                        <ul class="list-disc pl-5 text-gray-700">
                            <?php foreach ($user_listings as $listing): ?>
                                <li class="mb-2">
                                    <a href="item_detail.php?id=<?php echo htmlspecialchars($listing['id']); ?>" class="text-reseda-green hover:underline font-medium">
                                        <?php echo htmlspecialchars($listing['title']); ?>
                                    </a>
                                    - Current Bid: $<?php echo number_format($listing['current_bid'] ?? $listing['start_price'], 2); ?>
                                    (Ends: <?php echo date('M j, H:i', strtotime($listing['end_time'])); ?>)
                                    <span class="text-sm font-semibold <?php echo ($listing['status'] === 'active' && strtotime($listing['end_time']) > time()) ? 'text-green-600' : 'text-red-600'; ?>">
                                        (<?php echo htmlspecialchars(ucfirst($listing['status'])); ?>)
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="add_item.php" class="text-reseda-green hover:underline mt-4 block">List Another Item</a>
                    <?php endif; ?>
                </div>

                <!-- Dashboard Card: Your Active Bids -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Your Active Bids (<?php echo count($user_bids); ?>)</h3>
                    <?php if (empty($user_bids)): ?>
                        <p class="text-gray-700">You are currently not actively bidding on any items.</p>
                        <a href="browse_auctions.php" class="text-reseda-green hover:underline mt-2 block">Browse Auctions to Bid</a>
                    <?php else: ?>
                        <ul class="list-disc pl-5 text-gray-700">
                            <?php foreach ($user_bids as $bid_item): ?>
                                <li class="mb-2">
                                    <a href="item_detail.php?id=<?php echo htmlspecialchars($bid_item['id']); ?>" class="text-reseda-green hover:underline font-medium">
                                        <?php echo htmlspecialchars($bid_item['title']); ?>
                                    </a>
                                    - Your Highest Bid: $<?php echo number_format($bid_item['user_highest_bid_on_item'], 2); ?>
                                    (Current Auction Bid: $<?php echo number_format($bid_item['current_bid'] ?? $bid_item['start_price'], 2); ?>)
                                    <span class="text-sm font-semibold <?php echo ($bid_item['highest_bidder_id'] == $user_id) ? 'text-green-600' : 'text-red-600'; ?>">
                                        (<?php echo ($bid_item['highest_bidder_id'] == $user_id) ? 'Highest' : 'Outbid'; ?>)
                                    </span>
                                    (Ends: <?php echo date('M j, H:i', strtotime($bid_item['end_time'])); ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="browse_auctions.php" class="text-reseda-green hover:underline mt-4 block">Browse More Auctions</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dashboard Card: Won Items & Payment Status -->
            <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 mb-8">
                <h3 class="text-xl font-semibold text-moss-green mb-3">Your Won Items (<?php echo count($won_items); ?>)</h3>
                <?php if (empty($won_items)): ?>
                    <p class="text-gray-700">You haven't won any auctions yet.</p>
                    <a href="browse_auctions.php" class="text-reseda-green hover:underline mt-2 block">Browse Auctions</a>
                <?php else: ?>
                    <ul class="list-disc pl-5 text-gray-700">
                        <?php foreach ($won_items as $won_item): ?>
                            <li class="mb-2 flex items-center justify-between flex-wrap">
                                <div>
                                    <a href="item_detail.php?id=<?php echo htmlspecialchars($won_item['item_id']); ?>" class="text-reseda-green hover:underline font-medium">
                                        <?php echo htmlspecialchars($won_item['title']); ?>
                                    </a>
                                    - Final Price: $<?php echo number_format($won_item['final_price'], 2); ?>
                                    <br>
                                    <span class="text-sm text-gray-600">Seller: <?php echo htmlspecialchars($won_item['seller_username']); ?></span>
                                    <span class="text-sm font-semibold ml-2 px-2 py-1 rounded-full
                                        <?php
                                        if ($won_item['payment_status'] === 'paid') {
                                            echo 'bg-green-200 text-green-800';
                                        } else {
                                            echo 'bg-red-200 text-red-800';
                                        }
                                        ?>">
                                        Payment: <?php echo htmlspecialchars(ucfirst($won_item['payment_status'])); ?>
                                    </span>
                                    <span class="text-sm font-semibold ml-2 px-2 py-1 rounded-full
                                        <?php
                                        if ($won_item['shipping_status'] === 'shipped') {
                                            echo 'bg-blue-200 text-blue-800';
                                        } else {
                                            echo 'bg-yellow-200 text-yellow-800';
                                        }
                                        ?>">
                                        Shipping: <?php echo htmlspecialchars(ucfirst($won_item['shipping_status'])); ?>
                                    </span>
                                </div>
                                <?php if ($won_item['payment_status'] === 'pending'): ?>
                                    <a href="process_payment.php?item_id=<?php echo htmlspecialchars($won_item['item_id']); ?>" class="bg-moss-green hover:bg-reseda-green text-white text-sm px-3 py-1 rounded-lg mt-2 sm:mt-0 transition-colors duration-300">Pay Now</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Dashboard Card: Feedback Opportunities -->
            <?php if (!empty($feedback_opportunities)): ?>
                <div class="bg-yellow-100 p-6 rounded-lg shadow-md border border-yellow-400 mb-8">
                    <h3 class="text-xl font-semibold text-yellow-800 mb-3">Feedback Opportunities (<?php echo count($feedback_opportunities); ?>)</h3>
                    <p class="text-gray-700 mb-3">You have pending feedback to leave for recent transactions:</p>
                    <ul class="list-disc pl-5 text-gray-700">
                        <?php foreach ($feedback_opportunities as $opportunity): ?>
                            <li class="mb-2">
                                <span class="font-medium">Item:</span> <a href="item_detail.php?id=<?php echo htmlspecialchars($opportunity['item_id']); ?>" class="text-reseda-green hover:underline"><?php echo htmlspecialchars($opportunity['title']); ?></a>
                                <br>
                                <span class="font-medium">For:</span> <?php echo htmlspecialchars($opportunity['partner_username']); ?>
                                (as <?php echo htmlspecialchars(str_replace('_', ' ', $opportunity['feedback_type'])); ?>)
                                <a href="leave_feedback.php?item_id=<?php echo htmlspecialchars($opportunity['item_id']); ?>&partner_id=<?php echo htmlspecialchars($opportunity['partner_id']); ?>&type=<?php echo htmlspecialchars($opportunity['feedback_type']); ?>" class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded-lg ml-2 transition-colors duration-300">Leave Feedback</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Other Dashboard Cards (Profile Settings, Admin Panel) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Dashboard Card: Profile Settings -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Profile Settings</h3>
                    <p class="text-gray-700">Manage your account details and preferences.</p>
                    <a href="profile.php" class="text-reseda-green hover:underline mt-2 block">Edit Profile</a>
                </div>

                <?php if ($is_admin): ?>
                <!-- Dashboard Card: Admin Panel Access (only for admins) -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 col-span-full">
                    <h3 class="text-xl font-semibold text-moss-green mb-3">Admin Panel</h3>
                    <p class="text-gray-700">Access administrative controls for users, items, and categories.</p>
                    <a href="admin/index.php" class="text-reseda-green hover:underline mt-2 block">Go to Admin Panel</a>
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
