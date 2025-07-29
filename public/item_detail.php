<?php
// public/item_detail.php - Item Detail and Bidding Page for Online Auction System

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php'; // For isLoggedIn(), getNextBidIncrement()

$message = '';
$item = null;
$item_id = $_GET['id'] ?? 0; // Get item ID from URL
$current_user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
$bid_history = [];
$next_min_bid_display = 0.01; // Default for display before item is fetched

// Anti-sniping configuration
const ANTI_SNIPING_GRACE_PERIOD_SECONDS = 300; // Extend auction by this many seconds (e.g., 5 minutes) if bid placed in last X seconds
const ANTI_SNIPING_EXTENSION_SECONDS = 300;    // Extend auction by this amount (e.g., 5 minutes)

// --- Fetch Item Details ---
if ($item_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                i.id,
                i.title,
                i.description,
                i.start_price,
                i.reserve_price,
                i.buy_now_price,
                i.start_time,
                i.end_time,
                i.current_bid,
                i.highest_bidder_id,
                i.status,
                i.image_urls,
                u.username AS seller_username,
                u.id AS seller_id,
                hb.username AS highest_bidder_username,
                (SELECT proxy_max_amount FROM bids WHERE item_id = i.id AND bidder_id = i.highest_bidder_id ORDER BY bid_time DESC LIMIT 1) AS highest_bidder_proxy_max
            FROM
                items i
            JOIN
                users u ON i.seller_id = u.id
            LEFT JOIN
                users hb ON i.highest_bidder_id = hb.id
            WHERE
                i.id = ?
        ");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();

        if (!$item) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Item not found.</div>';
        } else {
            // Decode image_urls JSON
            $item['image_urls'] = json_decode($item['image_urls'] ?? '[]', true);

            // Calculate next minimum bid for display
            $current_effective_bid = $item['current_bid'] ?? $item['start_price'];
            $next_min_bid_display = $current_effective_bid + getNextBidIncrement($current_effective_bid);

            // Fetch bid history for this item
            $stmt_bids = $pdo->prepare("
                SELECT
                    b.bid_amount,
                    b.bid_time,
                    u.username AS bidder_username,
                    b.is_proxy_bid,
                    b.proxy_max_amount
                FROM
                    bids b
                JOIN
                    users u ON b.bidder_id = u.id
                WHERE
                    b.item_id = ?
                ORDER BY
                    b.bid_time DESC
            ");
            $stmt_bids->execute([$item_id]);
            $bid_history = $stmt_bids->fetchAll();
        }

    } catch (PDOException $e) {
        error_log("Error fetching item details: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading item details. Please try again.</div>';
    }
} else {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">No item ID provided.</div>';
}


