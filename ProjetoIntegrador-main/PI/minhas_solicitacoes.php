<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Verifica se o usuário está logado como estudante
if (!isset($_SESSION['estudante_id'])) {
    header('Location: login_estudante.php');
    exit();
}

$estudante_id = $_SESSION['estudante_id'];
$mensagem = '';

// Filtro por status
$status_filtro = isset($_GET['status']) ? $_GET['status'] : 'todos';

// Busca as solicitações do estudante
$sql = "SELECT s.*, t.nome as tutor_nome, t.email as tutor_email, t.telefone as tutor_telefone,
        (SELECT COUNT(*) FROM agendamentos a WHERE a.solicitacao_id = s.id) as tem_agendamento
        FROM solicitacoes_tutoria s
        JOIN tutores t ON s.tutor_id = t.id
        WHERE s.estudante_id = ? ";

if ($status_filtro !== 'todos') {
    $sql .= "AND s.status = ? ";
}

$sql .= "ORDER BY 
    CASE s.urgencia 
        WHEN 'alta' THEN 1 
        WHEN 'media' THEN 2 
        WHEN 'baixa' THEN 3 
    END,
    s.created_at DESC";

$stmt = $conn->prepare($sql);

if ($status_filtro !== 'todos') {
    $stmt->execute([$estudante_id, $status_filtro]);
} else {
    $stmt->execute([$estudante_id]);
}

$solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca agendamentos pendentes
$sql_agendamentos = "SELECT a.*, t.nome as tutor_nome, t.email as tutor_email, t.telefone as tutor_telefone,
                    s.descricao as solicitacao_descricao
                    FROM agendamentos a
                    JOIN solicitacoes_tutoria s ON a.solicitacao_id = s.id
                    JOIN tutores t ON s.tutor_id = t.id
                    WHERE s.estudante_id = ? AND a.status = 'pendente'
                    ORDER BY a.data ASC, a.horario_inicio ASC";

$stmt_agendamentos = $conn->prepare($sql_agendamentos);
$stmt_agendamentos->execute([$estudante_id]);
$agendamentos_pendentes = $stmt_agendamentos->fetchAll(PDO::FETCH_ASSOC);

