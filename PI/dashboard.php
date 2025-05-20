<?php
session_start();

if (!isset($_SESSION['estudante_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
    background: url('IMG_4199.PNG') no-repeat center center fixed;
    background-size: cover;
    min-height: 100vh;
    position: relative;
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

.header {
    background: #4a6fa5;
    color: white;
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}

.header a {
    color: white;
    text-decoration: none;
    font-weight: bold;
}

.content {
    margin: 30px auto;
    padding: 30px;
    max-width: 700px;
    background: #fefaf3;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    border: 2px solid #8fbf9f;
    position: relative;
    z-index: 1;
}

h1 {
    margin: 0;
}

p {
    font-size: 1.2rem;
    color: #333;
}
</style>
</head>
<body>
<div class="header">
<h1>Bem-vindo, <?= htmlspecialchars($_SESSION['estudante_nome']) ?>!</h1>
<a href="logout.php">Sair</a>
</div>

<div class="content">
<p>Esta é a área do estudante. Aqui você poderá acessar informações e funcionalidades do sistema de tutoria.</p>
</div>
</body>
</html>
