<?php
// public/admin/manage_items.php - Admin Item Management Page (CRUD)

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Ensure user is logged in AND is an admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$items = [];
$categories = []; // For the category dropdown in edit form
$edit_item = null; // To hold item data if in edit mode

// Fetch categories for the dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories for item management: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading categories.</div>';
}


// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_item_submit'])) {
        $id = intval($_POST['item_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $start_price = floatval($_POST['start_price'] ?? 0);
        $reserve_price = !empty($_POST['reserve_price']) ? floatval($_POST['reserve_price']) : NULL;
        $buy_now_price = !empty($_POST['buy_now_price']) ? floatval($_POST['buy_now_price']) : NULL;
        $end_time = trim($_POST['end_time'] ?? ''); // Format: YYYY-MM-DDTHH:MM
        $status = trim($_POST['status'] ?? '');

        // Basic validation
        if ($id <= 0 || empty($title) || empty($description) || $category_id <= 0 || $start_price <= 0 || empty($end_time) || empty($status)) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">All required fields must be filled.</div>';
        } elseif (!in_array($category_id, array_column($categories, 'id'))) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid category selected.</div>';
        } elseif (!in_array($status, ['active', 'closed', 'pending', 'sold', 'cancelled'])) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid item status.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE items SET title = ?, description = ?, category_id = ?, start_price = ?, reserve_price = ?, buy_now_price = ?, end_time = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category_id, $start_price, $reserve_price, $buy_now_price, $end_time, $status, $id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Item updated successfully!</div>';
                // Clear edit mode after successful update
                header('Location: manage_items.php');
                exit();
            } catch (PDOException $e) {
                error_log("Error updating item: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to update item due to a server error.</div>';
            }
        }
    } elseif (isset($_POST['delete_item'])) {
        $id = intval($_POST['item_id'] ?? 0);

        if ($id <= 0) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid item ID for deletion.</div>';
        } else {
            try {
                // Delete related bids first (due to foreign key constraints if not CASCADE)
                $stmt_delete_bids = $pdo->prepare("DELETE FROM bids WHERE item_id = ?");
                $stmt_delete_bids->execute([$id]);

                // Delete related sold item entry (if exists)
                $stmt_delete_sold = $pdo->prepare("DELETE FROM sold_items WHERE item_id = ?");
                $stmt_delete_sold->execute([$id]);

                // Now delete the item itself
                $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
                $stmt->execute([$id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Item deleted successfully!</div>';
            } catch (PDOException $e) {
                error_log("Error deleting item: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to delete item due to a server error.</div>';
            }
        }
    }
}

// --- Fetch all items for display ---
try {
    $stmt = $pdo->query("
        SELECT
            i.id,
            i.title,
            i.current_bid,
            i.start_price,
            i.end_time,
            i.status,
            u.username AS seller_username,
            c.name AS category_name
        FROM
            items i
        JOIN
            users u ON i.seller_id = u.id
        JOIN
            categories c ON i.category_id = c.id
        ORDER BY
            i.created_at DESC
    ");
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching items for admin panel: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading item data. Please try again.</div>';
}

// --- Handle Edit Mode (GET request) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT id, title, description, category_id, start_price, reserve_price, buy_now_price, end_time, status FROM items WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_item = $stmt->fetch();
        if (!$edit_item) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Item not found for editing.</div>';
        } else {
            // Format end_time for datetime-local input
            $edit_item['end_time'] = date('Y-m-d\TH:i', strtotime($edit_item['end_time']));
        }
    } catch (PDOException $e) {
        error_log("Error fetching item for edit: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading item for editing.</div>';
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
    <title>Manage Items - Admin Panel</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Manage Auction Items</h2>

            <?php echo $message; // Display messages ?>

            <div class="mb-6 flex justify-between items-center">
                <a href="index.php" class="bg-olivine-2 hover:bg-moss-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">
                    &larr; Back to Admin Dashboard
                </a>
            </div>

            <!-- Edit Item Form (conditionally displayed) -->
            <?php if ($edit_item): ?>
            <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 mb-8">
                <h3 class="text-2xl font-semibold text-moss-green mb-4">Edit Item: <?php echo htmlspecialchars($edit_item['title']); ?></h3>
                <form action="manage_items.php" method="POST">
                    <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($edit_item['id']); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Item Title:</label>
                            <input type="text" id="title" name="title" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($edit_item['title']); ?>">
                        </div>
                        <div class="mb-4">
                            <label for="category_id" class="block text-gray-700 text-sm font-bold mb-2">Category:</label>
                            <select id="category_id" name="category_id" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required>
                                <option value="">Select a Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo ($edit_item['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                        <textarea id="description" name="description" rows="6" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required><?php echo htmlspecialchars($edit_item['description']); ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="start_price" class="block text-gray-700 text-sm font-bold mb-2">Starting Price ($):</label>
                            <input type="number" id="start_price" name="start_price" step="0.01" min="0.01" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($edit_item['start_price']); ?>">
                        </div>
                        <div class="mb-4">
                            <label for="end_time" class="block text-gray-700 text-sm font-bold mb-2">Auction End Time:</label>
                            <input type="datetime-local" id="end_time" name="end_time" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($edit_item['end_time']); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="reserve_price" class="block text-gray-700 text-sm font-bold mb-2">Reserve Price (Optional $):</label>
                            <input type="number" id="reserve_price" name="reserve_price" step="0.01" min="0" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($edit_item['reserve_price']); ?>">
                        </div>
                        <div class="mb-4">
                            <label for="buy_now_price" class="block text-gray-700 text-sm font-bold mb-2">Buy Now Price (Optional $):</label>
                            <input type="number" id="buy_now_price" name="buy_now_price" step="0.01" min="0" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($edit_item['buy_now_price']); ?>">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                        <select id="status" name="status" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required>
                            <option value="active" <?php echo ($edit_item['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="closed" <?php echo ($edit_item['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
                            <option value="pending" <?php echo ($edit_item['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="sold" <?php echo ($edit_item['status'] == 'sold') ? 'selected' : ''; ?>>Sold</option>
                            <option value="cancelled" <?php echo ($edit_item['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="submit" name="edit_item_submit" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Update Item</button>
                    </div>
                    <p class="text-center text-gray-600 text-sm mt-4">
                        <a href="manage_items.php" class="text-moss-green hover:underline">Cancel Edit</a>
                    </p>
                </form>
            </div>
            <?php endif; ?>

            <!-- Items List -->
            <h3 class="text-2xl font-semibold text-moss-green mb-4">All Auction Items</h3>
            <?php if (empty($items)): ?>
                <p class="text-center text-gray-600 text-lg">No auction items found in the system.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg shadow-md border border-olivine-2">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-tea-green text-left text-gray-700 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 border-b border-olivine">ID</th>
                                <th class="py-3 px-6 border-b border-olivine">Title</th>
                                <th class="py-3 px-6 border-b border-olivine">Seller</th>
                                <th class="py-3 px-6 border-b border-olivine">Category</th>
                                <th class="py-3 px-6 border-b border-olivine">Current Bid</th>
                                <th class="py-3 px-6 border-b border-olivine">Ends</th>
                                <th class="py-3 px-6 border-b border-olivine">Status</th>
                                <th class="py-3 px-6 border-b border-olivine text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach ($items as $item): ?>
                                <tr class="border-b border-olivine-2 hover:bg-nyanza">
                                    <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($item['id']); ?></td>
                                    <td class="py-3 px-6 text-left">
                                        <a href="../item_detail.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="text-reseda-green hover:underline">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($item['seller_username']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td class="py-3 px-6 text-left font-semibold">$<?php echo number_format($item['current_bid'] ?? $item['start_price'], 2); ?></td>
                                    <td class="py-3 px-6 text-left text-sm"><?php echo date('M j, Y H:i', strtotime($item['end_time'])); ?></td>
                                    <td class="py-3 px-6 text-left">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                                            <?php
                                            if ($item['status'] === 'active' && strtotime($item['end_time']) > time()) {
                                                echo 'bg-green-200 text-green-800';
                                            } elseif ($item['status'] === 'sold') {
                                                echo 'bg-blue-200 text-blue-800';
                                            } elseif (strtotime($item['end_time']) <= time() && $item['status'] === 'active') { // Ended but not sold
                                                echo 'bg-yellow-200 text-yellow-800';
                                            } else {
                                                echo 'bg-gray-200 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars(ucfirst($item['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex item-center justify-center">
                                            <a href="manage_items.php?action=edit&id=<?php echo htmlspecialchars($item['id']); ?>" class="w-4 mr-2 transform hover:text-reseda-green hover:scale-110" title="Edit Item">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </a>
                                            <form action="manage_items.php" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to delete item <?php echo htmlspecialchars($item['title']); ?>? This will also delete all associated bids and sold records.');" class="inline-block">
                                                <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['id']); ?>">
                                                <button type="submit" name="delete_item" class="w-4 mr-2 transform hover:text-red-500 hover:scale-110" title="Delete Item">
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
