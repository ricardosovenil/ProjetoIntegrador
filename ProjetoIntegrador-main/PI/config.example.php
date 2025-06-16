<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_NAME', 'pi_db');

// Configurações do Site
define('SITE_NAME', 'Sistema de Mentoria Acadêmica');
define('SITE_URL', 'http://localhost/sistema-mentoria');
define('ADMIN_EMAIL', 'admin@exemplo.com');

// Configurações de Email
define('SMTP_HOST', 'smtp.exemplo.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu_email@exemplo.com');
define('SMTP_PASS', 'sua_senha_email');
define('SMTP_FROM', 'noreply@exemplo.com');
define('SMTP_FROM_NAME', SITE_NAME);

// Configurações de Segurança
define('HASH_COST', 12); // Custo do bcrypt
define('SESSION_LIFETIME', 3600); // 1 hora
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hora

// Configurações de Upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Configurações de Log
define('LOG_DIR', __DIR__ . '/logs');
define('ERROR_LOG', LOG_DIR . '/error.log');
define('ACCESS_LOG', LOG_DIR . '/access.log');

// Configurações de Debug
define('DEBUG_MODE', false);
define('DISPLAY_ERRORS', false);

// Configurações de Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de Idioma
setlocale(LC_ALL, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil'); 