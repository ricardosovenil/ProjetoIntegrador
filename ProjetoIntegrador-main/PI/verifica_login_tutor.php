<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $senha = $_POST["senha"];

    // Conexão com o banco
    $conn = new mysqli("localhost", "root", "", "sistema_tutoria");

    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    // Verificar se o e-mail existe
    $sql = "SELECT id, nome, senha FROM tutores WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $tutor = $resultado->fetch_assoc();

        // Verifica a senha
        if (password_verify($senha, $tutor["senha"])) {
            $_SESSION["tutor_id"] = $tutor["id"];
            $_SESSION["tutor_nome"] = $tutor["nome"];
            header("Location: dashboard_tutor.php");
            exit;
        }
    }

    // Se falhar
    header("Location: login_tutor.php?erro=1");
    exit;
}
?>
