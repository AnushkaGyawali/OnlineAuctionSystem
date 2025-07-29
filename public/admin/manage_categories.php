<?php
// public/admin/manage_categories.php - Admin Category Management Page

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Ensure user is logged in AND is an admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$categories = [];
$edit_category = null; // To hold category data if in edit mode

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Category name cannot be empty.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Category added successfully!</div>';
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') { // SQLSTATE for integrity constraint violation (e.g., duplicate unique key)
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Category name already exists.</div>';
                } else {
                    error_log("Error adding category: " . $e->getMessage());
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to add category due to a server error.</div>';
                }
            }
        }
    } elseif (isset($_POST['edit_category_submit'])) {
        $id = intval($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($id <= 0 || empty($name)) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid category ID or name.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Category updated successfully!</div>';
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Category name already exists.</div>';
                } else {
                    error_log("Error updating category: " . $e->getMessage());
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to update category due to a server error.</div>';
                }
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = intval($_POST['category_id'] ?? 0);

        if ($id <= 0) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid category ID for deletion.</div>';
        } else {
            try {
                // Check if any items are associated with this category before deleting
                $stmt_check_items = $pdo->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
                $stmt_check_items->execute([$id]);
                if ($stmt_check_items->fetchColumn() > 0) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Cannot delete category: items are currently associated with it.</div>';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Category deleted successfully!</div>';
                }
            } catch (PDOException $e) {
                error_log("Error deleting category: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to delete category due to a server error.</div>';
            }
        }
    }
}

// --- Fetch Categories for Display ---
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories for admin panel: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading category data. Please try again.</div>';
}

// --- Handle Edit Mode ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT id, name, description FROM categories WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_category = $stmt->fetch();
        if (!$edit_category) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Category not found for editing.</div>';
        }
    } catch (PDOException $e) {
        error_log("Error fetching category for edit: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading category for editing.</div>';
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
    <title>Manage Categories - Admin Panel</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Manage Categories</h2>

            <?php echo $message; // Display messages ?>

            <div class="mb-6 flex justify-between items-center">
                <a href="index.php" class="bg-olivine-2 hover:bg-moss-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">
                    &larr; Back to Admin Dashboard
                </a>
            </div>

            <!-- Add/Edit Category Form -->
            <div class="bg-tea-green p-6 rounded-lg shadow-md border border-olivine-2 mb-8">
                <h3 class="text-2xl font-semibold text-moss-green mb-4"><?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?></h3>
                <form action="manage_categories.php" method="POST">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($edit_category['id']); ?>">
                    <?php endif; ?>
                    <div class="mb-4">
                        <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Category Name:</label>
                        <input type="text" id="name" name="name" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>">
                    </div>
                    <div class="mb-6">
                        <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description (Optional):</label>
                        <textarea id="description" name="description" rows="3" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="submit" name="<?php echo $edit_category ? 'edit_category_submit' : 'add_category'; ?>" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">
                            <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                        </button>
                    </div>
                    <?php if ($edit_category): ?>
                        <p class="text-center text-gray-600 text-sm mt-4">
                            <a href="manage_categories.php" class="text-moss-green hover:underline">Cancel Edit</a>
                        </p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Categories List -->
            <h3 class="text-2xl font-semibold text-moss-green mb-4">Existing Categories</h3>
            <?php if (empty($categories)): ?>
                <p class="text-center text-gray-600 text-lg">No categories defined yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg shadow-md border border-olivine-2">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-tea-green text-left text-gray-700 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 border-b border-olivine">ID</th>
                                <th class="py-3 px-6 border-b border-olivine">Name</th>
                                <th class="py-3 px-6 border-b border-olivine">Description</th>
                                <th class="py-3 px-6 border-b border-olivine text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach ($categories as $category): ?>
                                <tr class="border-b border-olivine-2 hover:bg-nyanza">
                                    <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($category['id']); ?></td>
                                    <td class="py-3 px-6 text-left font-semibold"><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($category['description']); ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex item-center justify-center">
                                            <a href="manage_categories.php?action=edit&id=<?php echo htmlspecialchars($category['id']); ?>" class="w-4 mr-2 transform hover:text-reseda-green hover:scale-110" title="Edit Category">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </a>
                                            <form action="manage_categories.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this category? This cannot be undone if no items are linked.');" class="inline-block">
                                                <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category['id']); ?>">
                                                <button type="submit" name="delete_category" class="w-4 mr-2 transform hover:text-red-500 hover:scale-110" title="Delete Category">
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
