<?php
require_once 'config.php';
require_once 'functions.php'; // Certifique-se de que functions.php inclui requireAuth()

requireAuth(); // Garante que apenas usuários logados acessem

// Verifica se o usuário logado é um coordenador
if ($_SESSION['user_type'] !== 'coordenador') {
    // Redireciona para o dashboard padrão se não for coordenador
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();
$user_name = $_SESSION['user_name'];

// Lógica para buscar dados de relatórios - RF10

// 1. Relatório de Sessões Realizadas
$stmt_sessoes_total = $conn->prepare("SELECT COUNT(*) as total_sessoes_concluidas FROM agendamentos WHERE status = 'concluido'");
$stmt_sessoes_total->execute();
$total_sessoes_concluidas = $stmt_sessoes_total->fetchColumn();

// 2. Desempenho dos Tutores (Média de avaliações e número de sessões avaliadas)
$stmt_desempenho_tutores = $conn->prepare("
    SELECT 
        t.nome AS tutor_nome,
        AVG(av.nota) AS media_avaliacao,
        COUNT(DISTINCT av.agendamento_id) AS total_sessoes_avaliadas
    FROM tutores t
    LEFT JOIN agendamentos ag ON t.id = ag.tutor_id
    LEFT JOIN avaliacoes av ON ag.id = av.agendamento_id
    WHERE av.nota IS NOT NULL OR ag.status = 'concluido'
    GROUP BY t.id
    ORDER BY media_avaliacao DESC, total_sessoes_avaliadas DESC
");
$stmt_desempenho_tutores->execute();
$desempenho_tutores = $stmt_desempenho_tutores->fetchAll();

// Lógica para RF11 - Visualizar e Filtrar Sessões
$tutores_list = $conn->query("SELECT id, nome FROM tutores ORDER BY nome")->fetchAll();
$estudantes_list = $conn->query("SELECT id, nome FROM estudantes ORDER BY nome")->fetchAll();

$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$filter_tutor_id = isset($_GET['tutor_id']) ? (int)$_GET['tutor_id'] : 0;
$filter_estudante_id = isset($_GET['estudante_id']) ? (int)$_GET['estudante_id'] : 0;

$sql_filtered_sessions = "
    SELECT 
        ag.id, ag.data, ag.horario_inicio, ag.horario_termino, ag.assunto, ag.status,
        t.nome AS tutor_nome, e.nome AS estudante_nome
    FROM agendamentos ag
    JOIN tutores t ON ag.tutor_id = t.id
    JOIN estudantes e ON ag.estudante_id = e.id
    WHERE 1=1
";
$params_filtered_sessions = [];

if (!empty($filter_start_date)) {
    $sql_filtered_sessions .= " AND ag.data >= ?";
    $params_filtered_sessions[] = $filter_start_date;
}
if (!empty($filter_end_date)) {
    $sql_filtered_sessions .= " AND ag.data <= ?";
    $params_filtered_sessions[] = $filter_end_date;
}
if ($filter_tutor_id > 0) {
    $sql_filtered_sessions .= " AND ag.tutor_id = ?";
    $params_filtered_sessions[] = $filter_tutor_id;
}
if ($filter_estudante_id > 0) {
    $sql_filtered_sessions .= " AND ag.estudante_id = ?";
    $params_filtered_sessions[] = $filter_estudante_id;
}

$sql_filtered_sessions .= " ORDER BY ag.data DESC, ag.horario_inicio DESC";

$stmt_filtered_sessions = $conn->prepare($sql_filtered_sessions);
$stmt_filtered_sessions->execute($params_filtered_sessions);
$filtered_sessions = $stmt_filtered_sessions->fetchAll();

// Lógica para RF12 - Edição de Dados dos Usuários
$user_to_edit = null;
$edit_error = '';
$edit_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'search_user') {
        $search_type = $_POST['search_type'];
        $search_query = sanitizeInput($_POST['search_query']);

        if (!empty($search_query)) {
            $table = '';
            if ($search_type === 'tutor') {
                $table = 'tutores';
            } elseif ($search_type === 'estudante') {
                $table = 'estudantes';
            }

            if (!empty($table)) {
                $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ? OR email = ?");
                $stmt->execute([$search_query, $search_query]);
                $found_user = $stmt->fetch();

                if ($found_user) {
                    $user_to_edit = $found_user;
                    $user_to_edit['user_type'] = $search_type; // Adiciona o tipo de usuário para referência
                } else {
                    $edit_error = "Usuário não encontrado.";
                }
            } else {
                $edit_error = "Tipo de pesquisa inválido.";
            }
        } else {
            $edit_error = "Por favor, insira um ID ou e-mail para pesquisar.";
        }
    } elseif ($_POST['action'] === 'update_user' && isset($_POST['user_id']) && isset($_POST['user_type'])) {
        $update_id = (int)$_POST['user_id'];
        $update_type = $_POST['user_type'];
        $update_nome = sanitizeInput($_POST['nome']);
        $update_email = sanitizeInput($_POST['email']);
        $update_telefone = isset($_POST['telefone']) ? sanitizeInput($_POST['telefone']) : null; // Apenas para tutores
        $update_curso = isset($_POST['curso']) ? sanitizeInput($_POST['curso']) : null; // Apenas para estudantes
        $update_matricula = isset($_POST['matricula']) ? sanitizeInput($_POST['matricula']) : null; // Apenas para estudantes

        $table = '';
        $sql_update = '';
        $params_update = [];

        if ($update_type === 'tutor') {
            $table = 'tutores';
            $sql_update = "UPDATE $table SET nome = ?, email = ?, telefone = ? WHERE id = ?";
            $params_update = [$update_nome, $update_email, $update_telefone, $update_id];
        } elseif ($update_type === 'estudante') {
            $table = 'estudantes';
            $sql_update = "UPDATE $table SET nome = ?, email = ?, curso = ?, matricula = ? WHERE id = ?";
            $params_update = [$update_nome, $update_email, $update_curso, $update_matricula, $update_id];
        }

        if (!empty($table) && !empty($sql_update)) {
            try {
                $stmt_update = $conn->prepare($sql_update);
                if ($stmt_update->execute($params_update)) {
                    $edit_success = "Dados do usuário atualizados com sucesso!";
                    // Limpar $user_to_edit para que o formulário de edição suma após o sucesso
                    $user_to_edit = null;
                } else {
                    $edit_error = "Erro ao atualizar dados do usuário. Tente novamente.";
                }
            } catch (PDOException $e) {
                $edit_error = "Erro de banco de dados ao atualizar: " . $e->getMessage();
            }
        } else {
            $edit_error = "Tipo de usuário ou dados para atualização inválidos.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Coordenador - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Adicione estilos específicos para o dashboard do coordenador aqui, se necessário */
        .report-table th, .report-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .report-table th {
            background-color: #f2f2f2;
        }
        .filter-form .form-group {
            margin-bottom: 1rem;
        }
        .filter-form .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .filter-form input[type="date"], .filter-form select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .filter-form button {
            padding: 0.8rem 1.5rem;
            background-color: var(--primary-orange);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .filter-form button:hover {
            background-color: var(--secondary-orange);
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-content">
            <a href="dashboard_coordenador.php" class="nav-brand"><?php echo SITE_NAME; ?></a>
            <div class="nav-links">
                <a href="cadastro_coordenador.php" class="nav-link">Cadastrar Coordenador</a>
                <a href="logout.php" class="nav-link">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1 class="dashboard-title">Bem-vindo(a), Coordenador(a) <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p class="hero-description">Aqui você pode visualizar os relatórios e gerenciar o sistema.</p>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <h3>Relatório de Sessões Realizadas</h3>
                <p><strong>Total de sessões concluídas:</strong> <?php echo $total_sessoes_concluidas; ?></p>
                <!-- Conteúdo do relatório de sessões será adicionado aqui -->
            </div>

            <div class="card">
                <h3>Desempenho dos Tutores</h3>
                <?php if (!empty($desempenho_tutores)): ?>
                    <table class="report-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Tutor</th>
                                <th>Média Avaliações</th>
                                <th>Sessões Avaliadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($desempenho_tutores as $tutor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tutor['tutor_nome']); ?></td>
                                    <td><?php echo number_format($tutor['media_avaliacao'], 2); ?></td>
                                    <td><?php echo $tutor['total_sessoes_avaliadas']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nenhum dado de desempenho de tutores disponível ainda.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Seção para RF11 - Filtragem de Sessões -->
        <div class="card" style="margin-top: 2rem;">
            <h3>Visualizar e Filtrar Sessões (RF11)</h3>
            <form method="GET" class="filter-form grid grid-2">
                <div class="form-group">
                    <label for="start_date">Data Início:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">Data Fim:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                </div>
                <div class="form-group">
                    <label for="tutor_id">Tutor:</label>
                    <select id="tutor_id" name="tutor_id">
                        <option value="0">Todos</option>
                        <?php foreach ($tutores_list as $tutor): ?>
                            <option value="<?php echo $tutor['id']; ?>" <?php echo ($filter_tutor_id == $tutor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tutor['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="estudante_id">Estudante:</label>
                    <select id="estudante_id" name="estudante_id">
                        <option value="0">Todos</option>
                        <?php foreach ($estudantes_list as $estudante): ?>
                            <option value="<?php echo $estudante['id']; ?>" <?php echo ($filter_estudante_id == $estudante['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($estudante['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1; text-align: right;">
                    <button type="submit" class="btn">Filtrar Sessões</button>
                </div>
            </form>

            <?php if (!empty($filtered_sessions)): ?>
                <h4 style="margin-top: 2rem;">Sessões Encontradas:</h4>
                <table class="report-table" style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Tutor</th>
                            <th>Estudante</th>
                            <th>Assunto</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_sessions as $session): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($session['data'])); ?></td>
                                <td><?php echo date('H:i', strtotime($session['horario_inicio'])) . ' - ' . date('H:i', strtotime($session['horario_termino'])); ?></td>
                                <td><?php echo htmlspecialchars($session['tutor_nome']); ?></td>
                                <td><?php echo htmlspecialchars($session['estudante_nome']); ?></td>
                                <td><?php echo htmlspecialchars($session['assunto']); ?></td>
                                <td><?php echo htmlspecialchars($session['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin-top: 1rem;">Nenhuma sessão encontrada com os filtros aplicados.</p>
            <?php endif; ?>
        </div>

        <!-- Seção para RF12 - Edição de Dados dos Usuários -->
        <div class="card" style="margin-top: 2rem;">
            <h3>Editar Dados dos Usuários (RF12)</h3>
            <?php if (!empty($edit_success)): ?>
                <div class="alert alert-success">
                    <?php echo $edit_success; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($edit_error)): ?>
                <div class="alert alert-error">
                    <?php echo $edit_error; ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="filter-form" action="dashboard_coordenador.php">
                <input type="hidden" name="action" value="search_user">
                <div class="form-group">
                    <label for="search_type">Tipo de Usuário:</label>
                    <select id="search_type" name="search_type">
                        <option value="tutor">Tutor</option>
                        <option value="estudante">Estudante</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search_query">ID ou E-mail do Usuário:</label>
                    <input type="text" id="search_query" name="search_query" value="<?php echo isset($_POST['search_query']) ? htmlspecialchars($_POST['search_query']) : ''; ?>" placeholder="ID ou E-mail">
                </div>
                <div class="form-group" style="text-align: right;">
                    <button type="submit" class="btn">Buscar Usuário</button>
                </div>
            </form>

            <?php if ($user_to_edit): ?>
                <h4 style="margin-top: 2rem;">Editar Dados de <?php echo htmlspecialchars($user_to_edit['nome']); ?> (<?php echo ucfirst($user_to_edit['user_type']); ?>)</h4>
                <form method="POST" class="filter-form" style="margin-top: 1rem;" action="dashboard_coordenador.php">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_to_edit['id']); ?>">
                    <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($user_to_edit['user_type']); ?>">
                    
                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($user_to_edit['nome']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" required>
                    </div>
                    
                    <?php if ($user_to_edit['user_type'] === 'tutor'): ?>
                        <div class="form-group">
                            <label for="telefone">Telefone:</label>
                            <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($user_to_edit['telefone']); ?>">
                        </div>
                    <?php elseif ($user_to_edit['user_type'] === 'estudante'): ?>
                        <div class="form-group">
                            <label for="curso">Curso:</label>
                            <input type="text" id="curso" name="curso" value="<?php echo htmlspecialchars($user_to_edit['curso']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="matricula">Matrícula:</label>
                            <input type="text" id="matricula" name="matricula" value="<?php echo htmlspecialchars($user_to_edit['matricula']); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group" style="text-align: right;">
                        <button type="submit" class="btn">Salvar Alterações</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    </div>
</body>
</html> 