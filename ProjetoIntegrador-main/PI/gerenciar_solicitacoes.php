<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'notifications.php';

// Verifica se o usuário está logado como tutor
if (!isset($_SESSION['tutor_id'])) {
    header('Location: login_tutor.php');
    exit();
}

$tutor_id = $_SESSION['tutor_id'];
$mensagem = '';

// Filtro por status
$status_filtro = isset($_GET['status']) ? $_GET['status'] : 'pendente';

// Processa a resposta da solicitação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $solicitacao_id = filter_input(INPUT_POST, 'solicitacao_id', FILTER_VALIDATE_INT);
    $acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_STRING);
    $justificativa = filter_input(INPUT_POST, 'justificativa', FILTER_SANITIZE_STRING);
    
    if ($solicitacao_id && $acao) {
        $status = ($acao === 'aceitar') ? 'aceita' : 'recusada';
        
        // Se for recusar, a justificativa é obrigatória
        if ($acao === 'recusar' && empty($justificativa)) {
            $mensagem = "Por favor, forneça uma justificativa para recusar a solicitação.";
        } else {
            $sql = "UPDATE solicitacoes_tutoria SET status = ?, justificativa = ? WHERE id = ? AND tutor_id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$status, $justificativa, $solicitacao_id, $tutor_id])) {
                // Notificar o estudante sobre a mudança de status
                notifyAppointmentStatus($solicitacao_id, $status);
                $mensagem = "Solicitação " . ($acao === 'aceitar' ? 'aceita' : 'recusada') . " com sucesso!";
                header("Location: gerenciar_solicitacoes.php?status=" . $status_filtro);
                exit();
            } else {
                $mensagem = "Erro ao processar solicitação. Tente novamente.";
            }
        }
    }
}

// Busca solicitações
$sql = "SELECT s.*, e.nome as estudante_nome, e.email as estudante_email, e.curso as estudante_curso,
        (SELECT COUNT(*) FROM agendamentos a WHERE a.solicitacao_id = s.id) as tem_agendamento
        FROM solicitacoes_tutoria s 
        JOIN estudantes e ON s.estudante_id = e.id 
        WHERE s.tutor_id = ? ";

if ($status_filtro !== 'todos') {
    $sql .= "AND s.status = ? ";
}

$sql .= "ORDER BY 
    CASE s.urgencia 
        WHEN 'alta' THEN 1 
        WHEN 'media' THEN 2 
        WHEN 'baixa' THEN 3 
    END,
    s.created_at ASC";

$stmt = $conn->prepare($sql);

if ($status_filtro !== 'todos') {
    $stmt->execute([$tutor_id, $status_filtro]);
} else {
    $stmt->execute([$tutor_id]);
}

$solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Solicitações de Tutoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.9em;
            padding: 8px 12px;
        }
        .filters {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .modal-content {
            border-radius: 15px;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-radius: 15px 15px 0 0;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Gerenciar Solicitações de Tutoria</h2>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-info"><?php echo $mensagem; ?></div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="status" class="form-label">Filtrar por status:</label>
                    <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?php echo $status_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="pendente" <?php echo $status_filtro === 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                        <option value="aceita" <?php echo $status_filtro === 'aceita' ? 'selected' : ''; ?>>Aceitas</option>
                        <option value="recusada" <?php echo $status_filtro === 'recusada' ? 'selected' : ''; ?>>Recusadas</option>
                    </select>
                </div>
            </form>
        </div>

        <?php if (empty($solicitacoes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Nenhuma solicitação encontrada.
                <?php if ($status_filtro !== 'todos'): ?>
                    <a href="?status=todos" class="alert-link">Ver todas as solicitações</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($solicitacoes as $solicitacao): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($solicitacao['estudante_nome']); ?>
                                </h5>
                                <span class="badge status-badge bg-<?php
                                    echo $solicitacao['status'] === 'aceita' ? 'success' :
                                        ($solicitacao['status'] === 'recusada' ? 'danger' : 'warning');
                                ?>">
                                    <?php echo ucfirst($solicitacao['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <strong>Estudante:</strong><br>
                                    <?php echo htmlspecialchars($solicitacao['estudante_nome']); ?><br>
                                    <small class="text-muted">
                                        Curso: <?php echo htmlspecialchars($solicitacao['estudante_curso']); ?>
                                    </small>
                                </p>
                                <p class="card-text">
                                    <strong>Contato:</strong><br>
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($solicitacao['estudante_email']); ?>
                                </p>
                                <p class="card-text">
                                    <strong>Descrição:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($solicitacao['descricao'])); ?>
                                </p>
                                <p class="card-text">
                                    <strong>Urgência:</strong>
                                    <span class="badge bg-<?php
                                        echo $solicitacao['urgencia'] === 'alta' ? 'danger' :
                                            ($solicitacao['urgencia'] === 'media' ? 'warning' : 'info');
                                    ?>">
                                        <?php echo ucfirst($solicitacao['urgencia']); ?>
                                    </span>
                                </p>
                                <?php if ($solicitacao['status'] === 'recusada' && !empty($solicitacao['justificativa'])): ?>
                                    <p class="card-text">
                                        <strong>Justificativa:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($solicitacao['justificativa'])); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> 
                                        Solicitado em: <?php echo date('d/m/Y H:i', strtotime($solicitacao['created_at'])); ?>
                                    </small>
                                </p>
                            </div>
                            <div class="card-footer">
                                <?php if ($solicitacao['status'] === 'pendente'): ?>
                                    <div class="action-buttons">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="solicitacao_id" value="<?php echo $solicitacao['id']; ?>">
                                            <button type="submit" name="acao" value="aceitar" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Aceitar
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#recusarModal<?php echo $solicitacao['id']; ?>">
                                            <i class="fas fa-times"></i> Recusar
                                        </button>
                                    </div>

                                    <!-- Modal de Recusa -->
                                    <div class="modal fade" id="recusarModal<?php echo $solicitacao['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Recusar Solicitação</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="solicitacao_id" value="<?php echo $solicitacao['id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="justificativa<?php echo $solicitacao['id']; ?>" class="form-label">
                                                                Justificativa:
                                                            </label>
                                                            <textarea class="form-control" 
                                                                      id="justificativa<?php echo $solicitacao['id']; ?>" 
                                                                      name="justificativa" 
                                                                      rows="4" 
                                                                      required
                                                                      placeholder="Por favor, forneça uma justificativa para recusar esta solicitação..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" name="acao" value="recusar" class="btn btn-danger">
                                                            Confirmar Recusa
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($solicitacao['status'] === 'aceita' && $solicitacao['tem_agendamento']): ?>
                                    <a href="historico_sessoes.php" class="btn btn-info btn-sm">
                                        <i class="fas fa-calendar-check"></i> Ver Agendamento
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="dashboard_tutor.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 