<?php
// public/profile.php - User Profile Management Page

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in
requireLogin();

$message = '';
$user_data = [];
$current_user_id = $_SESSION['user_id'];

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, profile_picture, contact_info FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        // This should ideally not happen if requireLogin() works correctly
        logoutUser();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Error loading profile data. Please try again.</div>';
}

// Handle profile update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $contact_info = trim($_POST['contact_info'] ?? '');
    $email = trim($_POST['email'] ?? ''); // Allow email update

    // Basic validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">A valid email address is required.</div>';
    } else {
        // Check if new email already exists for another user
        $stmt_check_email = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check_email->execute([$email, $current_user_id]);
        if ($stmt_check_email->fetch()) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">This email is already registered by another user.</div>';
        } else {
            $profile_picture_path = $user_data['profile_picture']; // Keep existing path by default

            // Handle profile picture upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $target_dir = __DIR__ . "/../assets/images/profile_pictures/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true); // Create directory if it doesn't exist
                }

                $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($file_extension, $allowed_extensions)) {
                    $new_file_name = uniqid('profile_') . '.' . $file_extension;
                    $target_file = $target_dir . $new_file_name;

                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                        // Delete old profile picture if it exists and is not the default placeholder
                        if ($user_data['profile_picture'] && strpos($user_data['profile_picture'], 'placehold.co') === false) {
                            $old_file_path = __DIR__ . '/../' . $user_data['profile_picture']; // Adjust path relative to this script
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }
                        $profile_picture_path = 'assets/images/profile_pictures/' . $new_file_name; // Path relative to public/
                    } else {
                        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to upload profile picture.</div>';
                    }
                } else {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.</div>';
                }
            }

            try {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_picture = ?, contact_info = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $email, $profile_picture_path, $contact_info, $current_user_id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">Profile updated successfully!</div>';
                // Re-fetch updated data to display immediately
                $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, profile_picture, contact_info FROM users WHERE id = ?");
                $stmt->execute([$current_user_id]);
                $user_data = $stmt->fetch();
            } catch (PDOException $e) {
                error_log("Error updating user profile: " . $e->getMessage());
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Failed to update profile due to a server error.</div>';
            }
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
    <title>My Profile - Online Auction System</title>
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
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">My Profile</h2>

            <?php echo $message; // Display messages ?>

            <div class="flex flex-col md:flex-row items-center md:items-start gap-6 mb-8">
                <div class="flex-shrink-0">
                    <img src="<?php echo !empty($user_data['profile_picture']) ? htmlspecialchars($user_data['profile_picture']) : 'https://placehold.co/150x150/D0F0C0/6B8E23?text=Profile'; ?>"
                         alt="Profile Picture"
                         class="w-32 h-32 rounded-full object-cover border-4 border-olivine-2 shadow-md">
                </div>
                <div class="flex-grow text-center md:text-left">
                    <p class="text-xl font-semibold text-moss-green">Username: <?php echo htmlspecialchars($user_data['username']); ?></p>
                    <p class="text-gray-700">Email: <?php echo htmlspecialchars($user_data['email']); ?></p>
                    <p class="text-gray-700">Name: <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></p>
                    <p class="text-gray-700">Contact: <?php echo htmlspecialchars($user_data['contact_info']); ?></p>
                </div>
            </div>

            <h3 class="text-2xl font-semibold text-moss-green mb-4">Edit Profile</h3>
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label for="first_name" class="block text-gray-700 text-sm font-bold mb-2">First Name:</label>
                        <input type="text" id="first_name" name="first_name" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="last_name" class="block text-gray-700 text-sm font-bold mb-2">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                    <input type="email" id="email" name="email" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                </div>
                <div class="mb-4">
                    <label for="contact_info" class="block text-gray-700 text-sm font-bold mb-2">Contact Info (e.g., Phone):</label>
                    <input type="text" id="contact_info" name="contact_info" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" value="<?php echo htmlspecialchars($user_data['contact_info'] ?? ''); ?>">
                </div>
                <div class="mb-6">
                    <label for="profile_picture" class="block text-gray-700 text-sm font-bold mb-2">Profile Picture:</label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Upload a new profile picture (JPG, PNG, GIF).</p>
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Save Profile</button>
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
