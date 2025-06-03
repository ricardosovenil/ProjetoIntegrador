<?php
require_once 'config.php';
requireAuth();

if ($_SESSION['user_type'] !== 'student') {
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();
$agendamento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
$stmt->execute([$agendamento_id, $_SESSION['user_id']]);
$agendamento = $stmt->fetch();

if (!$agendamento) {
    header('Location: dashboard.php');
    exit;
}

// Processar a avaliação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nota = (int)$_POST['nota'];
    $comentario = sanitizeInput($_POST['comentario']);

    if ($nota >= 1 && $nota <= 5) {
        $stmt = $conn->prepare("
            INSERT INTO avaliacoes (
                agendamento_id, tutor_id, estudante_id, 
                nota, comentario, data_avaliacao
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        if ($stmt->execute([
            $agendamento_id, $agendamento['tutor_id'],
            $_SESSION['user_id'], $nota, $comentario
        ])) {
            setFlashMessage('success', 'Avaliação enviada com sucesso!');
            header('Location: dashboard.php');
            exit;
        } else {
            setFlashMessage('error', 'Erro ao enviar avaliação. Tente novamente.');
        }
    } else {
        setFlashMessage('error', 'A nota deve estar entre 1 e 5.');
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
            <a href="dashboard.php" class="nav-brand"><?php echo SITE_NAME; ?></a>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="logout.php" class="nav-link">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1 class="dashboard-title">Avaliar Sessão</h1>

            <div class="card" style="margin-bottom: 30px;">
                <h3>Informações da Sessão</h3>
                <p><strong>Tutor:</strong> <?php echo htmlspecialchars($agendamento['tutor_nome']); ?></p>
                <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($agendamento['data'])); ?></p>
                <p><strong>Horário:</strong> 
                    <?php echo date('H:i', strtotime($agendamento['hora_inicio'])); ?> - 
                    <?php echo date('H:i', strtotime($agendamento['hora_termino'])); ?>
                </p>
                <p><strong>Assunto:</strong> <?php echo htmlspecialchars($agendamento['assunto']); ?></p>
            </div>

            <?php if (hasFlashMessage('error')): ?>
                <div class="alert alert-error">
                    <?php echo getFlashMessage('error'); ?>
                </div>
            <?php endif; ?>

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