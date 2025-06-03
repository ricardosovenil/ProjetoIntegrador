<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $senha = $_POST["senha"];

    $conn = new mysqli("localhost", "root", "", "sistema_tutoria");
    if ($conn->connect_error) {
        die("Erro: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("SELECT id, senha FROM estudantes WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $senha_hash);
        $stmt->fetch();

        if (password_verify($senha, $senha_hash)) {
            $_SESSION["estudante_id"] = $id;
            echo "<h3>Login realizado com sucesso!</h3>";
        } else {
            echo "<p style='color:red;'>Senha incorreta.</p>";
        }
    } else {
        echo "<p style='color:red;'>Usuário não encontrado.</p>";
    }

    $conn->close();
}
?>
