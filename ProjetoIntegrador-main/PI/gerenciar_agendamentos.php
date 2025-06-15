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
    <title>Gerenciar Agendamentos - Sistema de Tutoria Acadêmica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .nav-bar {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 10px 0;
        }
        .nav-bar .navbar-brand {
            font-weight: bold;
            color: #28a745;
        }
        .nav-bar .nav-link {
            color: #6c757d;
        }
        .nav-bar .nav-link:hover {
            color: #28a745;
        }
        .container.main-content {
            padding-top: 30px;
        }
        .dashboard-title {
            color: #28a745;
            margin-bottom: 30px;
            font-size: 2.2em;
            text-align: center;
        }
        .stat-card {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .stat-card h3 {
            font-size: 1.1em;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .stat-card .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #28a745;
        }
        .agendamento-card {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 20px;
        }
        .agendamento-card .details {
            flex: 1;
            min-width: 250px;
        }
        .agendamento-card .description {
            flex: 1;
            min-width: 250px;
        }
        .agendamento-card h3 {
            font-size: 1.4em;
            color: #343a40;
            margin-bottom: 15px;
            width: 100%;
        }
        .agendamento-card p {
            margin-bottom: 8px;
            color: #495057;
        }
        .agendamento-card strong {
            color: #343a40;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
        }
        .status-pendente-color { background-color: #ffc107; color: #343a40; } /* warning */
        .status-agendado-color { background-color: #007bff; color: #ffffff; } /* primary */
        .status-concluido-color { background-color: #28a745; color: #ffffff; } /* success */
        .status-cancelado-color, .status-recusado-color { background-color: #dc3545; color: #ffffff; } /* danger */
        
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            width: 100%;
            justify-content: flex-end;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
        }
    </style>
</head>
<body>
    <nav class="nav-bar navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Sistema de Tutoria Acadêmica</a>
            <div class="collapse navbar-collapse justify-content-end">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <h1 class="dashboard-title">Gerenciar Agendamentos</h1>

        <?php if (hasFlashMessage('success')): ?>
            <div class="alert alert-success">
                <?php echo getFlashMessage('success'); ?>
            </div>
        <?php endif; ?>

        <?php if (hasFlashMessage('error')): ?>
            <div class="alert alert-danger">
                <?php echo getFlashMessage('error'); ?>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h3>Total de Sessões</h3>
                    <p class="stat-number"><?php echo $total_agendamentos; ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3>Sessões Concluídas</h3>
                    <p class="stat-number"><?php echo $agendamentos_concluidos; ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3>Média de Avaliações</h3>
                    <p class="stat-number"><?php echo $media_notas; ?>/5</p>
                </div>
            </div>
        </div>

        <div class="filters mb-4 p-3 bg-light rounded">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="status_filtro" class="form-label">Filtrar por status:</label>
                    <select name="status" id="status_filtro" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?php echo $status_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="pendente" <?php echo $status_filtro === 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                        <option value="agendado" <?php echo $status_filtro === 'agendado' ? 'selected' : ''; ?>>Agendados</option>
                        <option value="concluido" <?php echo $status_filtro === 'concluido' ? 'selected' : ''; ?>>Concluídos</option>
                        <option value="recusado" <?php echo $status_filtro === 'recusado' ? 'selected' : ''; ?>>Recusados</option>
                        <option value="cancelado" <?php echo $status_filtro === 'cancelado' ? 'selected' : ''; ?>>Cancelados</option>
                    </select>
                </div>
            </form>
        </div>

        <?php if (empty($agendamentos)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> Nenhuma sessão encontrada para o status selecionado.
            </div>
        <?php else: ?>
            <div class="agendamentos-list">
                <?php foreach ($agendamentos as $agendamento): ?>
                    <div class="agendamento-card">
                        <div class="details">
                            <h3><?php echo htmlspecialchars($agendamento['assunto']); ?></h3>
                            <p><strong>Estudante:</strong> <?php echo htmlspecialchars($agendamento['estudante_nome']); ?></p>
                            <p><strong>Curso:</strong> <?php echo htmlspecialchars($agendamento['estudante_curso']); ?></p>
                            <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($agendamento['data'])); ?></p>
                            <p><strong>Horário:</strong> 
                                <?php echo date('H:i', strtotime($agendamento['horario_inicio'])); ?> - 
                                <?php echo date('H:i', strtotime($agendamento['horario_termino'])); ?>
                            </p>
                            <p><strong>Status:</strong> 
                                <span class="status-badge 
                                    <?php 
                                        echo ($agendamento['status'] === 'pendente') ? 'status-pendente-color' : '';
                                        echo ($agendamento['status'] === 'agendado') ? 'status-agendado-color' : '';
                                        echo ($agendamento['status'] === 'concluido') ? 'status-concluido-color' : '';
                                        echo ($agendamento['status'] === 'recusado' || $agendamento['status'] === 'cancelado') ? 'status-recusado-color' : '';
                                    ?>">
                                    <?php echo ucfirst($agendamento['status']); ?>
                                </span>
                            </p>
                            <?php if ($agendamento['nota_avaliacao'] > 0): ?>
                                <p><strong>Avaliação:</strong> 
                                    <?php echo $agendamento['nota_avaliacao']; ?>/5
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="description">
                            <p><strong>Descrição:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($agendamento['descricao'])); ?></p>
                            
                            <div class="action-buttons">
                                <?php if ($agendamento['status'] === 'pendente'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="agendamento_id" value="<?php echo $agendamento['id']; ?>">
                                        <button type="submit" name="acao_agendamento" value="aceitar" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Confirmar
                                        </button>
                                        <button type="submit" name="acao_agendamento" value="recusar" 
                                                class="btn btn-danger btn-sm" style="margin-left: 10px;">
                                            <i class="fas fa-times"></i> Recusar
                                        </button>
                                    </form>
                                <?php elseif ($agendamento['status'] === 'agendado'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="agendamento_id" value="<?php echo $agendamento['id']; ?>">
                                        <button type="submit" name="acao" value="concluir" class="btn btn-primary btn-sm">
                                            <i class="fas fa-check-double"></i> Concluir
                                        </button>
                                        <button type="submit" name="acao" value="cancelar" 
                                                class="btn btn-secondary btn-sm" style="margin-left: 10px;">
                                            <i class="fas fa-times-circle"></i> Cancelar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 