<?php
// public/add_item.php - Page for adding new auction items

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in to list an item
requireLogin();

$message = '';
$categories = [];

// Fetch categories from the database to populate the dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading categories. Please try again.</div>';
}

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $start_price = floatval($_POST['start_price'] ?? 0);
    $reserve_price = !empty($_POST['reserve_price']) ? floatval($_POST['reserve_price']) : NULL;
    $buy_now_price = !empty($_POST['buy_now_price']) ? floatval($_POST['buy_now_price']) : NULL;
    $end_time = trim($_POST['end_time'] ?? ''); // Format: YYYY-MM-DDTHH:MM

    $seller_id = $_SESSION['user_id']; // The ID of the logged-in user

    // Basic server-side validation
    if (empty($title) || empty($description) || $category_id <= 0 || $start_price <= 0 || empty($end_time)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Please fill in all required fields (Title, Description, Category, Start Price, End Time).</div>';
    } elseif (!in_array($category_id, array_column($categories, 'id'))) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid category selected.</div>';
    } elseif (strtotime($end_time) <= time()) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">End time must be in the future.</div>';
    } elseif ($reserve_price !== NULL && $reserve_price < $start_price) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Reserve price cannot be less than the start price.</div>';
    } elseif ($buy_now_price !== NULL && $buy_now_price < $start_price) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Buy Now price cannot be less than the start price.</div>';
    } else {
        // Handle image upload (basic placeholder for now)
        $image_urls = [];
        // In a real application, you would handle file uploads here:
        // - Validate file type and size
        // - Move uploaded file to a secure directory (e.g., assets/images/items/)
        // - Store the path/URL in $image_urls array
        // For now, we'll just simulate with a placeholder if no actual upload mechanism is built yet.
        // Example: if ($_FILES['item_images']['name'][0] != '') { ... }
        // For simplicity, we'll assume no images are uploaded initially or use a default.
        // The `image_urls` column in `items` table is JSON type.

        // Convert end_time to MySQL datetime format
        $mysql_end_time = date('Y-m-d H:i:s', strtotime($end_time));
        $mysql_start_time = date('Y-m-d H:i:s'); // Auction starts now

        // Insert item into the database
        try {
            $stmt = $pdo->prepare("INSERT INTO items (title, description, category_id, seller_id, start_price, reserve_price, buy_now_price, start_time, end_time, status, image_urls) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $description,
                $category_id,
                $seller_id,
                $start_price,
                $reserve_price,
                $buy_now_price,
                $mysql_start_time,
                $mysql_end_time,
                'active', // Set status to 'active' immediately
                json_encode($image_urls) // Store empty JSON array for now
            ]);

            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Item listed successfully!</div>';
            // Optionally redirect after successful listing
            // header('Location: dashboard.php');
            // exit();

        } catch (PDOException $e) {
            error_log("Item listing failed: " . $e->getMessage());
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to list item due to a server error. Please try again.</div>';
        }
    }
}

// Determine navigation links based on login status (already handled in index.php, but repeated for consistency)
$nav_links = '';
if (isLoggedIn()) {
    $nav_links = '
        <li><a href="index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Home</a></li>
        <li><a href="#" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Browse Auctions</a></li>
        <li><a href="add_item.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Sell Item</a></li>
        <li><a href="dashboard.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Dashboard</a></li>
        <li><a href="dashboard.php?action=logout" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Logout</a></li>
    ';
} else {
    $nav_links = '
        <li><a href="index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Home</a></li>
        <li><a href="#" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Browse Auctions</a></li>
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
    <title>List New Item - Online Auction System</title>
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
        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-2xl border border-olivine-2">
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">List a New Auction Item</h2>

            <?php echo $message; // Display messages ?>

            <form action="add_item.php" method="POST" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Item Title:</label>
                        <input type="text" id="title" name="title" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="category_id" class="block text-gray-700 text-sm font-bold mb-2">Category:</label>
                        <select id="category_id" name="category_id" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required>
                            <option value="">Select a Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                    <textarea id="description" name="description" rows="6" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label for="start_price" class="block text-gray-700 text-sm font-bold mb-2">Starting Price ($):</label>
                        <input type="number" id="start_price" name="start_price" step="0.01" min="0.01" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($_POST['start_price'] ?? ''); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="end_time" class="block text-gray-700 text-sm font-bold mb-2">Auction End Time:</label>
                        <input type="datetime-local" id="end_time" name="end_time" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label for="reserve_price" class="block text-gray-700 text-sm font-bold mb-2">Reserve Price (Optional $):</label>
                        <input type="number" id="reserve_price" name="reserve_price" step="0.01" min="0" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($_POST['reserve_price'] ?? ''); ?>">
                        <p class="text-xs text-gray-500 mt-1">Auction only sells if bids reach this price.</p>
                    </div>
                    <div class="mb-4">
                        <label for="buy_now_price" class="block text-gray-700 text-sm font-bold mb-2">Buy Now Price (Optional $):</label>
                        <input type="number" id="buy_now_price" name="buy_now_price" step="0.01" min="0" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($_POST['buy_now_price'] ?? ''); ?>">
                        <p class="text-xs text-gray-500 mt-1">Allows immediate purchase at this price.</p>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="item_images" class="block text-gray-700 text-sm font-bold mb-2">Item Images (Optional):</label>
                    <input type="file" id="item_images" name="item_images[]" multiple accept="image/*" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Upload multiple images for your item.</p>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">List Item</button>
                </div>
            </form>
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
