<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'estudante';
$error = '';

error_log("login.php acessado. Método: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Requisição POST recebida.");
    error_log("Valor de _POST['type']: " . (isset($_POST['type']) ? $_POST['type'] : 'N/A'));

    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $senha = isset($_POST['senha']) ? $_POST['senha'] : '';
    $userType = isset($_POST['type']) ? $_POST['type'] : '';

    error_log("Dados recebidos - Email: " . $email . " | Tipo: " . $userType);

    if (empty($email) || empty($senha)) {
        $error = "Por favor, preencha todos os campos.";
        error_log("Erro de login: Campos vazios.");
    } else {
        try {
            $conn = getDBConnection();
            
            if ($conn) {
                error_log("Conexão com o banco de dados estabelecida.");
                $table = ($userType === 'tutor') ? 'tutores' : 'estudantes';
                error_log("Buscando usuário na tabela: " . $table . " com email: " . $email);

                $stmt = $conn->prepare("SELECT id, nome, senha, email FROM $table WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                error_log("Dados do usuário buscado para email " . $email . ": " . print_r($user, true));

                error_log("Usuário encontrado: " . ($user ? "Sim" : "Não"));

                if ($user) {
                    error_log("Verificando senha para " . $user['email']);
                    if (password_verify($senha, $user['senha'])) {
                        error_log("Verificação de senha: Bem-sucedida.");
                        error_log("Login bem-sucedido para " . $userType . ": " . $user['nome']);
                        
                        $_SESSION['user_type'] = $userType;
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['nome'];

                        // Registrar login na tabela login_history
                        $session_id = session_id();
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $stmt_log = $conn->prepare("INSERT INTO login_history (user_id, user_type, session_id, ip_address) VALUES (?, ?, ?, ?)");
                        
                        if ($stmt_log->execute([$user["id"], $userType, $session_id, $ip_address])) {
                            error_log("LOGIN_HISTORY: User ID " . $user["id"] . " (" . $userType . ") logado com sucesso.");
                        } else {
                            error_log("LOGIN_HISTORY ERROR: Falha ao inserir login para user ID " . $user["id"] . " (" . $userType . "): " . implode(" ", $stmt_log->errorInfo()));
                        }
                        $stmt_log->closeCursor();

                        error_log("Sessão definida para " . $userType . ". Redirecionando...");
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = "E-mail ou senha inválidos."; // Mensagem genérica por segurança
                        error_log("Erro de login: Senha incorreta para email: " . $email);
                    }
                } else {
                    $error = "E-mail ou senha inválidos."; // Mensagem genérica por segurança
                    error_log("Erro de login: Usuário não encontrado com email: " . $email);
                }
            } else {
                $error = "Erro ao conectar com o banco de dados.";
                error_log("Erro de login: Falha na conexão com o banco de dados.");
            }
        } catch (PDOException $e) {
            error_log("DATABASE ERROR in login.php: " . $e->getMessage());
            $error = "Erro interno do servidor. Tente novamente mais tarde.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-orange: #E8924A;
            --secondary-orange: #D4833D;
            --warm-green: #8BA572;
            --dark-green: #6B8B5A;
            --cream: #F4E5B8;
            --warm-cream: #F8F0D6;
            --brown: #8B5A3C;
            --dark-brown: #5D3B26;
            --text-dark: #3A3A3A;
            --shadow: rgba(139, 90, 60, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: linear-gradient(135deg, var(--warm-cream) 0%, var(--cream) 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        .nav {
            background: linear-gradient(90deg, var(--warm-green) 0%, var(--dark-green) 100%);
            padding: 1.2rem 0;
            box-shadow: 0 4px 20px var(--shadow);
            position: relative;
            overflow: hidden;
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
        }

        .nav::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><circle cx="10" cy="10" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="5" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="15" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="70" cy="8" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="12" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            opacity: 0.2;
            animation: navPattern 20s linear infinite;
        }

        @keyframes navPattern {
            0% { background-position: 0 0; }
            100% { background-position: 100px 100px; }
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .nav-brand {
            font-size: 2.2rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            letter-spacing: 1.5px;
            font-family: 'Georgia', 'Times New Roman', serif;
            position: relative;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-brand img {
            width: 40px;
            height: 40px;
            transition: transform 0.3s ease;
        }

        .nav-brand:hover img {
            transform: rotate(10deg);
        }

        .nav-brand::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-brand:hover::after {
            width: 80%;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.8rem;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: 0.5s;
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 240, 214, 0.9) 100%);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px var(--shadow);
            border: 2px solid rgba(232, 146, 74, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-orange) 0%, var(--warm-green) 100%);
        }

        .dashboard-title {
            font-size: 2.5rem;
            color: var(--brown);
            margin-bottom: 1.5rem;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-brown);
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid rgba(139, 90, 60, 0.2);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(232, 146, 74, 0.2);
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background: linear-gradient(135deg, var(--primary-orange) 0%, var(--secondary-orange) 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(232, 146, 74, 0.4);
            border: none;
            cursor: pointer;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232, 146, 74, 0.6);
            background: linear-gradient(135deg, var(--secondary-orange) 0%, var(--primary-orange) 100%);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .alert-error {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.2);
            color: #d32f2f;
        }

        .card p {
            color: var(--text-dark);
            text-align: center;
            margin-top: 1rem;
        }

        .card a {
            color: var(--primary-orange);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .card a:hover {
            color: var(--secondary-orange);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-content">
            <a href="index.php" class="nav-brand">
                <img src="assets/images/logo.svg" alt="Logo">
                <?php echo SITE_NAME; ?>
            </a>
            <div class="nav-links">
                <a href="register.php?type=<?php echo $type; ?>" class="nav-link">Cadastrar</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card" style="max-width: 500px; margin: 0 auto;">
            <h1 class="dashboard-title">Login</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="type" value="<?php echo $type; ?>">
                
                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Digite seu e-mail">
                </div>

                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required 
                           placeholder="Digite sua senha">
                </div>

                <button type="submit" class="btn" style="width: 100%;">Entrar</button>
            </form>

            <div style="margin-top: 20px; text-align: center;">
                <p>Não tem uma conta? 
                    <a href="register.php?type=<?php echo $type; ?>" 
                       style="color: var(--primary-color); text-decoration: none;">
                        Cadastre-se
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
