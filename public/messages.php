<?php
// public/messages.php - Messaging Inbox Page

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in
requireLogin();

$message = '';
$current_user_id = $_SESSION['user_id'];
$conversations = []; // To store distinct conversations

// Fetch conversations (distinct sender/receiver pairs involving current user)
try {
    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.sender_id,
            m.receiver_id,
            m.subject,
            m.message_text,
            m.sent_at,
            m.is_read,
            sender.username AS sender_username,
            receiver.username AS receiver_username,
            i.title AS item_title,
            i.id AS item_id
        FROM
            messages m
        LEFT JOIN
            users sender ON m.sender_id = sender.id
        LEFT JOIN
            users receiver ON m.receiver_id = receiver.id
        LEFT JOIN
            items i ON m.item_id = i.id
        WHERE
            m.sender_id = ? OR m.receiver_id = ?
        ORDER BY
            m.sent_at DESC
    ");
    $stmt->execute([$current_user_id, $current_user_id]);
    $all_messages = $stmt->fetchAll();

    // Group messages into conversations
    foreach ($all_messages as $msg) {
        $participant_id = ($msg['sender_id'] == $current_user_id) ? $msg['receiver_id'] : $msg['sender_id'];
        $participant_username = ($msg['sender_id'] == $current_user_id) ? $msg['receiver_username'] : $msg['sender_username'];

        // Use a unique key for each conversation (participant + item_id for context)
        $conversation_key = $participant_id . '_' . ($msg['item_id'] ?? '0');

        if (!isset($conversations[$conversation_key])) {
            $conversations[$conversation_key] = [
                'participant_id' => $participant_id,
                'participant_username' => $participant_username,
                'item_id' => $msg['item_id'],
                'item_title' => $msg['item_title'],
                'latest_message' => $msg,
                'unread_count' => 0
            ];
        }

        // Count unread messages for this conversation if current user is receiver and message is unread
        if ($msg['receiver_id'] == $current_user_id && !$msg['is_read']) {
            $conversations[$conversation_key]['unread_count']++;
        }
    }

    // Sort conversations by latest message time
    usort($conversations, function($a, $b) {
        return strtotime($b['latest_message']['sent_at']) - strtotime($a['latest_message']['sent_at']);
    });

} catch (PDOException $e) {
    error_log("Error fetching conversations: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading messages. Please try again.</div>';
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
    <title>My Messages - Online Auction System</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-8">My Messages</h2>

            <?php echo $message; // Display messages ?>

            <div class="mb-6 text-center">
                <a href="messages.php?compose=true" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">Compose New Message</a>
            </div>

            <?php if (isset($_GET['compose'])): ?>
                <!-- Compose Message Form -->
                <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 mb-8">
                    <h3 class="text-2xl font-semibold text-moss-green mb-4">Compose Message</h3>
                    <form action="messages.php" method="POST">
                        <div class="mb-4">
                            <label for="recipient_username" class="block text-gray-700 text-sm font-bold mb-2">Recipient Username:</label>
                            <input type="text" id="recipient_username" name="recipient_username" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($_GET['to_user'] ?? ''); ?>">
                        </div>
                        <div class="mb-4">
                            <label for="subject" class="block text-gray-700 text-sm font-bold mb-2">Subject (Optional):</label>
                            <input type="text" id="subject" name="subject" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($_GET['subject'] ?? ''); ?>">
                        </div>
                        <?php if (isset($_GET['item_id'])): ?>
                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($_GET['item_id']); ?>">
                            <p class="text-sm text-gray-600 mb-4">Context: Message about item ID <?php echo htmlspecialchars($_GET['item_id']); ?></p>
                        <?php endif; ?>
                        <div class="mb-6">
                            <label for="message_text" class="block text-gray-700 text-sm font-bold mb-2">Message:</label>
                            <textarea id="message_text" name="message_text" rows="6" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required></textarea>
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="submit" name="send_message" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Send Message</button>
                        </div>
                        <p class="text-center text-gray-600 text-sm mt-4">
                            <a href="messages.php" class="text-moss-green hover:underline">Cancel</a>
                        </p>
                    </form>
                </div>
            <?php
            // Handle sending message
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
                $recipient_username = trim($_POST['recipient_username'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $message_text = trim($_POST['message_text'] ?? '');
                $item_id = intval($_POST['item_id'] ?? 0);
                $item_id = ($item_id > 0) ? $item_id : NULL;

                // Find recipient ID
                $stmt_recipient = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt_recipient->execute([$recipient_username]);
                $recipient_user = $stmt_recipient->fetch();

                if (!$recipient_user) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Recipient username not found.</div>';
                } elseif ($recipient_user['id'] == $current_user_id) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">You cannot send a message to yourself.</div>';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, item_id, subject, message_text) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$current_user_id, $recipient_user['id'], $item_id, $subject, $message_text]);
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Message sent successfully!</div>';

                        // Add notification for the recipient
                        $notification_msg = "New message from " . htmlspecialchars($_SESSION['username']);
                        if (!empty($subject)) {
                            $notification_msg .= ": " . htmlspecialchars($subject);
                        }
                        $notification_link = "messages.php?view_user=" . $current_user_id; // Link to conversation
                        if ($item_id) {
                            $notification_link .= "&item_id=" . $item_id;
                        }
                        $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
                        $stmt_notify->execute([$recipient_user['id'], 'new_message', $notification_msg, $notification_link]);

                        // Redirect to clear form and show message
                        header('Location: messages.php?status=sent');
                        exit();
                    } catch (PDOException $e) {
                        error_log("Error sending message: " . $e->getMessage());
                        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to send message due to a server error.</div>';
                    }
                }
            }
            if (isset($_GET['status']) && $_GET['status'] == 'sent') {
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Message sent successfully!</div>';
            }
            ?>
            <?php else: ?>
                <!-- Conversation List -->
                <?php if (empty($conversations)): ?>
                    <p class="text-center text-gray-600 text-lg">You have no messages yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($conversations as $conv):
                            $latest_msg = $conv['latest_message'];
                            $is_unread = ($latest_msg['receiver_id'] == $current_user_id && !$latest_msg['is_read']);
                            $conversation_partner = ($latest_msg['sender_id'] == $current_user_id) ? $latest_msg['receiver_username'] : $latest_msg['sender_username'];
                            $conversation_link = "view_message.php?user_id=" . $conv['participant_id'];
                            if ($conv['item_id']) {
                                $conversation_link .= "&item_id=" . $conv['item_id'];
                            }
                        ?>
                            <a href="<?php echo htmlspecialchars($conversation_link); ?>" class="block">
                                <div class="p-4 rounded-lg shadow-sm border
                                    <?php echo $is_unread ? 'bg-tea-green border-olivine-2' : 'bg-gray-100 border-gray-300'; ?>
                                    hover:bg-nyanza transition-colors duration-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                                    <div class="flex-grow">
                                        <p class="font-semibold text-lg <?php echo $is_unread ? 'text-moss-green' : 'text-gray-700'; ?>">
                                            Conversation with: <?php echo htmlspecialchars($conversation_partner); ?>
                                            <?php if ($conv['unread_count'] > 0): ?>
                                                <span class="ml-2 px-2 py-1 bg-red-500 text-white text-xs rounded-full"><?php echo $conv['unread_count']; ?> Unread</span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($conv['item_title']): ?>
                                            <p class="text-sm text-gray-600">Regarding: <span class="font-medium"><?php echo htmlspecialchars($conv['item_title']); ?></span></p>
                                        <?php endif; ?>
                                        <p class="text-sm text-gray-500 mt-1 truncate">
                                            <?php echo htmlspecialchars($latest_msg['subject'] ? $latest_msg['subject'] . ' - ' : '') . htmlspecialchars($latest_msg['message_text']); ?>
                                        </p>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2 sm:mt-0 sm:ml-4 flex-shrink-0">
                                        <?php echo date('M j, Y H:i', strtotime($latest_msg['sent_at'])); ?>
                                    </p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
