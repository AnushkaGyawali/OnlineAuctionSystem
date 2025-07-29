<?php
// includes/auth_functions.php - Reusable authentication and auction helper functions

/**
 * Hashes a given password securely.
 * @param string $password The plain-text password.
 * @return string The hashed password.
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID);
}

/**
 * Verifies a plain-text password against a hashed password.
 * @param string $password The plain-text password.
 * @param string $hashedPassword The hashed password from the database.
 * @return bool True if the password matches, false otherwise.
 */
function verifyPassword(string $password, string $hashedPassword): bool {
    return password_verify($password, $hashedPassword);
}

/**
 * Registers a new user in the database.
 * @param PDO $pdo The PDO database connection object.
 * @param string $username The desired username.
 * @param string $email The user's email address.
 * @param string $password The plain-text password.
 * @return bool|string True on success, or an error message string on failure.
 */
function registerUser(PDO $pdo, string $username, string $email, string $password) {
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return "Username or Email already exists.";
    }

    // Hash the password
    $hashedPassword = hashPassword($password);

    // Insert new user into the database
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword]);
        return true; // Registration successful
    } catch (PDOException $e) {
        // Log the error (e.g., to a file)
        error_log("User registration failed: " . $e->getMessage());
        return "Registration failed due to a server error. Please try again.";
    }
}

/**
 * Logs in a user by verifying credentials and setting up a session.
 * @param PDO $pdo The PDO database connection object.
 * @param string $usernameOrEmail The username or email provided by the user.
 * @param string $password The plain-text password.
 * @return bool|string True on successful login, or an error message string on failure.
 */
function loginUser(PDO $pdo, string $usernameOrEmail, string $password) {
    // Fetch user by username or email
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, is_admin FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $user = $stmt->fetch();

    if ($user && verifyPassword($password, $user['password_hash'])) {
        // Password is correct, set up session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        session_regenerate_id(true); // Prevent session fixation attacks
        return true; // Login successful
    } else {
        return "Invalid username/email or password.";
    }
}

/**
 * Checks if a user is currently logged in.
 * @return bool True if a user session exists, false otherwise.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Redirects to the login page if the user is not logged in.
 * @param string $redirectUrl The URL to redirect to if not logged in. Defaults to login.php.
 */
function requireLogin(string $redirectUrl = 'login.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Logs out the current user by destroying the session.
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();
}

/**
 * Calculates the appropriate bid increment based on the current bid.
 * This can be customized based on auction rules.
 * @param float $currentBid The current highest bid amount.
 * @return float The recommended bid increment.
 */
function getNextBidIncrement(float $currentBid): float {
    if ($currentBid < 10.00) {
        return 0.50;
    } elseif ($currentBid < 50.00) {
        return 1.00;
    } elseif ($currentBid < 100.00) {
        return 2.50;
    } elseif ($currentBid < 500.00) {
        return 5.00;
    } elseif ($currentBid < 1000.00) {
        return 10.00;
    } else {
        return 25.00;
    }
}
