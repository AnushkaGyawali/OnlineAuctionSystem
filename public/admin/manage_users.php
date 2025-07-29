<?php
// public/admin/manage_users.php - Admin User Management Page (CRUD)

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Ensure user is logged in AND is an admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$users = [];
$edit_user = null; // To hold user data if in edit mode

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_user_submit'])) {
        $id = intval($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $is_admin = isset($_POST['is_admin']) ? 1 : 0; // Checkbox value

        if ($id <= 0 || empty($username) || empty($email)) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid user ID or missing required fields.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid email format.</div>';
        } else {
            try {
                // Check for duplicate username/email (excluding current user's own)
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt_check->execute([$username, $email, $id]);
                if ($stmt_check->fetch()) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Username or Email already exists for another user.</div>';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, is_admin = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $is_admin, $id]);
                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">User updated successfully!</div>';
                    // Clear edit mode after successful update
                    header('Location: manage_users.php');
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Error updating user: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to update user due to a server error.</div>';
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = intval($_POST['user_id'] ?? 0);

        // Prevent deleting the currently logged-in admin
        if ($id === $_SESSION['user_id']) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">You cannot delete your own admin account.</div>';
        } elseif ($id <= 0) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid user ID for deletion.</div>';
        } else {
            try {
                // In a real application, you might want to:
                // 1. Reassign items/bids from this user or mark them as anonymous.
                // 2. Prevent deletion if active auctions/pending transactions exist.
                // For simplicity, CASCADE DELETE is set up in database.sql for items/bids,
                // meaning items/bids associated with the user will be deleted or set to NULL.
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">User deleted successfully!</div>';
            } catch (PDOException $e) {
                error_log("Error deleting user: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to delete user due to a server error.</div>';
            }
        }
    }
}

// --- Fetch all users for display ---
try {
    $stmt = $pdo->query("SELECT id, username, email, first_name, last_name, is_admin, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users for admin panel: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading user data. Please try again.</div>';
}

// --- Handle Edit Mode (GET request) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, is_admin FROM users WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_user = $stmt->fetch();
        if (!$edit_user) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">User not found for editing.</div>';
        }
    } catch (PDOException $e) {
        error_log("Error fetching user for edit: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading user for editing.</div>';
    }
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
    <title>Manage Users - Admin Panel</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Manage Users</h2>

            <?php echo $message; // Display messages ?>

            <div class="mb-6 flex justify-between items-center">
                <a href="index.php" class="bg-olivine-2 hover:bg-moss-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">
                    &larr; Back to Admin Dashboard
                </a>
            </div>

            <!-- Edit User Form (conditionally displayed) -->
            <?php if ($edit_user): ?>
            <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 mb-8">
                <h3 class="text-2xl font-semibold text-moss-green mb-4">Edit User: <?php echo htmlspecialchars($edit_user['username']); ?></h3>
                <form action="manage_users.php" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($edit_user['id']); ?>">
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                        <input type="text" id="username" name="username" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($edit_user['username']); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                        <input type="email" id="email" name="email" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($edit_user['email']); ?>">
                    </div>
                    <div class="mb-6">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="is_admin" class="form-checkbox h-5 w-5 text-moss-green rounded-md focus:ring-olivine-2" <?php echo $edit_user['is_admin'] ? 'checked' : ''; ?>>
                            <span class="ml-2 text-gray-700 font-semibold">Is Admin</span>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="submit" name="edit_user_submit" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Update User</button>
                    </div>
                    <p class="text-center text-gray-600 text-sm mt-4">
                        <a href="manage_users.php" class="text-moss-green hover:underline">Cancel Edit</a>
                    </p>
                </form>
            </div>
            <?php endif; ?>

            <!-- Users List -->
            <h3 class="text-2xl font-semibold text-moss-green mb-4">All Registered Users</h3>
            <?php if (empty($users)): ?>
                <p class="text-center text-gray-600 text-lg">No users found in the system.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg shadow-md border border-olivine-2">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-tea-green text-left text-gray-700 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 border-b border-olivine">ID</th>
                                <th class="py-3 px-6 border-b border-olivine">Username</th>
                                <th class="py-3 px-6 border-b border-olivine">Email</th>
                                <th class="py-3 px-6 border-b border-olivine">Admin</th>
                                <th class="py-3 px-6 border-b border-olivine">Registered On</th>
                                <th class="py-3 px-6 border-b border-olivine text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach ($users as $user): ?>
                                <tr class="border-b border-olivine-2 hover:bg-nyanza">
                                    <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="py-3 px-6 text-left">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $user['is_admin'] ? 'bg-green-200 text-green-800' : 'bg-gray-200 text-gray-800'; ?>">
                                            <?php echo $user['is_admin'] ? 'Yes' : 'No'; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-6 text-left"><?php echo date('M j, Y H:i', strtotime($user['created_at'])); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex item-center justify-center">
                                            <a href="manage_users.php?action=edit&id=<?php echo htmlspecialchars($user['id']); ?>" class="w-4 mr-2 transform hover:text-reseda-green hover:scale-110" title="Edit User">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </a>
                                            <form action="manage_users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete user <?php echo htmlspecialchars($user['username']); ?>? This action cannot be undone.');" class="inline-block">
                                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                <button type="submit" name="delete_user" class="w-4 mr-2 transform hover:text-red-500 hover:scale-110" title="Delete User" <?php echo ($user['id'] === $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
