<?php
require_once 'config.php';
require_once 'functions.php';
requireAuth();

// Log para debug
error_log("Iniciando avaliar.php");
error_log("User type: " . $_SESSION['user_type']);
error_log("User ID: " . $_SESSION['user_id']);

if ($_SESSION['user_type'] !== 'estudante') {
    error_log("Usuário não é estudante. Redirecionando...");
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();
$agendamento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

error_log("Agendamento ID: " . $agendamento_id);

// Buscar informações do agendamento
$stmt = $conn->prepare("
    SELECT a.*, t.nome as tutor_nome, t.id as tutor_id
    FROM agendamentos a
    JOIN tutores t ON a.tutor_id = t.id
    WHERE a.id = ? AND a.estudante_id = ? AND a.status = 'concluido'
    AND NOT EXISTS (
        SELECT 1 FROM avaliacoes av 
        WHERE av.agendamento_id = a.id
    )
");

error_log("Executando query para buscar agendamento");
$stmt->execute([$agendamento_id, $_SESSION['user_id']]);
$agendamento = $stmt->fetch();

error_log("Agendamento encontrado: " . ($agendamento ? "Sim" : "Não"));

if (!$agendamento) {
    error_log("Agendamento não encontrado ou já avaliado");
    setFlashMessage('error', 'Sessão não encontrada ou já avaliada.');
    header('Location: dashboard.php');
    exit;
}

// Processar a avaliação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Processando POST request");
    
    if (!isset($_POST['nota']) || !isset($_POST['comentario'])) {
        error_log("Campos obrigatórios não preenchidos");
        setFlashMessage('error', 'Por favor, preencha todos os campos.');
    } else {
        $nota = (int)$_POST['nota'];
        $comentario = sanitizeInput($_POST['comentario']);

        error_log("Nota: " . $nota);
        error_log("Comentário: " . $comentario);

        if ($nota >= 1 && $nota <= 5) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO avaliacoes (
                        agendamento_id, tutor_id, estudante_id, 
                        nota, comentario
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $agendamento_id,
                    $agendamento['tutor_id'],
                    $_SESSION['user_id'], 
                    $nota, 
                    $comentario
                ]);

                error_log("Resultado da inserção: " . ($result ? "Sucesso" : "Falha"));

                if ($result) {
                    // Atualizar o status do agendamento para 'concluido'
                    $stmt_update_status = $conn->prepare("UPDATE agendamentos SET status = 'concluido' WHERE id = ?");
                    $stmt_update_status->execute([$agendamento_id]);

                    setFlashMessage('success', 'Avaliação enviada com sucesso!');
                    header('Location: dashboard.php');
                    exit;
                } else {
                    error_log("Erro ao executar a query de inserção");
                    setFlashMessage('error', 'Erro ao enviar avaliação. Tente novamente.');
                }
            } catch (PDOException $e) {
                error_log("Erro PDO: " . $e->getMessage());
                setFlashMessage('error', 'Erro ao enviar avaliação. Tente novamente.');
            }
        } else {
            error_log("Nota inválida: " . $nota);
            setFlashMessage('error', 'A nota deve estar entre 1 e 5.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliar Sessão - <?php echo SITE_NAME; ?></title>
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

        .grid {
            display: grid;
            gap: 2rem;
            margin-top: 2rem;
        }

        .grid-1 {
            grid-template-columns: 1fr;
        }

        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .stat {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-orange);
            text-align: center;
            margin: 1rem 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
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
        }

        .btn:hover {
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

        .appointment-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 240, 214, 0.95) 100%);
        }

        .appointment-card h4 {
            color: var(--brown);
            font-size: 1.3rem;
            margin-bottom: 1rem;
            font-weight: bold;
        }

        .appointment-card p {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .appointment-card strong {
            color: var(--dark-brown);
        }

        @media (max-width: 768px) {
            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
        }

        .notification-item {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-item.read {
            background-color: #e9e9e9;
            color: #777;
        }
        .notification-item .message {
            flex-grow: 1;
        }
        .notification-item .timestamp {
            font-size: 0.8em;
            color: #999;
            margin-left: 15px;
        }
        .notification-item .mark-read-btn {
            background: none;
            border: none;
            color: var(--primary-orange);
            cursor: pointer;
            font-weight: bold;
            margin-left: 15px;
        }
        .notification-item .mark-read-btn:hover {
            text-decoration: underline;
        }

        /* New styles for student dashboard layout */
        .dashboard-section {
            margin-top: 2rem;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 240, 214, 0.9) 100%);
            border-radius: 20px;
            box-shadow: 0 8px 25px var(--shadow);
            border: 2px solid rgba(232, 146, 74, 0.2);
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            padding: 1.5rem;
            text-align: center;
            background: var(--warm-cream);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid rgba(232, 146, 74, 0.1);
        }

        .stat-card h3 {
            font-size: 1.2rem;
            color: var(--brown);
            margin-bottom: 0.8rem;
        }

        .stat-number-small {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-orange);
        }

        .collapsible-header {
            cursor: pointer;
            background: linear-gradient(90deg, var(--warm-green) 0%, var(--dark-green) 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: background 0.3s ease;
        }

        .collapsible-header:hover {
            background: linear-gradient(90deg, var(--dark-green) 0%, var(--warm-green) 100%);
        }

        .collapsible-header .arrow {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .collapsible-content {
            padding: 1rem 0;
        }
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            font-size: 30px;
            color: #ddd;
            padding: 5px;
        }
        .rating label:hover,
        .rating label:hover ~ label,
        .rating input:checked ~ label {
            color: #ffd700;
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-content">
            <a href="dashboard.php" class="nav-brand">
                <img src="assets/images/logo.svg" alt="Logo">
                <?php echo SITE_NAME; ?>
            </a>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="logout.php" class="nav-link">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1 class="dashboard-title">Avaliar Sessão</h1>

            <?php if (hasFlashMessage('error')): ?>
                <div class="alert alert-error">
                    <?php $msg = getFlashMessage(); echo htmlspecialchars($msg['message'] ?? ''); ?>
                </div>
            <?php endif; ?>

            <div class="card" style="margin-bottom: 30px;">
                <h3>Informações da Sessão</h3>
                <p><strong>Tutor:</strong> <?php echo htmlspecialchars($agendamento['tutor_nome']); ?></p>
                <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($agendamento['data'])); ?></p>
                <p><strong>Horário:</strong> 
                    <?php echo date('H:i', strtotime($agendamento['horario_inicio'])); ?> - 
                    <?php echo date('H:i', strtotime($agendamento['horario_termino'])); ?>
                </p>
                <p><strong>Assunto:</strong> <?php echo htmlspecialchars($agendamento['assunto']); ?></p>
            </div>

            <form method="POST" class="grid grid-2">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Avaliação:</label>
                    <div class="rating">
                        <input type="radio" name="nota" value="5" id="star5" required>
                        <label for="star5">★</label>
                        <input type="radio" name="nota" value="4" id="star4">
                        <label for="star4">★</label>
                        <input type="radio" name="nota" value="3" id="star3">
                        <label for="star3">★</label>
                        <input type="radio" name="nota" value="2" id="star2">
                        <label for="star2">★</label>
                        <input type="radio" name="nota" value="1" id="star1">
                        <label for="star1">★</label>
                    </div>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="comentario">Comentário:</label>
                    <textarea id="comentario" name="comentario" rows="4" required
                              placeholder="Conte-nos como foi sua experiência com a sessão..."></textarea>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <button type="submit" class="btn">Enviar Avaliação</button>
                    <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 10px;">
                        Voltar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 