<?php
/**
 * Funções utilitárias para o sistema de mentoria acadêmica
 */

/**
 * Obtém uma conexão com o banco de dados
 * @return PDO Objeto de conexão PDO
 */
function getDBConnection() {
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
        return $conn;
    } catch(PDOException $e) {
        die("Erro na conexão: " . $e->getMessage());
    }
}

/**
 * Sanitiza uma string para uso seguro
 * @param string $data String a ser sanitizada
 * @return string String sanitizada
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Define uma mensagem flash
 * @param string $type Tipo da mensagem (success, error, warning, info)
 * @param string $message Conteúdo da mensagem
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Verifica se existe uma mensagem flash
 * @param string $type Tipo da mensagem (opcional)
 * @return bool
 */
function hasFlashMessage($type = null) {
    if ($type === null) {
        return isset($_SESSION['flash_message']);
    }
    return isset($_SESSION['flash_message']) && $_SESSION['flash_message']['type'] === $type;
}

/**
 * Obtém a mensagem flash e a remove da sessão
 * @return array|null Array com type e message ou null se não houver mensagem
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Requer autenticação do usuário
 * Redireciona para login se não estiver autenticado
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Formata uma data para o padrão brasileiro
 * @param string $date Data no formato Y-m-d
 * @return string Data formatada como d/m/Y
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Formata um horário para o padrão brasileiro
 * @param string $time Horário no formato H:i:s
 * @return string Horário formatado como H:i
 */
function formatTime($time) {
    return date('H:i', strtotime($time));
}

/**
 * Gera um token CSRF
 * @return string Token gerado
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica se o token CSRF é válido
 * @param string $token Token a ser verificado
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitiza uma string para uso em HTML
 * @param string $str String a ser sanitizada
 * @return string String sanitizada
 */
function h($str) {
    if ($str === null) {
        return '';
    }
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica se uma requisição é POST
 * @return bool
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Verifica se uma requisição é AJAX
 * @return bool
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Redireciona para uma URL
 * @param string $url URL para redirecionamento
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Retorna a URL base do sistema
 * @return string URL base
 */
function baseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = dirname($_SERVER['PHP_SELF']);
    return "$protocol://$host$base";
}

/**
 * Cria uma notificação no banco de dados para um usuário específico
 * @param int $userId ID do usuário receptor da notificação
 * @param string $userType Tipo do usuário (estudante, tutor, coordenador)
 * @param string $message Conteúdo da notificação
 * @return bool True se a notificação foi criada com sucesso, false caso contrário
 */
function createNotification($userId, $userType, $message) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_type, message) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $userType, $message]);
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação: " . $e->getMessage());
        return false;
    }
} 