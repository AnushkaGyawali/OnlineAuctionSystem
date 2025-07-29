<?php
// public/view_message.php - View Single Message Thread Page

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in
requireLogin();

$message = '';
$current_user_id = $_SESSION['user_id'];
$conversation_partner_id = intval($_GET['user_id'] ?? 0);
$item_id = intval($_GET['item_id'] ?? 0);
$item_id = ($item_id > 0) ? $item_id : NULL;

$conversation_partner_username = '';
$item_title = '';
$messages_in_thread = [];

if ($conversation_partner_id <= 0) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid conversation partner.</div>';
} else {
    // Fetch conversation partner's username
    try {
        $stmt_partner = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_partner->execute([$conversation_partner_id]);
        $partner_data = $stmt_partner->fetch();
        if ($partner_data) {
            $conversation_partner_username = $partner_data['username'];
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Conversation partner not found.</div>';
        }

        // Fetch item title if item_id is provided
        if ($item_id) {
            $stmt_item = $pdo->prepare("SELECT title FROM items WHERE id = ?");
            $stmt_item->execute([$item_id]);
            $item_data = $stmt_item->fetch();
            if ($item_data) {
                $item_title = $item_data['title'];
            }
        }

        // Fetch messages in this thread
        $sql_messages = "
            SELECT
                m.id,
                m.sender_id,
                m.receiver_id,
                m.subject,
                m.message_text,
                m.sent_at,
                m.is_read,
                sender.username AS sender_username,
                receiver.username AS receiver_username
            FROM
                messages m
            JOIN
                users sender ON m.sender_id = sender.id
            JOIN
                users receiver ON m.receiver_id = receiver.id
            WHERE
                ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                AND (m.item_id = ? OR (m.item_id IS NULL AND ? IS NULL))
            ORDER BY
                m.sent_at ASC
        ";
        $stmt_messages = $pdo->prepare($sql_messages);
        $stmt_messages->execute([
            $current_user_id, $conversation_partner_id,
            $conversation_partner_id, $current_user_id,
            $item_id, $item_id
        ]);
        $messages_in_thread = $stmt_messages->fetchAll();

        // Mark messages received by current user in this thread as read
        $stmt_mark_read = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ? AND (item_id = ? OR (item_id IS NULL AND ? IS NULL)) AND is_read = FALSE");
        $stmt_mark_read->execute([$current_user_id, $conversation_partner_id, $item_id, $item_id]);

    } catch (PDOException $e) {
        error_log("Error fetching message thread: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading message thread. Please try again.</div>';
    }
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_text = trim($_POST['reply_text'] ?? '');
    $original_subject = trim($_POST['original_subject'] ?? ''); // Carry over subject

    if (empty($reply_text)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Reply cannot be empty.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, item_id, subject, message_text) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$current_user_id, $conversation_partner_id, $item_id, $original_subject, $reply_text]);
            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Reply sent!</div>';

            // Add notification for the recipient
            $notification_msg = "New reply from " . htmlspecialchars($_SESSION['username']);
            if (!empty($original_subject)) {
                $notification_msg .= ": " . htmlspecialchars($original_subject);
            }
            $notification_link = "view_message.php?user_id=" . $current_user_id;
            if ($item_id) {
                $notification_link .= "&item_id=" . $item_id;
            }
            $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
            $stmt_notify->execute([$conversation_partner_id, 'new_message', $notification_msg, $notification_link]);

            // Redirect to refresh the page and show new message
            header("Location: view_message.php?user_id={$conversation_partner_id}" . ($item_id ? "&item_id={$item_id}" : ''));
            exit();
        } catch (PDOException $e) {
            error_log("Error sending reply: " . $e->getMessage());
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to send reply due to a server error.</div>';
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
    <title>Message Thread - Online Auction System</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Conversation with <?php echo htmlspecialchars($conversation_partner_username); ?></h2>

            <?php if ($item_title): ?>
                <p class="text-center text-gray-600 mb-6">Regarding Item: <a href="item_detail.php?id=<?php echo htmlspecialchars($item_id); ?>" class="text-moss-green hover:underline"><?php echo htmlspecialchars($item_title); ?></a></p>
            <?php endif; ?>

            <?php echo $message; // Display messages ?>

            <div class="mb-6 text-center">
                <a href="messages.php" class="bg-olivine-2 hover:bg-moss-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">
                    &larr; Back to Inbox
                </a>
            </div>

            <div class="message-thread-container h-96 overflow-y-auto p-4 border border-gray-300 rounded-lg bg-nyanza mb-6 flex flex-col-reverse">
                <?php if (empty($messages_in_thread)): ?>
                    <p class="text-center text-gray-600 text-lg">No messages in this conversation yet.</p>
                <?php else: ?>
                    <?php foreach (array_reverse($messages_in_thread) as $msg): // Display in chronological order ?>
                        <div class="flex <?php echo ($msg['sender_id'] == $current_user_id) ? 'justify-end' : 'justify-start'; ?> mb-4">
                            <div class="max-w-md p-3 rounded-lg shadow-md
                                <?php echo ($msg['sender_id'] == $current_user_id) ? 'bg-moss-green text-white' : 'bg-gray-200 text-gray-800'; ?>">
                                <p class="font-semibold text-sm mb-1">
                                    <?php echo ($msg['sender_id'] == $current_user_id) ? 'You' : htmlspecialchars($msg['sender_username']); ?>
                                </p>
                                <?php if ($msg['subject'] && ($msg['sender_id'] == $current_user_id || $msg['receiver_id'] == $current_user_id)): // Only show subject on first message or if relevant ?>
                                    <p class="text-xs italic mb-1">Subject: <?php echo htmlspecialchars($msg['subject']); ?></p>
                                <?php endif; ?>
                                <p class="text-base"><?php echo htmlspecialchars($msg['message_text']); ?></p>
                                <p class="text-xs text-right mt-1 opacity-75">
                                    <?php echo date('M j, H:i', strtotime($msg['sent_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Reply Form -->
            <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2">
                <h3 class="text-2xl font-semibold text-moss-green mb-4">Reply</h3>
                <form action="view_message.php?user_id=<?php echo htmlspecialchars($conversation_partner_id); ?><?php echo $item_id ? '&item_id=' . htmlspecialchars($item_id) : ''; ?>" method="POST">
                    <input type="hidden" name="original_subject" value="<?php echo htmlspecialchars($messages_in_thread[0]['subject'] ?? ''); ?>">
                    <div class="mb-6">
                        <label for="reply_text" class="block text-gray-700 text-sm font-bold mb-2">Your Reply:</label>
                        <textarea id="reply_text" name="reply_text" rows="4" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required></textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="submit" name="send_reply" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Send Reply</button>
                    </div>
                </form>
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