// --- Handle Bid Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_bid'])) {
    if (!isLoggedIn()) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">You must be logged in to place a bid.</div>';
    } elseif (!$item || $item['status'] !== 'active' || strtotime($item['end_time']) <= time()) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">This auction is not active or has ended.</div>';
    } elseif ($current_user_id === $item['seller_id']) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">You cannot bid on your own item.</div>';
    } else {
        $user_bid_amount = floatval($_POST['bid_amount'] ?? 0);
        $user_proxy_max = !empty($_POST['proxy_max_amount']) ? floatval($_POST['proxy_max_amount']) : NULL;

        // Re-fetch item data inside the POST request to ensure it's fresh
        // This helps prevent race conditions, though a full locking mechanism would be more robust
        $stmt_re_fetch = $pdo->prepare("
            SELECT
                i.id,
                i.title,
                i.start_price,
                i.current_bid,
                i.highest_bidder_id,
                i.end_time, -- Fetch end_time for anti-sniping check
                (SELECT proxy_max_amount FROM bids WHERE item_id = i.id AND bidder_id = i.highest_bidder_id ORDER BY bid_time DESC LIMIT 1) AS highest_bidder_proxy_max
            FROM
                items i
            WHERE
                i.id = ?
        ");
        $stmt_re_fetch->execute([$item_id]);
        $fresh_item = $stmt_re_fetch->fetch();

        if (!$fresh_item) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Item not found or auction state changed.</div>';
        } else {
            $current_effective_bid = $fresh_item['current_bid'] ?? $fresh_item['start_price'];
            $next_min_increment = getNextBidIncrement($current_effective_bid);
            $next_min_bid_required = $current_effective_bid + $next_min_increment;

            if ($user_bid_amount <= 0) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Bid amount must be positive.</div>';
            } elseif ($user_bid_amount < $next_min_bid_required) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Your bid must be at least $' . number_format($next_min_bid_required, 2) . '.</div>';
            } elseif ($user_proxy_max !== NULL && $user_proxy_max < $user_bid_amount) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Your maximum bid cannot be less than your current bid.</div>';
            } else {
                try {
                    $pdo->beginTransaction();

                    $new_current_bid = $user_bid_amount;
                    $new_highest_bidder_id = $current_user_id;
                    $is_proxy = ($user_proxy_max !== NULL);
                    $new_end_time = $fresh_item['end_time']; // Initialize with current end time

                    // Logic for Proxy Bidding (Ascending Clock Algorithm simulation)
                    $highest_bidder_id_before = $fresh_item['highest_bidder_id'];
                    $highest_bidder_proxy_max_before = $fresh_item['highest_bidder_proxy_max'];

                    if ($highest_bidder_id_before !== NULL && $highest_bidder_id_before !== $current_user_id) {
                        // There's an existing highest bidder who is not the current user
                        if ($highest_bidder_proxy_max_before !== NULL && $highest_bidder_proxy_max_before >= $user_bid_amount) {
                            // Current highest bidder has a proxy bid that can beat the incoming bid
                            $new_current_bid = $user_bid_amount + $next_min_increment;
                            if ($new_current_bid > $highest_bidder_proxy_max_before) {
                                // If the new calculated bid exceeds the current highest bidder's proxy max,
                                // the new user becomes the highest bidder at the current highest bidder's proxy max + increment.
                                $new_current_bid = $highest_bidder_proxy_max_before + $next_min_increment;
                                // But if the incoming user's bid is still lower than this new_current_bid,
                                // then the current highest bidder remains the winner at their proxy max.
                                if ($user_bid_amount < $new_current_bid) {
                                    $new_current_bid = min($user_bid_amount, $highest_bidder_proxy_max_before); // Bid up to just beat the incoming bid
                                    if ($new_current_bid < $next_min_bid_required) {
                                        $new_current_bid = $next_min_bid_required;
                                    }
                                }
                                $new_highest_bidder_id = $highest_bidder_id_before; // Current highest bidder remains
                                $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">You were outbid by a proxy bid. Current bid is now $' . number_format($new_current_bid, 2) . '.</div>';
                            } else {
                                // Current highest bidder's proxy bid is higher than or equal to incoming bid
                                // The current highest bidder remains, bid increases by increment
                                $new_current_bid = $user_bid_amount; // Bid up to the incoming bid
                                $new_highest_bidder_id = $highest_bidder_id_before; // Current highest bidder remains
                                $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">You were outbid by a proxy bid. Current bid is now $' . number_format($new_current_bid, 2) . '.</div>';
                            }
                        }
                    }

                    // Ensure the new_current_bid is at least the next_min_bid_required
                    // This handles cases where user bids just the minimum, or if proxy logic resulted in a lower value
                    $new_current_bid = max($new_current_bid, $next_min_bid_required);

                    // Anti-Sniping Logic
                    $time_left = strtotime($fresh_item['end_time']) - time();
                    if ($time_left < ANTI_SNIPING_GRACE_PERIOD_SECONDS) {
                        // If bid is placed within the grace period, extend the auction end time
                        $new_end_time = date('Y-m-d H:i:s', strtotime($fresh_item['end_time']) + ANTI_SNIPING_EXTENSION_SECONDS);
                        $message .= '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mt-2" role="alert">Auction extended due to last-minute bid!</div>';
                    }


                    // 1. Insert the new bid (always record the user's actual bid and proxy max)
                    $stmt_insert_bid = $pdo->prepare("INSERT INTO bids (item_id, bidder_id, bid_amount, is_proxy_bid, proxy_max_amount) VALUES (?, ?, ?, ?, ?)");
                    $stmt_insert_bid->execute([$item_id, $current_user_id, $user_bid_amount, $is_proxy, $user_proxy_max]);

                    // 2. Update the item's current_bid, highest_bidder_id, and end_time based on the new logic
                    $stmt_update_item = $pdo->prepare("UPDATE items SET current_bid = ?, highest_bidder_id = ?, end_time = ? WHERE id = ?");
                    $stmt_update_item->execute([$new_current_bid, $new_highest_bidder_id, $new_end_time, $item_id]);

                    $pdo->commit(); // Commit the transaction

                    // If the user is the new highest bidder, display success
                    if ($new_highest_bidder_id === $current_user_id) {
                        $_SESSION['success_message'] = 'Your bid of $' . number_format($user_bid_amount, 2) . ' has been placed successfully! You are the highest bidder.';
                    }
                    // If the user was outbid by a proxy, the message is already set above

                    header("Location: item_detail.php?id=" . $item_id); // Redirect to refresh data
                    exit();

                } catch (PDOException $e) {
                    $pdo->rollBack(); // Rollback on error
                    error_log("Bid placement failed: " . $e->getMessage());
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to place bid due to a server error. Please try again.</div>';
                }
            }
        }
    }
}

