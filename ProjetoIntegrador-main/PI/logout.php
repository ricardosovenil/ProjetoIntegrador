<?php
session_start(); // Inicia a sessão para acessar as variáveis de sessão

require_once 'config.php';

// Registrar logout na tabela login_history
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    try {
        $session_id = session_id();
        $user_id = $_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];

        $stmt = $conn->prepare("UPDATE login_history SET logout_time = CURRENT_TIMESTAMP WHERE user_id = ? AND user_type = ? AND session_id = ? AND logout_time IS NULL");
            
        if ($stmt->execute([$user_id, $user_type, $session_id])) {
            error_log("LOGIN_HISTORY: Logout registrado para user ID " . $user_id . " (" . $user_type . ").");
        } else {
            error_log("LOGIN_HISTORY ERROR: Falha ao registrar logout para user ID " . $user_id . " (" . $user_type . "): " . implode(" ", $stmt->errorInfo()));
        }
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("DATABASE ERROR in logout.php: " . $e->getMessage());
    }
}

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie da sessão
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destruir a sessão
session_destroy();

// Redirecionar para a página inicial
header('Location: index.php');
exit;