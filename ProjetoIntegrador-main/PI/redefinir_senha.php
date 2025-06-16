<?php
require_once 'config.php';
require_once 'functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $user_type = sanitizeInput($_POST['user_type']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $identifier = isset($_POST['identifier']) ? sanitizeInput($_POST['identifier']) : '';

    $conn = getDBConnection();
    if (!$conn) {
        $error = "Erro ao conectar com o banco de dados.";
    } else {
        if ($new_password !== $confirm_password) {
            $error = "As novas senhas não coincidem.";
        } else if (empty($new_password)) {
            $error = "A nova senha não pode ser vazia.";
        } else {
            $table = '';
            $identifier_column = '';
            switch ($user_type) {
                case 'estudante':
                    $table = 'estudantes';
                    $identifier_column = 'matricula';
                    break;
                case 'tutor':
                    $table = 'tutores';
                    $identifier_column = 'telefone';
                    break;
                case 'coordenador':
                    $table = 'coordenadores';
                    break;
                default:
                    $error = "Tipo de usuário inválido.";
                    break;
            }

            if (empty($error)) {
                $sql = "SELECT id FROM $table WHERE email = ?";
                $params = [$email];

                if ($identifier_column && !empty($identifier)) {
                    $sql .= " AND $identifier_column = ?";
                    $params[] = $identifier;
                } else if ($user_type !== 'coordenador') {
                    $error = "Por favor, informe a " . ($user_type === 'estudante' ? 'matrícula' : 'telefone') . ".";
                }

                if (empty($error)) {
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);

                    if ($stmt->rowCount() > 0) {
                        $user_id = $stmt->fetchColumn();
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                        $update_stmt = $conn->prepare("UPDATE $table SET senha = ? WHERE id = ?");
                        if ($update_stmt->execute([$hashed_password, $user_id])) {
                            $success = "Senha redefinida com sucesso! Você pode fazer login agora.";
                        } else {
                            $error = "Erro ao redefinir a senha. Tente novamente.";
                        }
                    } else {
                        $error = "Dados de usuário não encontrados ou incorretos.";
                    }
                }
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
    <title>Redefinir Senha - <?php echo SITE_NAME; ?></title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .nav {
            background: linear-gradient(90deg, var(--warm-green) 0%, var(--dark-green) 100%);
            padding: 1.2rem 0;
            box-shadow: 0 4px 20px var(--shadow);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .nav-brand {
            font-size: 2.2rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            letter-spacing: 1.5px;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
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
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 240, 214, 0.9) 100%);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px var(--shadow);
            border: 2px solid rgba(232, 146, 74, 0.2);
            width: 100%;
            max-width: 500px;
            margin-top: 80px;
        }

        h1 {
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

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-brown);
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid rgba(139, 90, 60, 0.2);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            font-family: 'Georgia', 'Times New Roman', serif;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(232, 146, 74, 0.2);
        }

        button {
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
            margin-bottom: 1rem;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232, 146, 74, 0.6);
            background: linear-gradient(135deg, var(--secondary-orange) 0%, var(--primary-orange) 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--warm-green) 0%, var(--dark-green) 100%);
            box-shadow: 0 4px 15px rgba(139, 165, 114, 0.4);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--warm-green) 100%);
            box-shadow: 0 6px 20px rgba(139, 165, 114, 0.6);
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

        .alert-success {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(0, 255, 0, 0.2);
            color: #2e7d32;
        }

        a {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-content">
            <a href="index.php" class="nav-brand">
                <?php echo SITE_NAME; ?>
            </a>
            <div class="nav-links">
                <a href="login.php" class="nav-link">Login</a>
                <a href="register.php" class="nav-link">Cadastro</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Redefinir Senha</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="user_type">Eu sou:</label>
                <select name="user_type" id="user_type" required onchange="toggleIdentifierField()">
                    <option value="">Selecione o tipo de usuário</option>
                    <option value="estudante" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'estudante') ? 'selected' : ''; ?>>Estudante</option>
                    <option value="tutor" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'tutor') ? 'selected' : ''; ?>>Tutor</option>
                    <option value="coordenador" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'coordenador') ? 'selected' : ''; ?>>Coordenador</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group" id="identifier_field" style="display: none;">
                <label for="identifier" id="identifier_label"></label>
                <input type="text" id="identifier" name="identifier" value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="new_password">Nova Senha:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Nova Senha:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit">Redefinir Senha</button>
            <a href="login.php"><button type="button" class="btn-secondary">Voltar ao Login</button></a>
        </form>
    </div>

    <script>
        function toggleIdentifierField() {
            var userType = document.getElementById('user_type').value;
            var identifierField = document.getElementById('identifier_field');
            var identifierLabel = document.getElementById('identifier_label');
            var identifierInput = document.getElementById('identifier');

            if (userType === 'estudante') {
                identifierLabel.textContent = 'Matrícula:';
                identifierInput.placeholder = 'Digite sua matrícula';
                identifierField.style.display = 'block';
                identifierInput.setAttribute('required', 'required');
            } else if (userType === 'tutor') {
                identifierLabel.textContent = 'Telefone:';
                identifierInput.placeholder = 'Digite seu telefone';
                identifierField.style.display = 'block';
                identifierInput.setAttribute('required', 'required');
            } else {
                identifierField.style.display = 'none';
                identifierInput.removeAttribute('required');
                identifierInput.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', toggleIdentifierField);
    </script>
</body>
</html> 