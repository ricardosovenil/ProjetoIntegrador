<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login de Tutor</title>
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

    form {
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

    h2 {
      text-align: center;
      color: #4a6fa5;
      margin-bottom: 25px;
    }

    label {
      display: block;
      margin-top: 15px;
      font-weight: 600;
      color: #333;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 12px;
      margin-top: 6px;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 1rem;
      background-color: #fffdf9;
      transition: border 0.3s ease, box-shadow 0.3s ease;
    }

    input:focus {
      border-color: #e88e5a;
      box-shadow: 0 0 0 2px rgba(232, 142, 90, 0.3);
      outline: none;
    }

    button {
      margin-top: 30px;
      width: 100%;
      padding: 14px;
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

    .mensagem {
      margin-top: 15px;
      color: red;
      text-align: center;
    }
  </style>
</head>
<body>
  <form action="verifica_login_tutor.php" method="POST">
    <h2>Login de Tutor</h2>

    <label for="email">E-mail:</label>
    <input type="email" id="email" name="email" required placeholder="Digite seu e-mail">

    <label for="senha">Senha:</label>
    <input type="password" id="senha" name="senha" required placeholder="Digite sua senha">

    <button type="submit">Entrar</button>

    <?php
      if (isset($_GET["erro"])) {
        echo "<div class='mensagem'>E-mail ou senha inválidos!</div>";
      }
    ?>
  </form>
</body>
</html>
