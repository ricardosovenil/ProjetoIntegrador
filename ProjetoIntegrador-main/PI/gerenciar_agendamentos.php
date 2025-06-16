<?php
session_start(); // Ensure session is started for $_SESSION access
require_once 'config.php';
require_once 'functions.php';
requireAuth();

if ($_SESSION['user_type'] !== 'tutor') {
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();

// Filtro por status para a exibição principal
$status_filtro = isset($_GET['status']) ? $_GET['status'] : 'todos';

// Processar ações de agendamento (Confirmar/Recusar ou Concluir/Cancelar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao_agendamento'])) {
        $agendamento_id = filter_input(INPUT_POST, 'agendamento_id', FILTER_VALIDATE_INT);
        $acao_agendamento = filter_input(INPUT_POST, 'acao_agendamento', FILTER_SANITIZE_STRING);

        if ($agendamento_id && $acao_agendamento) {
            $status = ($acao_agendamento === 'aceitar') ? 'agendado' : 'recusado';
            
            $stmt = $conn->prepare("UPDATE agendamentos SET status = ? WHERE id = ? AND tutor_id = ? AND status = 'pendente'");
            
            if ($stmt->execute([$status, $agendamento_id, $_SESSION['user_id']])) {
                setFlashMessage('success', 
                    $acao_agendamento === 'aceitar' ? 
                    'Agendamento confirmado com sucesso!' : 
                    'Agendamento recusado com sucesso!'
                );
            } else {
                setFlashMessage('error', 'Erro ao processar agendamento. Tente novamente.');
            }
        }
        header("Location: gerenciar_agendamentos.php?status=" . $status_filtro);
        exit();
    } 
    elseif (isset($_POST['acao'])) {
        $agendamento_id = filter_input(INPUT_POST, 'agendamento_id', FILTER_VALIDATE_INT);
        $acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_STRING);

        if ($agendamento_id && $acao) {
            $novo_status = $acao === 'concluir' ? 'concluido' : 'cancelado';
            
            $stmt = $conn->prepare("
                UPDATE agendamentos 
                SET status = ? 
                WHERE id = ? AND tutor_id = ? AND status = 'agendado'
            ");
            
            if ($stmt->execute([$novo_status, $agendamento_id, $_SESSION['user_id']])) {
                setFlashMessage('success', 
                    $acao === 'concluir' ? 
                    'Sessão marcada como concluída!' : 
                    'Sessão cancelada com sucesso!'
                );
            } else {
                setFlashMessage('error', 'Erro ao atualizar o status da sessão.');
            }
        }
        header("Location: gerenciar_agendamentos.php?status=" . $status_filtro);
        exit();
    }
}

// Buscar agendamentos (todos os status ou filtrado)
$sql_agendamentos = "
    SELECT a.*, e.nome as estudante_nome, e.curso as estudante_curso,
           COALESCE(av.nota, 0) as nota_avaliacao
    FROM agendamentos a
    JOIN estudantes e ON a.estudante_id = e.id
    LEFT JOIN avaliacoes av ON a.id = av.agendamento_id
    WHERE a.tutor_id = ?
";

if ($status_filtro !== 'todos') {
    $sql_agendamentos .= "AND a.status = ? ";
}

$sql_agendamentos .= "ORDER BY a.data DESC, a.horario_inicio DESC";

$stmt = $conn->prepare($sql_agendamentos);