// Processar cancelamento de solicitação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancelar_solicitacao'])) {
        $solicitacao_id = filter_input(INPUT_POST, 'solicitacao_id', FILTER_VALIDATE_INT);
        
        if ($solicitacao_id) {
            $sql = "UPDATE solicitacoes_tutoria SET status = 'cancelada' 
                    WHERE id = ? AND estudante_id = ? AND status = 'pendente'";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$solicitacao_id, $estudante_id])) {
                $mensagem = "Solicitação cancelada com sucesso!";
                header("Location: minhas_solicitacoes.php?status=" . $status_filtro);
                exit();
            } else {
                $mensagem = "Erro ao cancelar solicitação.";
            }
        }
    } elseif (isset($_POST['cancelar_agendamento'])) {
        $agendamento_id = filter_input(INPUT_POST, 'agendamento_id', FILTER_VALIDATE_INT);
        
        if ($agendamento_id) {
            $sql = "UPDATE agendamentos SET status = 'cancelado' 
                    WHERE id = ? AND solicitacao_id IN (SELECT id FROM solicitacoes_tutoria WHERE estudante_id = ?) 
                    AND status = 'pendente'";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$agendamento_id, $estudante_id])) {
                $mensagem = "Agendamento cancelado com sucesso!";
                header("Location: minhas_solicitacoes.php?status=" . $status_filtro);
                exit();
            } else {
                $mensagem = "Erro ao cancelar agendamento.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Minhas Solicitações e Agendamentos</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
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
        .section-title {
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class='container mt-5'>
        <div class='d-flex justify-content-between align-items-center mb-4'>
            <h2>Minhas Solicitações e Agendamentos</h2>
            <a href='solicitar_tutoria.php' class='btn btn-primary'>
                <i class='fas fa-plus'></i> Nova Solicitação
            </a>
        </div>

        <?php if ($mensagem): ?>
            <div class='alert alert-info'><?php echo $mensagem; ?></div>
        <?php endif; ?>

        <!-- Seção de Agendamentos Pendentes -->
        <h3 class='section-title'>Agendamentos Pendentes</h3>
        <?php if (empty($agendamentos_pendentes)): ?>
            <div class='alert alert-info'>
                <i class='fas fa-info-circle'></i> 
                Nenhum agendamento pendente no momento.
            </div>
        <?php else: ?>
            <div class='row'>
                <?php foreach ($agendamentos_pendentes as $agendamento): ?>
                    <div class='col-md-6 col-lg-4'>
                        <div class='card h-100'>
                            <div class='card-header d-flex justify-content-between align-items-center'>
                                <h5 class='card-title mb-0'>
                                    <?php echo htmlspecialchars($agendamento['tutor_nome']); ?>
                                </h5>
                                <span class='badge status-badge bg-warning'>Pendente</span>
                            </div>
                            <div class='card-body'>
                                <p class='card-text'>
                                    <strong>Data e Hora:</strong><br>
                                    <i class='fas fa-calendar'></i> 
                                    <?php echo date('d/m/Y H:i', strtotime($agendamento['data_hora'])); ?>
                                </p>
                                <p class='card-text'>
                                    <strong>Duração:</strong><br>
                                    <i class='fas fa-clock'></i> 
                                    <?php echo $agendamento['duracao']; ?> minutos
                                </p>
                                <p class='card-text'>
                                    <strong>Tutor:</strong><br>
                                    <?php echo htmlspecialchars($agendamento['tutor_nome']); ?>
                                </p>
                                <p class='card-text'>
                                    <strong>Contato do Tutor:</strong><br>
                                    <i class='fas fa-envelope'></i> <?php echo htmlspecialchars($agendamento['tutor_email']); ?><br>
                                    <i class='fas fa-phone'></i> <?php echo htmlspecialchars($agendamento['tutor_telefone']); ?>
                                </p>
                                <p class='card-text'>
                                    <strong>Descrição da Solicitação:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($agendamento['solicitacao_descricao'])); ?>
                                </p>
                            </div>
                            <div class='card-footer'>
                                <form method='POST' class='d-inline' onsubmit='return confirm("Tem certeza que deseja cancelar este agendamento?");'>
                                    <input type='hidden' name='agendamento_id' value='<?php echo $agendamento['id']; ?>'>
                                    <button type='submit' name='cancelar_agendamento' class='btn btn-danger btn-sm'>
                                        <i class='fas fa-times'></i> Cancelar Agendamento
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Seção de Solicitações -->
        <h3 class='section-title'>Solicitações de Tutoria</h3>
        <div class='filters'>
            <form method='GET' class='row g-3 align-items-center'>
                <div class='col-auto'>
                    <label for='status' class='form-label'>Filtrar por status:</label>
                    <select name='status' id='status' class='form-select' onchange='this.form.submit()'>
                        <option value='todos' <?php echo $status_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value='pendente' <?php echo $status_filtro === 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                        <option value='aceita' <?php echo $status_filtro === 'aceita' ? 'selected' : ''; ?>>Aceitas</option>
                        <option value='recusada' <?php echo $status_filtro === 'recusada' ? 'selected' : ''; ?>>Recusadas</option>
                        <option value='cancelada' <?php echo $status_filtro === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                    </select>
                </div>
            </form>
        </div>

        <?php if (empty($solicitacoes)): ?>
            <div class='alert alert-info'>
                <i class='fas fa-info-circle'></i> 
                Nenhuma solicitação encontrada.
                <?php if ($status_filtro !== 'todos'): ?>
                    <a href='?status=todos' class='alert-link'>Ver todas as solicitações</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class='row'>
                <?php foreach ($solicitacoes as $solicitacao): ?>
                    <div class='col-md-6 col-lg-4'>
                        <div class='card h-100'>
                            <div class='card-header d-flex justify-content-between align-items-center'>
                                <h5 class='card-title mb-0'>
                                    <?php echo htmlspecialchars($solicitacao['tutor_nome']); ?>
                                </h5>
                                <span class='badge status-badge bg-<?php
                                    echo $solicitacao['status'] === 'aceita' ? 'success' :
                                        ($solicitacao['status'] === 'recusada' ? 'danger' :
                                        ($solicitacao['status'] === 'cancelada' ? 'secondary' : 'warning'));
                                ?>'>
                                    <?php echo ucfirst($solicitacao['status']); ?>
                                </span>
                            </div>
                            <div class='card-body'>
                                <p class='card-text'>
                                    <strong>Descrição:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($solicitacao['descricao'])); ?>
                                </p>
                                <p class='card-text'>
                                    <strong>Urgência:</strong>
                                    <span class='badge bg-<?php
                                        echo $solicitacao['urgencia'] === 'alta' ? 'danger' :
                                            ($solicitacao['urgencia'] === 'media' ? 'warning' : 'info');
                                    ?>'>
                                        <?php echo ucfirst($solicitacao['urgencia']); ?>
                                    </span>
                                </p>
                                <p class='card-text'>
                                    <strong>Contato do Tutor:</strong><br>
                                    <i class='fas fa-envelope'></i> <?php echo htmlspecialchars($solicitacao['tutor_email']); ?><br>
                                    <i class='fas fa-phone'></i> <?php echo htmlspecialchars($solicitacao['tutor_telefone']); ?>
                                </p>
                                <p class='card-text'>
                                    <small class='text-muted'>
                                        <i class='fas fa-clock'></i> 
                                        Solicitado em: <?php echo date('d/m/Y H:i', strtotime($solicitacao['created_at'])); ?>
                                    </small>
                                </p>
                            </div>
                            <div class='card-footer'>
                                <?php if ($solicitacao['status'] === 'pendente'): ?>
                                    <form method='POST' class='d-inline' onsubmit='return confirm("Tem certeza que deseja cancelar esta solicitação?");'>
                                        <input type='hidden' name='solicitacao_id' value='<?php echo $solicitacao['id']; ?>'>
                                        <button type='submit' name='cancelar_solicitacao' class='btn btn-danger btn-sm'>
                                            <i class='fas fa-times'></i> Cancelar
                                        </button>
                                    </form>
                                <?php elseif ($solicitacao['status'] === 'aceita' && !$solicitacao['tem_agendamento']): ?>
                                    <a href='agendar.php?solicitacao_id=<?php echo $solicitacao['id']; ?>' class='btn btn-success btn-sm'>
                                        <i class='fas fa-calendar-plus'></i> Agendar Sessão
                                    </a>
                                <?php elseif ($solicitacao['tem_agendamento']): ?>
                                    <a href='historico_sessoes.php' class='btn btn-info btn-sm'>
                                        <i class='fas fa-calendar-check'></i> Ver Agendamento
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class='mt-4'>
            <a href='dashboard.php' class='btn btn-secondary'>
                <i class='fas fa-arrow-left'></i> Voltar ao Dashboard
            </a>
        </div>
    </div>

    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html> 