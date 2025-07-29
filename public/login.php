<?php
// public/login.php - User Login Page for Online Auction System

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_functions.php';

$message = '';

// Display success message from registration if redirected
if (isset($_SESSION['success_message'])) {
    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']); // Clear the message after displaying
}

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usernameOrEmail) || empty($password)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">Please enter your username/email and password.</div>';
    } else {
        $login_result = loginUser($pdo, $usernameOrEmail, $password);

        if ($login_result === true) {
            // Login successful, redirect to dashboard or intended page
            header('Location: dashboard.php');
            exit();
        } else {
            // Login failed
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($login_result) . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Online Auction System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="font-sans antialiased flex flex-col min-h-screen bg-gradient-to-br from-nyanza to-tea-green text-gray-800">
    <header class="bg-reseda-green text-white p-4 shadow-md">
        <nav class="container mx-auto flex justify-between items-center flex-wrap">
            <a href="index.php" class="text-2xl font-bold text-white py-2">AuctionHub</a>
            <ul class="flex space-x-6 flex-wrap justify-center mt-4 md:mt-0">
                <li><a href="index.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Home</a></li>
                <li><a href="#" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Browse Auctions</a></li>
                <li><a href="#" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Sell Item</a></li>
                <li><a href="#" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Dashboard</a></li>
                <li><a href="login.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Login</a></li>
                <li><a href="register.php" class="text-white hover:text-nyanza font-semibold py-2 transition-colors duration-300">Register</a></li>
            </ul>
        </nav>
    </header>

    <main class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md border border-olivine-2">
            <h2 class="text-3xl font-bold text-center text-moss-green mb-6">Login to Your Account</h2>

            <?php echo $message; // Display messages ?>

            <form action="login.php" method="POST">
                <div class="mb-4">
                    <label for="username_or_email" class="block text-gray-700 text-sm font-bold mb-2">Username/Email:</label>
                    <input type="text" id="username_or_email" name="username_or_email" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required value="<?php echo htmlspecialchars($usernameOrEmail ?? ''); ?>">
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                    <input type="password" id="password" name="password" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-olivine-2 focus:border-transparent" required>
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-moss-green hover:bg-reseda-green text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline transition-all duration-300 w-full">Login</button>
                </div>
                <p class="text-center text-gray-600 text-sm mt-4">
                    Don't have an account? <a href="register.php" class="text-moss-green hover:underline">Register here</a>
                </p>
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
