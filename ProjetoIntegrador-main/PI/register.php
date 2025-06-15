<?php
require_once 'config.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'student';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitizeInput($_POST['nome']);
    $email = sanitizeInput($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $userType = $_POST['type'];

    if ($senha !== $confirmar_senha) {
        $error = "As senhas não coincidem.";
    } else {
        $conn = getDBConnection();
        
        // Verificar se o email já existe
        $table = $userType === 'tutor' ? 'tutores' : 'estudantes';
        $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Este e-mail já está cadastrado.";
        } else {
            try {
                $conn->beginTransaction();

                if ($userType === 'tutor') {
                    $telefone = sanitizeInput($_POST['telefone']);
                    $dia_semana = sanitizeInput($_POST['dia_semana']);
                    $horario_inicio = sanitizeInput($_POST['horario_inicio']);
                    $horario_termino = sanitizeInput($_POST['horario_termino']);
                    $areas = $_POST['areas'];

                    $stmt = $conn->prepare("
                        INSERT INTO tutores (nome, email, telefone, senha, dia_semana, horario_inicio, horario_termino)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt->execute([$nome, $email, $telefone, $senha_hash, $dia_semana, $horario_inicio, $horario_termino]);
                    
                    $tutor_id = $conn->lastInsertId();
                    
                    // Inserir áreas do tutor
                    $stmt = $conn->prepare("INSERT INTO areas_tutor (tutor_id, area_id) VALUES (?, ?)");
                    foreach ($areas as $area_id) {
                        $stmt->execute([$tutor_id, $area_id]);
                    }
                } else {
                    $curso = sanitizeInput($_POST['curso']);
                    $matricula = sanitizeInput($_POST['matricula']);

                    $stmt = $conn->prepare("
                        INSERT INTO estudantes (nome, email, curso, matricula, senha)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt->execute([$nome, $email, $curso, $matricula, $senha_hash]);
                }

                $conn->commit();
                $success = "Cadastro realizado com sucesso!";
                
                // Redirecionar para login após 2 segundos
                header("refresh:2;url=login.php?type=" . $userType);
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Erro ao realizar cadastro. Por favor, tente novamente.";
            }
        }
    }
}

// Buscar áreas para o formulário de tutor
$areas = [];
if ($type === 'tutor') {
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT id, nome FROM areas ORDER BY nome");
    $areas = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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
        }

        .nav {
            background: linear-gradient(90deg, var(--warm-green) 0%, var(--dark-green) 100%);
            padding: 1.2rem 0;
            box-shadow: 0 4px 20px var(--shadow);
            position: relative;
            overflow: hidden;
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
        }

        .nav::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><circle cx="10" cy="10" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="5" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="15" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="70" cy="8" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="12" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            opacity: 0.2;
            animation: navPattern 20s linear infinite;
        }

        @keyframes navPattern {
            0% { background-position: 0 0; }
            100% { background-position: 100px 100px; }
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .nav-brand {
            font-size: 2.2rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            letter-spacing: 1.5px;
            font-family: 'Georgia', 'Times New Roman', serif;
            position: relative;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-brand img {
            width: 40px;
            height: 40px;
            transition: transform 0.3s ease;
        }

        .nav-brand:hover img {
            transform: rotate(10deg);
        }

        .nav-brand::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-brand:hover::after {
            width: 80%;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
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
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: 0.5s;
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 240, 214, 0.9) 100%);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px var(--shadow);
            border: 2px solid rgba(232, 146, 74, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-orange) 0%, var(--warm-green) 100%);
        }

        .dashboard-title {
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

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-brown);
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid rgba(139, 90, 60, 0.2);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(232, 146, 74, 0.2);
        }

        .form-group select[multiple] {
            height: 120px;
        }

        .btn {
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
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232, 146, 74, 0.6);
            background: linear-gradient(135deg, var(--secondary-orange) 0%, var(--primary-orange) 100%);
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
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.2);
            color: #2e7d32;
        }

        .card p {
            color: var(--text-dark);
            text-align: center;
            margin-top: 1rem;
        }

        .card a {
            color: var(--primary-orange);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .card a:hover {
            color: var(--secondary-orange);
            text-decoration: underline;
        }

        .time-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .time-inputs span {
            color: var(--dark-brown);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-content">
            <a href="index.php" class="nav-brand">
                <img src="assets/images/logo.svg" alt="Logo">
                <?php echo SITE_NAME; ?>
            </a>
            <div class="nav-links">
                <a href="login.php?type=<?php echo $type; ?>" class="nav-link">Entrar</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h1 class="dashboard-title">
                Cadastro de <?php echo $type === 'tutor' ? 'Tutor' : 'Estudante'; ?>
            </h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="type" value="<?php echo $type; ?>">

                <div class="form-group">
                    <label for="nome">Nome Completo:</label>
                    <input type="text" id="nome" name="nome" required 
                           placeholder="Digite seu nome completo">
                </div>

                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="exemplo@email.com">
                </div>

                <?php if ($type === 'tutor'): ?>
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="text" id="telefone" name="telefone" required 
                               placeholder="(XX) XXXXX-XXXX">
                    </div>

                    <div class="form-group">
                        <label for="areas">Área(s) de atuação:</label>
                        <select name="areas[]" id="areas" multiple required>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['id']; ?>">
                                    <?php echo $area['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="dia_semana">Dia da Semana:</label>
                        <select name="dia_semana" id="dia_semana" required>
                            <option value="">Selecione um dia</option>
                            <option value="Segunda">Segunda-feira</option>
                            <option value="Terca">Terça-feira</option>
                            <option value="Quarta">Quarta-feira</option>
                            <option value="Quinta">Quinta-feira</option>
                            <option value="Sexta">Sexta-feira</option>
                            <option value="Sabado">Sábado</option>
                            <option value="Domingo">Domingo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Horário:</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="time" name="horario_inicio" required>
                            <span>até</span>
                            <input type="time" name="horario_termino" required>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="curso">Curso:</label>
                        <input type="text" id="curso" name="curso" required 
                               placeholder="Digite seu curso">
                    </div>

                    <div class="form-group">
                        <label for="matricula">Matrícula:</label>
                        <input type="text" id="matricula" name="matricula" required 
                               placeholder="Digite sua matrícula">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required 
                           placeholder="Digite uma senha">
                </div>

                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Senha:</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required 
                           placeholder="Confirme a senha">
                </div>

                <button type="submit" class="btn" style="width: 100%;">Cadastrar</button>
            </form>

            <div style="margin-top: 20px; text-align: center;">
                <p>Já tem uma conta? 
                    <a href="login.php?type=<?php echo $type; ?>" 
                       style="color: var(--primary-color); text-decoration: none;">
                        Faça login
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html> 