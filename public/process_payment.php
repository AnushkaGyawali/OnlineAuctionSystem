<?php
// public/process_payment.php - Dummy Payment Processing Page

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in
requireLogin();

$message = '';
$current_user_id = $_SESSION['user_id'];
$item_id = intval($_GET['item_id'] ?? 0);
$sold_item_details = null;

// Fetch sold item details to ensure it's a valid item for payment
if ($item_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                si.item_id,
                si.buyer_id,
                si.final_price,
                si.payment_status,
                i.title,
                i.seller_id,
                s.username AS seller_username
            FROM
                sold_items si
            JOIN
                items i ON si.item_id = i.id
            JOIN
                users s ON i.seller_id = s.id
            WHERE
                si.item_id = ? AND si.buyer_id = ?
        ");
        $stmt->execute([$item_id, $current_user_id]);
        $sold_item_details = $stmt->fetch();

        if (!$sold_item_details) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Item not found in your sold items or you are not the buyer.</div>';
        } elseif ($sold_item_details['payment_status'] === 'paid') {
            $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">This item has already been paid for.</div>';
        }

    } catch (PDOException $e) {
        error_log("Error fetching sold item for payment: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading payment details. Please try again.</div>';
    }
} else {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">No item ID provided for payment.</div>';
}

// Handle dummy payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $payment_keyword = trim($_POST['payment_keyword'] ?? '');

    if ($sold_item_details && $sold_item_details['payment_status'] === 'pending') {
        if (strtoupper($payment_keyword) === 'PAYMENT') {
            try {
                // Simulate payment processing delay
                sleep(2); // Wait for 2 seconds

                $pdo->beginTransaction();

                // Update payment status in sold_items
                $stmt_update_payment = $pdo->prepare("UPDATE sold_items SET payment_status = 'paid' WHERE item_id = ? AND buyer_id = ?");
                $stmt_update_payment->execute([$item_id, $current_user_id]);

                // Add notification for the buyer
                $notification_buyer_msg = "Payment for '" . htmlspecialchars($sold_item_details['title']) . "' was successful!";
                $stmt_notify_buyer = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
                $stmt_notify_buyer->execute([$current_user_id, 'payment_successful', $notification_buyer_msg, 'item_detail.php?id=' . $item_id]);

                // Add notification for the seller
                $notification_seller_msg = "Your item '" . htmlspecialchars($sold_item_details['title']) . "' has been paid for by " . htmlspecialchars($_SESSION['username']) . ".";
                $stmt_notify_seller = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
                $stmt_notify_seller->execute([$sold_item_details['seller_id'], 'payment_received', $notification_seller_msg, 'item_detail.php?id=' . $item_id]);

                $pdo->commit();

                $_SESSION['success_message'] = 'Payment for "' . htmlspecialchars($sold_item_details['title']) . '" successful! You can now arrange shipping.';
                header('Location: dashboard.php');
                exit();

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Dummy payment processing failed: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Payment failed due to a server error. Please try again.</div>';
            }
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Incorrect keyword. Please type "PAYMENT".</div>';
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
    <title>Process Payment - Online Auction System</title>
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

    <main class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md border border-olivine-2">
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Process Payment</h2>

            <?php echo $message; // Display messages ?>

            <?php if ($sold_item_details && $sold_item_details['payment_status'] === 'pending'): ?>
                <div class="mb-6 text-center">
                    <p class="text-lg text-gray-700 mb-2">Item: <span class="font-semibold"><?php echo htmlspecialchars($sold_item_details['title']); ?></span></p>
                    <p class="text-lg text-gray-700 mb-2">Amount Due: <span class="font-bold text-reseda-green">$<?php echo number_format($sold_item_details['final_price'], 2); ?></span></p>
                    <p class="text-md text-gray-600">Seller: <span class="font-medium"><?php echo htmlspecialchars($sold_item_details['seller_username']); ?></span></p>
                </div>

                <form action="process_payment.php?item_id=<?php echo htmlspecialchars($item_id); ?>" method="POST">
                    <div class="mb-4">
                        <label for="payment_keyword" class="block text-gray-700 text-sm font-bold mb-2">
                            To confirm payment, please type "PAYMENT" below:
                        </label>
                        <input type="text" id="payment_keyword" name="payment_keyword" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="submit" name="confirm_payment" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Confirm Payment</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-center text-gray-600 text-lg">No pending payments for this item, or item not found.</p>
                <div class="text-center mt-6">
                    <a href="dashboard.php" class="bg-olivine-2 hover:bg-moss-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">
                        &larr; Back to Dashboard
                    </a>
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
