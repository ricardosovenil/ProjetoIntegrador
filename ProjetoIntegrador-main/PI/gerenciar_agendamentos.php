<?php
require_once 'config.php';
require_once 'functions.php';
requireAuth();

if ($_SESSION['user_type'] !== 'tutor') {
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agendamento_id = (int)$_POST['agendamento_id'];
    $acao = $_POST['acao'];

    if ($acao === 'concluir' || $acao === 'cancelar') {
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
}

// Buscar agendamentos
$stmt = $conn->prepare("
    SELECT a.*, e.nome as estudante_nome, e.curso as estudante_curso,
           COALESCE(av.nota, 0) as nota_avaliacao
    FROM agendamentos a
    JOIN estudantes e ON a.estudante_id = e.id
    LEFT JOIN avaliacoes av ON a.id = av.agendamento_id
    WHERE a.tutor_id = ?
    ORDER BY a.data DESC, a.horario_inicio DESC
");
$stmt->execute([$_SESSION['user_id']]);
$agendamentos = $stmt->fetchAll();

// Calcular estatísticas
$total_agendamentos = count($agendamentos);
$agendamentos_concluidos = 0;
$agendamentos_cancelados = 0;
$soma_notas = 0;
$total_avaliacoes = 0;

foreach ($agendamentos as $agendamento) {
    if ($agendamento['status'] === 'concluido') {
        $agendamentos_concluidos++;
        if ($agendamento['nota_avaliacao'] > 0) {
            $soma_notas += $agendamento['nota_avaliacao'];
            $total_avaliacoes++;
        }
    } elseif ($agendamento['status'] === 'cancelado') {
        $agendamentos_cancelados++;
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
                    <?php echo getFlashMessage('success'); ?>
                </div>
            <?php endif; ?>

            <?php if (hasFlashMessage('error')): ?>
                <div class="alert alert-error">
                    <?php echo getFlashMessage('error'); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-3" style="margin-bottom: 30px;">
                <div class="card">
                    <h3>Total de Sessões</h3>
                    <p class="stat"><?php echo $total_agendamentos; ?></p>
                </div>
                <div class="card">
                    <h3>Sessões Concluídas</h3>
                    <p class="stat"><?php echo $agendamentos_concluidos; ?></p>
                </div>
                <div class="card">
                    <h3>Média de Avaliações</h3>
                    <p class="stat"><?php echo $media_notas; ?>/5</p>
                </div>
            </div>

            <?php if (empty($agendamentos)): ?>
                <p>Você ainda não possui agendamentos.</p>
            <?php else: ?>
                <div class="grid grid-1">
                    <?php foreach ($agendamentos as $agendamento): ?>
                        <div class="card">
                            <div class="grid grid-2">
                                <div>
                                    <h3><?php echo htmlspecialchars($agendamento['assunto']); ?></h3>
                                    <p><strong>Estudante:</strong> <?php echo htmlspecialchars($agendamento['estudante_nome']); ?></p>
                                    <p><strong>Curso:</strong> <?php echo htmlspecialchars($agendamento['estudante_curso']); ?></p>
                                    <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($agendamento['data'])); ?></p>
                                    <p><strong>Horário:</strong> 
                                        <?php echo date('H:i', strtotime($agendamento['horario_inicio'])); ?> - 
                                        <?php echo date('H:i', strtotime($agendamento['horario_termino'])); ?>
                                    </p>
                                    <p><strong>Status:</strong> 
                                        <span class="status-<?php echo $agendamento['status']; ?>">
                                            <?php echo ucfirst($agendamento['status']); ?>
                                        </span>
                                    </p>
                                    <?php if ($agendamento['nota_avaliacao'] > 0): ?>
                                        <p><strong>Avaliação:</strong> 
                                            <?php echo $agendamento['nota_avaliacao']; ?>/5
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <p><strong>Descrição:</strong></p>
                                    <p><?php echo nl2br(htmlspecialchars($agendamento['descricao'])); ?></p>
                                    
                                    <?php if ($agendamento['status'] === 'agendado'): ?>
                                        <form method="POST" style="margin-top: 15px;">
                                            <input type="hidden" name="agendamento_id" value="<?php echo $agendamento['id']; ?>">
                                            <button type="submit" name="acao" value="concluir" class="btn">
                                                Marcar como Concluído
                                            </button>
                                            <button type="submit" name="acao" value="cancelar" 
                                                    class="btn btn-secondary" style="margin-left: 10px;">
                                                Cancelar Sessão
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
    </div>
</body>
</html> 