<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_tutoria');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('SITE_NAME', 'Sistema de Tutoria Acadêmica');
define('SITE_URL', 'http://localhost/ProjetoIntegrador-main/PI');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Database connection function
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Erro na conexão: " . $e->getMessage());
    }
}

// Security functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function isAuthenticated() {
    return isset($_SESSION['user_id']);
}
?> 