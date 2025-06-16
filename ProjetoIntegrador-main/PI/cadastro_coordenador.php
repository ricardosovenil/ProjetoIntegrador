<?php
require_once 'config.php';
require_once 'functions.php';

requireAuth(); // Apenas coordenadores logados podem cadastrar novos coordenadores

if ($_SESSION['user_type'] !== 'coordenador') {
    header('Location: dashboard.coordenador.php');
    exit;
}

$nome = '';
$email = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitizeInput($_POST['nome']);
    $email = sanitizeInput($_POST['email']);
    $senha = $_POST['senha'];
    $confirm_senha = $_POST['confirm_senha'];

    if (empty($nome) || empty($email) || empty($senha) || empty($confirm_senha)) {
        $error = "Por favor, preencha todos os campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Formato de e-mail inválido.";
    } elseif ($senha !== $confirm_senha) {
        $error = "As senhas não coincidem.";
    } elseif (strlen($senha) < 6) {
        $error = "A senha deve ter no mínimo 6 caracteres.";
    } else {
        $conn = getDBConnection();
        
        // Verificar se o e-mail já existe
        $stmt = $conn->prepare("SELECT COUNT(*) FROM coordenadores WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Este e-mail já está cadastrado para um coordenador.";
        } else {
            $hashed_password = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO coordenadores (nome, email, senha) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$nome, $email, $hashed_password])) {
                $success = "Coordenador cadastrado com sucesso!";
                $nome = ''; // Limpar campos após o sucesso
                $email = '';
            } else {
                $error = "Erro ao cadastrar coordenador. Tente novamente.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Coordenador - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-group button {
            padding: 0.8rem 1.5rem;
            background-color: var(--warm-green);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .form-group button:hover {
            background-color: var(--dark-green);
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            font-weight: bold;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-content">
            <a href="dashboard_coordenador.php" class="nav-brand"><?php echo SITE_NAME; ?></a>
            <div class="nav-links">
                <a href="dashboard_coordenador.php" class="nav-link">Dashboard</a>
                <a href="logout.php" class="nav-link">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1 class="dashboard-title">Cadastrar Novo Coordenador</h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="cadastro_coordenador.php">
                <div class="form-group">
                    <label for="nome">Nome:</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <div class="form-group">
                    <label for="confirm_senha">Confirmar Senha:</label>
                    <input type="password" id="confirm_senha" name="confirm_senha" required>
                </div>
                <div class="form-group" style="text-align: right;">
                    <button type="submit" class="btn">Cadastrar Coordenador</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 