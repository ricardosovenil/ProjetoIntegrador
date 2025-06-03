<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST["nome"];
    $email = $_POST["email"];
    $curso = $_POST["curso"];
    $matricula = $_POST["matricula"];
    $senha = $_POST["senha"];
    $confirmar_senha = $_POST["confirmar_senha"];

    if ($senha !== $confirmar_senha) {
        echo "<p style='color:red;'>As senhas não coincidem.</p>";
        exit;
    }

    $conn = new mysqli("localhost", "root", "", "sistema_tutoria");
    if ($conn->connect_error) {
        die("Erro na conexão: " . $conn->connect_error);
    }

    $verifica = $conn->prepare("SELECT id FROM estudantes WHERE email = ? OR matricula = ?");
    $verifica->bind_param("ss", $email, $matricula);
    $verifica->execute();
    $verifica->store_result();

    if ($verifica->num_rows > 0) {
        echo "<p style='color:red;'>E-mail ou matrícula já cadastrados.</p>";
        exit;
    }

    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO estudantes (nome, email, curso, matricula, senha) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $nome, $email, $curso, $matricula, $senha_hash);

    if ($stmt->execute()) {
        echo "<h3>Cadastro realizado com sucesso!</h3>";
    } else {
        echo "<p>Erro: " . $stmt->error . "</p>";
    }

    $conn->close();
}
?>
