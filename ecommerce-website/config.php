<?php




define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_db');

// Site Configuration
define('SITE_NAME', 'AURORA STORE');
define('SITE_URL', 'http://localhost/ecommerce-website');

// HARDCODED ADMIN CREDENTIALS
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// Determine if we're in admin area
$isAdminArea = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);

// Set different session names for admin vs user
if ($isAdminArea) {
    $sessionName = 'admin_session';
} else {
    $sessionName = 'user_session';
}

// Start Session with proper name
if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_httponly' => true,
        'cookie_secure' => false,
        'cookie_samesite' => 'Lax'
    ]);
}

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}



// USER Session Functions (for main site)
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && session_name() === 'user_session';
}

function userLogin($userId, $username, $email) {
    // Only set user session if we're using user_session
    if (session_name() === 'user_session') {
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['login_time'] = time();
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function userLogout() {
    if (session_name() === 'user_session') {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        return true;
    }
    return false;
}

// ADMIN Session Functions (for admin area)
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && session_name() === 'admin_session';
}

function adminLogin($username) {
    // Only set admin session if we're using admin_session
    if (session_name() === 'admin_session') {
        $_SESSION['admin_id'] = 'admin_' . time();
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function adminLogout() {
    if (session_name() === 'admin_session') {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        return true;
    }
    return false;
}


// GENERAL FUNCTIONS


function redirect($url) {
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Compatibility functions (for existing code)
function isLoggedIn() {
    // This will work for both, but be careful
    if (session_name() === 'user_session') {
        return isUserLoggedIn();
    } else {
        return isAdminLoggedIn();
    }
}

function isAdmin() {
    return isAdminLoggedIn();
}
?>

