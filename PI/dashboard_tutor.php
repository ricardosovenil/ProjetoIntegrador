<?php
session_start();
if (!isset($_SESSION["tutor_id"])) {
    header("Location: login_tutor.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Dashboard do Tutor</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background: url('IMG_4199.PNG') no-repeat center center fixed;
      background-size: cover;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
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

    .container {
      display: flex;
      justify-content: center;
      align-items: flex-start;
      gap: 20px;
      position: relative;
      z-index: 1;
    }

    .anuncio {
      width: 150px;
      height: 100%;
      background-color: #fffdf9;
      border: 2px dashed #ccc;
      border-radius: 10px;
      text-align: center;
      padding: 20px 10px;
      font-size: 1rem;
      color: #777;
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    }

    .dashboard {
      background-color: #fefaf3;
      border: 2px solid #8fbf9f;
      border-radius: 20px;
      padding: 40px;
      width: 500px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    .dashboard h1 {
      font-size: 1.8rem;
      color: #4a6fa5;
      margin-bottom: 20px;
    }

    .dashboard p {
      font-size: 1.1rem;
      margin-bottom: 30px;
      color: #444;
    }

    .logout-btn {
      padding: 12px 20px;
      background-color: #e88e5a;
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .logout-btn:hover {
      background-color: #c76f3a;
    }

    @media (max-width: 768px) {
      .container {
        flex-direction: column;
        align-items: center;
      }

      .anuncio {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="anuncio">Anuncie Aqui</div>

    <div class="dashboard">
      <h1>Bem-vindo(a), <?php echo htmlspecialchars($_SESSION["tutor_nome"]); ?>!</h1>
      <p>Este é o seu painel de tutor. Aqui você poderá gerenciar suas atividades.</p>
      <form action="logout.php" method="post">
        <button class="logout-btn" type="submit">Sair</button>
      </form>
    </div>

    <div class="anuncio">Anuncie Aqui</div>
  </div>
</body>
</html>
