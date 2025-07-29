<?php
// public/notifications.php - User Notifications Page

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in
requireLogin();

$message = '';
$current_user_id = $_SESSION['user_id'];
$notifications = [];

// Handle marking notification as read
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $notification_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $current_user_id]);
        // Redirect to clear GET parameters and refresh list
        header('Location: notifications.php');
        exit();
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to mark notification as read.</div>';
    }
}

// Fetch notifications for the current user, unread first
try {
    $stmt = $pdo->prepare("SELECT id, type, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC");
    $stmt->execute([$current_user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading notifications. Please try again.</div>';
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
    <title>My Notifications - Online Auction System</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-8">My Notifications</h2>

            <?php echo $message; // Display messages ?>

            <?php if (empty($notifications)): ?>
                <p class="text-center text-gray-600 text-lg">You have no notifications at this time.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="p-4 rounded-lg shadow-sm border
                            <?php echo $notification['is_read'] ? 'bg-gray-100 border-gray-300' : 'bg-tea-green border-olivine-2'; ?>
                            flex items-center justify-between flex-wrap">
                            <div class="flex-grow">
                                <p class="font-semibold text-gray-800 <?php echo $notification['is_read'] ? 'text-gray-600' : 'text-moss-green'; ?>">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?>
                                    (Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $notification['type']))); ?>)
                                </p>
                            </div>
                            <div class="flex-shrink-0 mt-2 sm:mt-0">
                                <?php if ($notification['link']): ?>
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="bg-olivine-2 hover:bg-moss-green text-white text-sm px-3 py-1 rounded-lg mr-2 transition-colors duration-300">View</a>
                                <?php endif; ?>
                                <?php if (!$notification['is_read']): ?>
                                    <a href="notifications.php?action=mark_read&id=<?php echo htmlspecialchars($notification['id']); ?>" class="bg-gray-400 hover:bg-gray-500 text-white text-sm px-3 py-1 rounded-lg transition-colors duration-300">Mark Read</a>
                                <?php endif; ?>
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