// --- Handle Buy Now (remains mostly the same, but ensure it's integrated with proxy logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    if (!isLoggedIn()) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">You must be logged in to use Buy Now.</div>';
    } elseif (!$item || $item['status'] !== 'active' || strtotime($item['end_time']) <= time()) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">This auction is not active or has ended.</div>';
    } elseif ($current_user_id === $item['seller_id']) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">You cannot buy your own item.</div>';
    } elseif ($item['buy_now_price'] === NULL) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">This item does not have a "Buy Now" option.</div>';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update item status and final price
            $stmt_update_item = $pdo->prepare("UPDATE items SET status = 'sold', current_bid = ?, highest_bidder_id = ? WHERE id = ?");
            $stmt_update_item->execute([$item['buy_now_price'], $current_user_id, $item_id]);

            // 2. Record in sold_items table
            $stmt_sold_item = $pdo->prepare("INSERT INTO sold_items (item_id, buyer_id, seller_id, final_price) VALUES (?, ?, ?, ?)");
            $stmt_sold_item->execute([$item_id, $current_user_id, $item['seller_id'], $item['buy_now_price']]);

            $pdo->commit();

            $_SESSION['success_message'] = 'Congratulations! You have purchased "' . htmlspecialchars($item['title']) . '" for $' . number_format($item['buy_now_price'], 2) . '.';
            header("Location: dashboard.php"); // Redirect to dashboard or order confirmation
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Buy Now failed: " . $e->getMessage());
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to complete purchase due to a server error. Please try again.</div>';
        }
    }
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

