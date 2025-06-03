<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST["nome"];
    $email = $_POST["email"];
    $telefone = $_POST["telefone"];
    $areas = $_POST["areas"];
    $dia_semana = $_POST["dia_semana"];
    $horario_inicio = $_POST["horario_inicio"];
    $horario_termino = $_POST["horario_termino"];
    $senha = $_POST["senha"];
    $confirmar_senha = $_POST["confirmar_senha"];

    if ($senha !== $confirmar_senha) {
        echo "<p style='color:red; font-weight:bold;'>As senhas não coincidem. Tente novamente.</p>";
        echo '<button onclick="window.history.back()" style="
          margin-top: 20px;
          padding: 10px 20px;
          background-color: #e88e5a;
          border: none;
          border-radius: 8px;
          color: white;
          font-weight: bold;
          cursor: pointer;
          transition: background-color 0.3s ease;
        ">Voltar</button>';
        exit;
    }

    // Conectar ao banco
    $conn = new mysqli("localhost", "root", "", "sistema_tutoria");
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    // Verificar se email já existe
    $check_sql = "SELECT id FROM tutores WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        echo "<p style='color:red; font-weight:bold;'>Este email já está cadastrado. Use outro email.</p>";
        echo '<button onclick="window.history.back()" style="
          margin-top: 20px;
          padding: 10px 20px;
          background-color: #e88e5a;
          border: none;
          border-radius: 8px;
          color: white;
          font-weight: bold;
          cursor: pointer;
          transition: background-color 0.3s ease;
        ">Voltar</button>';
        $check_stmt->close();
        $conn->close();
        exit;
    }
    $check_stmt->close();

    // Hash da senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    // Inserir tutor
    $sql = "INSERT INTO tutores (nome, email, telefone, senha, dia_semana, horario_inicio, horario_termino)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $nome, $email, $telefone, $senha_hash, $dia_semana, $horario_inicio, $horario_termino);

    if ($stmt->execute()) {
        $tutor_id = $stmt->insert_id;

        // Inserir áreas
        foreach ($areas as $area_id) {
            $area_stmt = $conn->prepare("INSERT INTO areas_tutor (tutor_id, area_id) VALUES (?, ?)");
            $area_stmt->bind_param("ii", $tutor_id, $area_id);
            $area_stmt->execute();
            $area_stmt->close();
        }

        echo "<h2>Cadastro realizado com sucesso!</h2>";
        echo '<button onclick="window.history.back()" style="
          margin-top: 20px;
          padding: 10px 20px;
          background-color: #e88e5a;
          border: none;
          border-radius: 8px;
          color: white;
          font-weight: bold;
          cursor: pointer;
          transition: background-color 0.3s ease;
        ">Voltar</button>';
    } else {
        echo "<p style='color:red;'>Erro ao cadastrar: " . $stmt->error . "</p>";
        echo '<button onclick="window.history.back()" style="
          margin-top: 20px;
          padding: 10px 20px;
          background-color: #e88e5a;
          border: none;
          border-radius: 8px;
          color: white;
          font-weight: bold;
          cursor: pointer;
          transition: background-color 0.3s ease;
        ">Voltar</button>';
    }

    $stmt->close();
    $conn->close();
}
?>
