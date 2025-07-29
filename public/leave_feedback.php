<?php
// public/leave_feedback.php - Page to leave feedback for a transaction

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in
requireLogin();

$message = '';
$current_user_id = $_SESSION['user_id'];
$item_id = intval($_GET['item_id'] ?? 0);
$transaction_partner_id = intval($_GET['partner_id'] ?? 0); // The user to leave feedback for
$feedback_type = $_GET['type'] ?? ''; // 'buyer_to_seller' or 'seller_to_buyer'

$item_details = null;
$partner_username = '';

// Validate parameters and fetch necessary data
if ($item_id <= 0 || $transaction_partner_id <= 0 || !in_array($feedback_type, ['buyer_to_seller', 'seller_to_buyer'])) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid request for feedback.</div>';
} else {
    try {
        // Fetch item details
        $stmt_item = $pdo->prepare("SELECT id, title, seller_id, highest_bidder_id, status FROM items WHERE id = ?");
        $stmt_item->execute([$item_id]);
        $item_details = $stmt_item->fetch();

        // Fetch partner username
        $stmt_partner = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_partner->execute([$transaction_partner_id]);
        $partner_data = $stmt_partner->fetch();
        if ($partner_data) {
            $partner_username = $partner_data['username'];
        }

        // Basic authorization: ensure the current user was part of this transaction (buyer or seller)
        // and that the item is sold/closed.
        if (!$item_details || ($item_details['seller_id'] != $current_user_id && $item_details['highest_bidder_id'] != $current_user_id)) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">You are not authorized to leave feedback for this item.</div>';
        } elseif ($item_details['status'] !== 'sold' && $item_details['status'] !== 'closed') { // Allow feedback for closed/sold items
             $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">Feedback can only be left for closed or sold auctions.</div>';
        } elseif (($feedback_type === 'buyer_to_seller' && $current_user_id !== $item_details['highest_bidder_id']) ||
                   ($feedback_type === 'seller_to_buyer' && $current_user_id !== $item_details['seller_id'])) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid feedback type for your role in this transaction.</div>';
        } else {
            // Check if feedback already exists
            $stmt_check_feedback = $pdo->prepare("SELECT id FROM feedback_profiles WHERE user_id = ? AND rater_id = ? AND item_id = ? AND feedback_type = ?");
            $stmt_check_feedback->execute([$transaction_partner_id, $current_user_id, $item_id, $feedback_type]);
            if ($stmt_check_feedback->fetch()) {
                $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">You have already left feedback for this transaction.</div>';
            }
        }

    } catch (PDOException $e) {
        error_log("Error fetching feedback data: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading feedback form. Please try again.</div>';
    }
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $item_id_post = intval($_POST['item_id'] ?? 0);
    $partner_id_post = intval($_POST['partner_id'] ?? 0);
    $feedback_type_post = trim($_POST['feedback_type'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Please select a rating between 1 and 5 stars.</div>';
    } elseif (empty($comment)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Please provide a comment for the feedback.</div>';
    } else {
        try {
            // Re-check if feedback already exists to prevent double submission
            $stmt_check_feedback = $pdo->prepare("SELECT id FROM feedback_profiles WHERE user_id = ? AND rater_id = ? AND item_id = ? AND feedback_type = ?");
            $stmt_check_feedback->execute([$partner_id_post, $current_user_id, $item_id_post, $feedback_type_post]);
            if ($stmt_check_feedback->fetch()) {
                $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">You have already left feedback for this transaction.</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO feedback_profiles (user_id, rater_id, item_id, rating, comment, feedback_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$partner_id_post, $current_user_id, $item_id_post, $rating, $comment, $feedback_type_post]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Feedback submitted successfully!</div>';

                // Optionally redirect
                // header('Location: dashboard.php');
                // exit();
            }
        } catch (PDOException $e) {
            error_log("Error submitting feedback: " . $e->getMessage());
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to submit feedback due to a server error.</div>';
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
    <title>Leave Feedback - Online Auction System</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Leave Feedback</h2>

            <?php echo $message; // Display messages ?>

            <?php if ($item_details && $partner_username && empty($message)): // Only show form if valid and no error/already submitted ?>
                <p class="text-center text-gray-700 mb-4">
                    Leaving feedback for <span class="font-semibold text-reseda-green"><?php echo htmlspecialchars($partner_username); ?></span>
                    regarding item: <a href="item_detail.php?id=<?php echo htmlspecialchars($item_details['id']); ?>" class="text-moss-green hover:underline font-semibold"><?php echo htmlspecialchars($item_details['title']); ?></a>.
                </p>

                <form action="leave_feedback.php" method="POST">
                    <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item_id); ?>">
                    <input type="hidden" name="partner_id" value="<?php echo htmlspecialchars($transaction_partner_id); ?>">
                    <input type="hidden" name="feedback_type" value="<?php echo htmlspecialchars($feedback_type); ?>">

                    <div class="mb-4">
                        <label for="rating" class="block text-gray-700 text-sm font-bold mb-2">Rating:</label>
                        <div class="flex items-center justify-center space-x-2 text-3xl">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" class="hidden peer" required>
                                    <span class="text-gray-400 peer-hover:text-yellow-500 peer-checked:text-yellow-500 transition-colors duration-200">&#9733;</span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="comment" class="block text-gray-700 text-sm font-bold mb-2">Comment:</label>
                        <textarea id="comment" name="comment" rows="6" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required></textarea>
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="submit" name="submit_feedback" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Submit Feedback</button>
                    </div>
                </form>
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
