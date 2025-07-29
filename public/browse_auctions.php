<?php
// public/browse_auctions.php - Page to browse active auction items with search and filters

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php'; // For isLoggedIn() and navigation

$message = '';
$active_items = [];
$categories = []; // For category filter dropdown

// Get search and filter parameters
$search_query = trim($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$min_price = floatval($_GET['min_price'] ?? 0);
$max_price = !empty($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$sort_by = $_GET['sort_by'] ?? 'end_time_asc'; // Default sort

// Fetch categories for the filter dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories for browse page: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading categories for filters.</div>';
}

// Build SQL query dynamically based on filters
$sql = "
    SELECT
        i.id,
        i.title,
        i.description,
        i.current_bid,
        i.start_price,
        i.end_time,
        i.image_urls,
        u.username as seller_username
    FROM
        items i
    JOIN
        users u ON i.seller_id = u.id
    WHERE
        i.status = 'active' AND i.end_time > NOW()
";

$params = [];

if (!empty($search_query)) {
    $sql .= " AND (i.title LIKE ? OR i.description LIKE ?)";
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
}

if ($category_filter > 0) {
    $sql .= " AND i.category_id = ?";
    $params[] = $category_filter;
}

if ($min_price > 0) {
    $sql .= " AND (i.current_bid >= ? OR i.start_price >= ?)";
    $params[] = $min_price;
    $params[] = $min_price;
}

if ($max_price !== null && $max_price > 0) {
    $sql .= " AND (i.current_bid <= ? OR i.start_price <= ?)";
    $params[] = $max_price;
    $params[] = $max_price;
}

// Add sorting
switch ($sort_by) {
    case 'price_asc':
        $sql .= " ORDER BY COALESCE(i.current_bid, i.start_price) ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY COALESCE(i.current_bid, i.start_price) DESC";
        break;
    case 'title_asc':
        $sql .= " ORDER BY i.title ASC";
        break;
    case 'title_desc':
        $sql .= " ORDER BY i.title DESC";
        break;
    case 'end_time_desc':
        $sql .= " ORDER BY i.end_time DESC";
        break;
    case 'end_time_asc':
    default:
        $sql .= " ORDER BY i.end_time ASC";
        break;
}


try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $active_items = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching active items with filters: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading auction items. Please try again.</div>';
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
    <title>Browse Auctions - Online Auction System</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-8">Active Auctions</h2>

            <?php echo $message; // Display messages ?>

            <!-- Search and Filter Form -->
            <form action="browse_auctions.php" method="GET" class="mb-8 p-6 bg-nyanza rounded-lg shadow-inner border border-olivine-2">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="col-span-full">
                        <label for="search" class="block text-gray-700 text-sm font-bold mb-2">Search by Keyword:</label>
                        <input type="text" id="search" name="search" placeholder="e.g., vintage watch, antique" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>

                    <div>
                        <label for="category" class="block text-gray-700 text-sm font-bold mb-2">Category:</label>
                        <select id="category" name="category" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="min_price" class="block text-gray-700 text-sm font-bold mb-2">Min Price ($):</label>
                        <input type="number" id="min_price" name="min_price" step="0.01" min="0" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($min_price > 0 ? $min_price : ''); ?>">
                    </div>

                    <div>
                        <label for="max_price" class="block text-gray-700 text-sm font-bold mb-2">Max Price ($):</label>
                        <input type="number" id="max_price" name="max_price" step="0.01" min="0" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($max_price !== null && $max_price > 0 ? $max_price : ''); ?>">
                    </div>

                    <div>
                        <label for="sort_by" class="block text-gray-700 text-sm font-bold mb-2">Sort By:</label>
                        <select id="sort_by" name="sort_by" class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent">
                            <option value="end_time_asc" <?php echo ($sort_by == 'end_time_asc') ? 'selected' : ''; ?>>Ending Soonest</option>
                            <option value="end_time_desc" <?php echo ($sort_by == 'end_time_desc') ? 'selected' : ''; ?>>Ending Latest</option>
                            <option value="price_asc" <?php echo ($sort_by == 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo ($sort_by == 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="title_asc" <?php echo ($sort_by == 'title_asc') ? 'selected' : ''; ?>>Title: A-Z</option>
                            <option value="title_desc" <?php echo ($sort_by == 'title_desc') ? 'selected' : ''; ?>>Title: Z-A</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="submit" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">Apply Filters</button>
                    <a href="browse_auctions.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg transition-colors duration-300">Clear Filters</a>
                </div>
            </form>


            <?php if (empty($active_items)): ?>
                <p class="text-center text-gray-600 text-lg">No active auctions found matching your criteria. Check back later!</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($active_items as $item):
                        // Decode image_urls JSON, fallback to empty array if invalid or null
                        $image_urls = json_decode($item['image_urls'] ?? '[]', true);
                        $display_image = !empty($image_urls[0]) ? htmlspecialchars($image_urls[0]) : 'https://placehold.co/400x300/D0F0C0/6B8E23?text=No+Image'; // Placeholder image
                    ?>
                        <div class="bg-tea-green rounded-lg shadow-md overflow-hidden border border-olivine-2 flex flex-col">
                            <img src="<?php echo $display_image; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-48 object-cover">
                            <div class="p-4 flex flex-col flex-grow">
                                <h3 class="text-xl font-semibold text-moss-green mb-2 truncate"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <p class="text-sm text-gray-600 mb-2">Seller: <?php echo htmlspecialchars($item['seller_username']); ?></p>
                                <p class="text-gray-700 text-sm mb-3 line-clamp-3"><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="mt-auto pt-2 border-t border-olivine-2">
                                    <p class="text-lg font-bold text-reseda-green">
                                        Current Bid: $<?php echo number_format($item['current_bid'] ?? $item['start_price'], 2); ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Ends: <span class="font-medium"><?php echo date('M j, Y H:i', strtotime($item['end_time'])); ?></span>
                                    </p>
                                    <a href="item_detail.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="mt-4 block bg-moss-green text-white px-4 py-2 rounded-lg text-center hover:bg-reseda-green transition-colors duration-300">View Details</a>
                                </div>
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