if ($status_filtro !== 'todos') {
    $stmt->execute([$_SESSION['user_id'], $status_filtro]);
} else {
    $stmt->execute([$_SESSION['user_id']]);
}
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estatísticas (baseado em todos os agendamentos, sem filtro de exibição)
$stmt_stats = $conn->prepare("
    SELECT a.*, 
           COALESCE(av.nota, 0) as nota_avaliacao
    FROM agendamentos a
    LEFT JOIN avaliacoes av ON a.id = av.agendamento_id
    WHERE a.tutor_id = ?
");
$stmt_stats->execute([$_SESSION['user_id']]);
$all_agendamentos_for_stats = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

$total_agendamentos = count($all_agendamentos_for_stats);
$agendamentos_concluidos = 0;
$soma_notas = 0;
$total_avaliacoes = 0;

foreach ($all_agendamentos_for_stats as $agendamento) {
    if ($agendamento['status'] === 'concluido') {
        $agendamentos_concluidos++;
        if ($agendamento['nota_avaliacao'] > 0) {
            $soma_notas += $agendamento['nota_avaliacao'];
            $total_avaliacoes++;
        }
    }
}

$media_notas = $total_avaliacoes > 0 ? round($soma_notas / $total_avaliacoes, 1) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Agendamentos - <?php echo SITE_NAME; ?></title>
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
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(139, 90, 60, 0.1);
        }

        .dashboard-title {
            color: var(--dark-brown);
            font-size: 2.5rem;
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .grid {
            display: grid;
            gap: 2rem;
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .stat-card {
            background: var(--warm-cream);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px var(--shadow);
            border: 1px solid rgba(139, 90, 60, 0.1);
        }

        .stat-card h3 {
            color: var(--dark-brown);
            font-size: 1.2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .stat-number {
            color: var(--brown);
            font-size: 2.5rem;
            font-weight: 800;
        }

        .agendamento-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px var(--shadow);
            border: 1px solid rgba(139, 90, 60, 0.1);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .status-pendente-color { background: #ffd700; color: var(--dark-brown); }
        .status-agendado-color { background: var(--warm-green); color: white; }
        .status-concluido-color { background: var(--dark-green); color: white; }
        .status-cancelado-color, .status-recusado-color { background: var(--primary-orange); color: white; }

        .btn {
            background: var(--primary-orange);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .btn:hover {
            background: var(--secondary-orange);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: var(--warm-green);
        }

        .btn-secondary:hover {
            background: var(--dark-green);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .filtro-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }

        .filtro-link {
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            background: var(--warm-cream);
            color: var(--dark-brown);
            border: 1px solid rgba(139, 90, 60, 0.1);
        }

        .filtro-link:hover, .filtro-link.active {
            background: var(--warm-green);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .grid-3 {
                grid-template-columns: 1fr;
            }
            
            .nav-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .nav-links {
                flex-direction: column;
            }

            .filtro-container {
                flex-direction: column;
                align-items: center;
            }
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
            <h1 class="dashboard-title">Gerenciar Agendamentos</h1>

            <?php if (hasFlashMessage('success')): ?>
                <div class="alert alert-success">
                    <?php $msg = getFlashMessage(); echo htmlspecialchars($msg['message'] ?? ''); ?>
                </div>
            <?php endif; ?>

            <?php if (hasFlashMessage('error')): ?>
                <div class="alert alert-error">
                    <?php $msg = getFlashMessage(); echo htmlspecialchars($msg['message'] ?? ''); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-3">
                <div class="stat-card">
                    <h3>Total de Agendamentos</h3>
                    <div class="stat-number"><?php echo $total_agendamentos; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Sessões Concluídas</h3>
                    <div class="stat-number"><?php echo $agendamentos_concluidos; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Média de Avaliações</h3>
                    <div class="stat-number"><?php echo $media_notas; ?></div>
                </div>
            </div>

            <div class="filtro-container">
                <a href="?status=todos" class="filtro-link <?php echo $status_filtro === 'todos' ? 'active' : ''; ?>">
                    Todos
                </a>
                <a href="?status=pendente" class="filtro-link <?php echo $status_filtro === 'pendente' ? 'active' : ''; ?>">
                    Pendentes
                </a>
                <a href="?status=agendado" class="filtro-link <?php echo $status_filtro === 'agendado' ? 'active' : ''; ?>">
                    Agendados
                </a>
                <a href="?status=concluido" class="filtro-link <?php echo $status_filtro === 'concluido' ? 'active' : ''; ?>">
                    Concluídos
                </a>
                <a href="?status=cancelado" class="filtro-link <?php echo $status_filtro === 'cancelado' ? 'active' : ''; ?>">
                    Cancelados
                </a>
            </div>

            <?php if (empty($agendamentos)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i> Nenhuma sessão encontrada para o status selecionado.
                </div>
            <?php else: ?>
                <div class="agendamentos-list">
                    <?php foreach ($agendamentos as $agendamento): ?>
                        <div class="agendamento-card">
                            <div class="status-badge status-<?php echo $agendamento['status']; ?>-color">
                                <?php 
                                $status_labels = [
                                    'pendente' => 'Pendente',
                                    'agendado' => 'Agendado',
                                    'concluido' => 'Concluído',
                                    'cancelado' => 'Cancelado',
                                    'recusado' => 'Recusado'
                                ];
                                echo $status_labels[$agendamento['status']] ?? ucfirst($agendamento['status']);
                                ?>
                            </div>

                            <h3><?php echo htmlspecialchars($agendamento['assunto']); ?></h3>
                            
                            <div class="grid grid-2">
                                <div>
                                    <p><strong>Estudante:</strong> <?php echo htmlspecialchars($agendamento['estudante_nome']); ?></p>
                                    <p><strong>Curso:</strong> <?php echo htmlspecialchars($agendamento['estudante_curso']); ?></p>
                                    <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($agendamento['data'])); ?></p>
                                    <p><strong>Horário:</strong> 
                                        <?php echo date('H:i', strtotime($agendamento['horario_inicio'])); ?> - 
                                        <?php echo date('H:i', strtotime($agendamento['horario_termino'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <?php if ($agendamento['local']): ?>
                                        <p><strong>Local:</strong> <?php echo htmlspecialchars($agendamento['local']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($agendamento['link_videoconferencia']): ?>
                                        <p><strong>Link:</strong> <?php echo htmlspecialchars($agendamento['link_videoconferencia']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($agendamento['descricao']): ?>
                                        <p><strong>Descrição:</strong> <?php echo htmlspecialchars($agendamento['descricao']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <?php if ($agendamento['status'] === 'pendente'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="agendamento_id" value="<?php echo $agendamento['id']; ?>">
                                        <button type="submit" name="acao_agendamento" value="aceitar" class="btn">
                                            Aceitar
                                        </button>
                                        <button type="submit" name="acao_agendamento" value="recusar" class="btn btn-secondary">
                                            Recusar
                                        </button>
                                    </form>
                                <?php elseif ($agendamento['status'] === 'agendado'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="agendamento_id" value="<?php echo $agendamento['id']; ?>">
                                        <button type="submit" name="acao" value="concluir" class="btn">
                                            Concluir
                                        </button>
                                        <button type="submit" name="acao" value="cancelar" class="btn btn-secondary">
                                            Cancelar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 