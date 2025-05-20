<?php
session_start();

// Configurações do banco de dados
$db_host = 'localhost';
$db_name = 'sistema_tutoria';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        $erro = "Preencha e-mail e senha";
    } else {
        $stmt = $pdo->prepare("SELECT id, nome, senha FROM estudantes WHERE email = ?");
        $stmt->execute([$email]);
        $estudante = $stmt->fetch();

        if ($estudante && password_verify($senha, $estudante['senha'])) {
            $_SESSION['estudante_id'] = $estudante['id'];
            $_SESSION['estudante_nome'] = $estudante['nome'];
            header('Location: dashboard.php');
            exit;
        } else {
            $erro = "Credenciais inválidas";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Sistema de Tutoria</title>
<style>
body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: url('IMG_4199.PNG') no-repeat center center fixed;
    background-size: cover;
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

body::before {
    content: "";
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background-color: rgba(255, 248, 240, 0.65);
    backdrop-filter: blur(3px);
    z-index: 0;
}

.login-box {
    position: relative;
    z-index: 1;
    background: #fefaf3;
    padding: 40px 30px;
    border-radius: 20px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    border: 2px solid #8fbf9f;
}

h1 {
    text-align: center;
    color: #4a6fa5;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

label {
    display: block;
    margin-bottom: 5px;
    color: #333;
    font-weight: 600;
}

input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 10px;
    font-size: 1rem;
    background-color: #fffdf9;
}

input:focus {
    border-color: #e88e5a;
    box-shadow: 0 0 0 2px rgba(232, 142, 90, 0.3);
    outline: none;
}

button {
    width: 100%;
    padding: 12px;
    background-color: #e88e5a;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s ease;
}

button:hover {
    background-color: #c76f3a;
}

.error {
    color: red;
    text-align: center;
    margin-bottom: 15px;
    font-weight: bold;
}
</style>
</head>
<body>
<div class="login-box">
<h1>Login do Estudante</h1>

<?php if (isset($erro)): ?>
<div class="error"><?= $erro ?></div>
<?php endif; ?>

<form method="POST">
<div class="form-group">
<label for="email">E-mail:</label>
<input type="email" name="email" id="email" required>
</div>
<div class="form-group">
<label for="senha">Senha:</label>
<input type="password" name="senha" id="senha" required>
</div>
<button type="submit">Entrar</button>
</form>
</div>
</body>
</html>