// Display session messages (e.g., from successful bid redirect)
if (isset($_SESSION['success_message'])) {
    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $item ? htmlspecialchars($item['title']) : 'Item Not Found'; ?> - Online Auction System</title>
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
            <?php echo $message; // Display messages ?>

            <?php if ($item): ?>
                <h2 class="text-4xl font-bold text-moss-green mb-6 text-center md:text-left"><?php echo htmlspecialchars($item['title']); ?></h2>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Item Images/Gallery -->
                    <div class="lg:col-span-2">
                        <?php if (!empty($item['image_urls'])): ?>
                            <div class="relative w-full h-96 bg-gray-200 rounded-lg overflow-hidden shadow-lg">
                                <img src="<?php echo htmlspecialchars($item['image_urls'][0]); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-full object-cover">
                                <!-- Add more images/gallery functionality here later -->
                            </div>
                        <?php else: ?>
                            <div class="relative w-full h-96 bg-gray-200 rounded-lg overflow-hidden shadow-lg flex items-center justify-center text-gray-500 text-lg">
                                <img src="https://placehold.co/800x600/D0F0C0/6B8E23?text=No+Image" alt="No Image Available" class="w-full h-full object-cover">
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Item Details and Bidding -->
                    <div class="lg:col-span-1">
                        <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 mb-6">
                            <p class="text-lg text-gray-700 mb-2">Seller: <span class="font-semibold text-reseda-green"><?php echo htmlspecialchars($item['seller_username']); ?></span></p>
                            <p class="text-lg text-gray-700 mb-2">Starting Price: <span class="font-semibold text-moss-green">$<?php echo number_format($item['start_price'], 2); ?></span></p>
                            <?php if ($item['reserve_price'] !== NULL): ?>
                                <p class="text-sm text-gray-500 mb-2">Reserve Price: <span class="font-semibold text-gray-600">Met/Not Met (Hidden)</span></p>
                            <?php endif; ?>
                            <?php if ($item['buy_now_price'] !== NULL): ?>
                                <p class="text-lg text-gray-700 mb-4">Buy Now Price: <span class="font-semibold text-moss-green">$<?php echo number_format($item['buy_now_price'], 2); ?></span></p>
                            <?php endif; ?>

                            <p class="text-2xl font-bold text-reseda-green mb-4">
                                Current Bid: $<?php echo number_format($item['current_bid'] ?? $item['start_price'], 2); ?>
                            </p>
                            <?php if ($item['highest_bidder_username']): ?>
                                <p class="text-sm text-gray-600 mb-4">Highest Bidder: <span class="font-medium"><?php echo htmlspecialchars($item['highest_bidder_username']); ?></span></p>
                            <?php endif; ?>

                            <p class="text-lg text-gray-700 mb-4">
                                Auction Ends: <span class="font-semibold" id="auction-countdown" data-end-time="<?php echo htmlspecialchars($item['end_time']); ?>">
                                    <?php echo date('M j, Y H:i:s', strtotime($item['end_time'])); ?>
                                </span>
                            </p>

                            <?php
                            $auction_ended = (strtotime($item['end_time']) <= time());
                            $is_seller = ($current_user_id === $item['seller_id']);
                            $is_highest_bidder = ($current_user_id === $item['highest_bidder_id']);
                            ?>

                            <?php if ($auction_ended): ?>
                                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                                    This auction has ended.
                                    <?php if ($item['status'] === 'sold'): ?>
                                        <span class="font-bold">Sold!</span>
                                    <?php elseif ($item['status'] === 'active'): // Ended but not yet processed as sold/unsold ?>
                                        <span class="font-bold">Awaiting processing.</span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($is_seller): ?>
                                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
                                    You are the seller of this item.
                                </div>
                            <?php else: ?>
                                <!-- Bidding Form -->
                                <form action="item_detail.php?id=<?php echo htmlspecialchars($item['id']); ?>" method="POST" class="mb-4">
                                    <div class="mb-4">
                                        <label for="bid_amount" class="block text-gray-700 text-sm font-bold mb-2">Your Bid ($):</label>
                                        <input type="number" id="bid_amount" name="bid_amount" step="0.01" min="<?php echo number_format($next_min_bid_display, 2, '.', ''); ?>" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required>
                                        <p class="text-xs text-gray-500 mt-1">Minimum bid: $<?php echo number_format($next_min_bid_display, 2); ?></p>
                                    </div>
                                    <div class="mb-6">
                                        <label for="proxy_max_amount" class="block text-gray-700 text-sm font-bold mb-2">Your Maximum (Proxy) Bid (Optional $):</label>
                                        <input type="number" id="proxy_max_amount" name="proxy_max_amount" step="0.01" min="<?php echo number_format($next_min_bid_display, 2, '.', ''); ?>" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent">
                                        <p class="text-xs text-gray-500 mt-1">The system will bid for you up to this amount.</p>
                                    </div>
                                    <button type="submit" name="place_bid" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Place Bid</button>
                                </form>

                                <?php if ($item['buy_now_price'] !== NULL): ?>
                                    <form action="item_detail.php?id=<?php echo htmlspecialchars($item['id']); ?>" method="POST">
                                        <button type="submit" name="buy_now" class="bg-olivine hover:bg-olivine-2 text-gray-800 font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full mt-2">Buy Now for $<?php echo number_format($item['buy_now_price'], 2); ?></button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($is_highest_bidder): ?>
                                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-4" role="alert">
                                        You are currently the highest bidder!
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>
                        </div>

                        <!-- Item Description -->
                        <div class="bg-white p-6 rounded-lg shadow-md border border-olivine-2">
                            <h3 class="text-2xl font-semibold text-moss-green mb-3">Description</h3>
                            <p class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($item['description']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Bid History -->
                <div class="mt-8 bg-white p-8 rounded-lg shadow-xl border border-olivine-2">
                    <h3 class="text-2xl font-bold text-moss-green mb-4">Bid History</h3>
                    <?php if (empty($bid_history)): ?>
                        <p class="text-gray-600">No bids have been placed on this item yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-olivine-2 rounded-lg">
                                <thead>
                                    <tr class="bg-tea-green text-left text-gray-700">
                                        <th class="py-3 px-4 border-b border-olivine">Bidder</th>
                                        <th class="py-3 px-4 border-b border-olivine">Bid Amount</th>
                                        <th class="py-3 px-4 border-b border-olivine">Type</th>
                                        <th class="py-3 px-4 border-b border-olivine">Bid Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bid_history as $bid): ?>
                                        <tr class="hover:bg-nyanza">
                                            <td class="py-3 px-4 border-b border-olivine-2"><?php echo htmlspecialchars($bid['bidder_username']); ?></td>
                                            <td class="py-3 px-4 border-b border-olivine-2 font-semibold text-reseda-green">$<?php echo number_format($bid['bid_amount'], 2); ?></td>
                                            <td class="py-3 px-4 border-b border-olivine-2">
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $bid['is_proxy_bid'] ? 'bg-blue-200 text-blue-800' : 'bg-green-200 text-green-800'; ?>">
                                                    <?php echo $bid['is_proxy_bid'] ? 'Proxy' : 'Direct'; ?>
                                                </span>
                                                <?php if ($bid['is_proxy_bid'] && $bid['proxy_max_amount'] !== NULL): ?>
                                                    <span class="text-xs text-gray-500">(Max: $<?php echo number_format($bid['proxy_max_amount'], 2); ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 border-b border-olivine-2 text-sm text-gray-600"><?php echo date('M j, Y H:i:s', strtotime($bid['bid_time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
    <script>
        // JavaScript for countdown timer
        document.addEventListener('DOMContentLoaded', function() {
            const countdownElement = document.getElementById('auction-countdown');
            if (countdownElement) {
                const endTimeString = countdownElement.dataset.endTime;
                const endTime = new Date(endTimeString).getTime();

                const updateCountdown = () => {
                    const now = new Date().getTime();
                    const distance = endTime - now;

                    if (distance < 0) {
                        countdownElement.innerHTML = "Auction Ended!";
                        clearInterval(countdownInterval);
                        // Optionally reload the page or update UI to reflect "ended" status
                        // setTimeout(() => location.reload(), 2000); // Reload after 2 seconds
                        return;
                    }

                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    countdownElement.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;
                };

                const countdownInterval = setInterval(updateCountdown, 1000);
                updateCountdown(); // Initial call to display immediately
            }
        });
    </script>
</body>
</html>
