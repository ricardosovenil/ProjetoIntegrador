<?php
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistema_tutoria');

// Configurações do site
define('SITE_NAME', 'Sistema de Tutoria Acadêmica');
define('SITE_URL', 'http://localhost/ProjetoIntegrador-main/PI');
define('SITE_EMAIL', 'sistema.tutoria@example.com'); // Email do sistema

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sessão apenas se não houver uma sessão ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexão com o banco de dados
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
} 