<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'notifications.php'; // Incluindo para usar getUserNotifications, se necessário
requireAuth();

$conn = getDBConnection();
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Buscar dados específicos baseado no tipo de usuário
if ($user_type === 'tutor') {
    // Buscar áreas de atuação
    $stmt = $conn->prepare("
        SELECT a.nome
        FROM areas a
        JOIN areas_tutor at ON a.id = at.area_id
        WHERE at.tutor_id = ?
        ORDER BY a.nome
    ");
    $stmt->execute([$user_id]);
    $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Buscar agendamentos futuros (tutores)
    $stmt = $conn->prepare("
        SELECT a.*, e.nome as estudante_nome, e.curso as estudante_curso
        FROM agendamentos a
        JOIN estudantes e ON a.estudante_id = e.id
        WHERE a.tutor_id = ? AND a.status = 'agendado'
        AND a.data >= CURDATE()
        ORDER BY a.data ASC, a.horario_inicio ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $agendamentos = $stmt->fetchAll();

    // Calcular média de avaliações (corrigido)
    $stmt = $conn->prepare("
        SELECT AVG(av.nota) as media, COUNT(*) as total
        FROM avaliacoes av
        JOIN agendamentos ag ON av.agendamento_id = ag.id
        WHERE ag.tutor_id = ?
    ");
    $stmt->execute([$user_id]);
    $avaliacoes = $stmt->fetch();

    // Buscar notificações não lidas para o tutor
    $notifications = getUserNotifications($user_id, $user_type, 5); // Buscar as 5 últimas

} else {
    // Buscar agendamentos futuros (estudantes)
    $stmt = $conn->prepare("
        SELECT a.*, t.nome as tutor_nome
        FROM agendamentos a
        JOIN tutores t ON a.tutor_id = t.id
        WHERE a.estudante_id = ? AND a.status = 'agendado'
        AND a.data >= CURDATE()
        ORDER BY a.data ASC, a.horario_inicio ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $agendamentos = $stmt->fetchAll();

    // Buscar sessões concluídas que ainda não foram avaliadas pelo estudante
    $stmt_avaliar = $conn->prepare("
        SELECT ag.id, ag.data, ag.horario_inicio, ag.horario_termino, ag.assunto, t.nome as tutor_nome
        FROM agendamentos ag
        JOIN tutores t ON ag.tutor_id = t.id
        WHERE ag.estudante_id = ? AND ag.status = 'concluido'
        AND NOT EXISTS (
            SELECT 1 FROM avaliacoes av WHERE av.agendamento_id = ag.id
        )
        ORDER BY ag.data DESC
        LIMIT 5
    ");
    $stmt_avaliar->execute([$user_id]);
    $sessoes_para_avaliar = $stmt_avaliar->fetchAll();

    // Buscar estatísticas do estudante
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'agendado' THEN 1 END) as agendadas,
            COUNT(CASE WHEN status = 'concluido' THEN 1 END) as concluidas,
            COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as canceladas
        FROM agendamentos 
        WHERE estudante_id = ?
    ");
    $stmt_stats->execute([$user_id]);
    $estatisticas = $stmt_stats->fetch();

    // Buscar histórico de sessões
    $stmt_historico = $conn->prepare("
        SELECT a.*, t.nome as tutor_nome, av.nota, av.comentario
        FROM agendamentos a
        JOIN tutores t ON a.tutor_id = t.id
        LEFT JOIN avaliacoes av ON a.id = av.agendamento_id
        WHERE a.estudante_id = ? AND a.status = 'concluido'
        ORDER BY a.data DESC, a.horario_inicio DESC
        LIMIT 5
    ");
    $stmt_historico->execute([$user_id]);
    $historico_sessoes = $stmt_historico->fetchAll();

    // Buscar áreas de interesse do estudante
    $stmt_areas = $conn->prepare("
        SELECT a.nome
        FROM areas a
        JOIN areas_estudante ae ON a.id = ae.area_id
        WHERE ae.estudante_id = ?
        ORDER BY a.nome
    ");
    $stmt_areas->execute([$user_id]);
    $areas_interesse = $stmt_areas->fetchAll(PDO::FETCH_COLUMN);

    // Buscar notificações não lidas para o estudante
    $notifications = getUserNotifications($user_id, $user_type, 5);
}

// Processar marcação de notificação como lida
if (isset($_GET['mark_read']) && isset($_GET['notification_id'])) {
    $notificationId = (int)$_GET['notification_id'];
    markNotificationAsRead($notificationId, $user_id, $user_type);
    // Redirecionar para limpar os parâmetros da URL
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
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
                <?php if ($user_type === 'tutor'): ?>
                    <a href="gerenciar_areas.php" class="nav-link">Áreas</a>
                    <a href="gerenciar_horarios.php" class="nav-link">Horários</a>
                    <a href="gerenciar_agendamentos.php" class="nav-link">Agendamentos</a>
                <?php else: ?>
                    <a href="buscar_tutores.php" class="nav-link">Buscar Tutores</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-link">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1 class="dashboard-title">Bem-vindo(a), <?php echo htmlspecialchars($user_name); ?>!</h1>

            <?php if (!empty($notifications)): ?>
                <div class="card" style="margin-top: 2rem;">
                    <h3>Suas Notificações</h3>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? 'read' : ''; ?>">
                            <span class="message"><?php echo htmlspecialchars($notification['message']); ?></span>
                            <span class="timestamp"><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></span>
                            <?php if (!$notification['is_read']): ?>
                                <a href="dashboard.php?mark_read=true&notification_id=<?php echo $notification['id']; ?>" class="mark-read-btn">Marcar como Lida</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($user_type === 'tutor'): ?>
                <div class="grid grid-3">
                    <div class="card">
                        <h2>Áreas de Atuação</h2>
                        <div class="stat-number">
                            <?php if (empty($areas)): ?>
                                Nenhuma
                            <?php else: ?>
                                <?php echo count($areas); ?>
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons">
                            <a href="gerenciar_areas.php" class="btn">
                                Gerenciar Áreas
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Média de Avaliações</h2>
                        <div class="stat-number">
                            <a href="ver_avaliacoes_tutor.php" style="text-decoration: none; color: inherit;">
                                <?php echo number_format($avaliacoes['media'] ?? 0, 1); ?>/5
                                <?php if (($avaliacoes['total'] ?? 0) > 0): ?>
                                    (<?php echo $avaliacoes['total']; ?> avaliações)
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="action-buttons">
                             <a href="ver_avaliacoes_tutor.php" class="btn">
                                Ver Avaliações
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Próximos Agendamentos</h2>
                        <div class="stat-number">
                            <?php echo count($agendamentos); ?>
                        </div>
                        <div class="action-buttons">
                            <a href="gerenciar_agendamentos.php" class="btn">
                                Ver Todos
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($user_type === 'estudante'): ?>
                <div class="dashboard-section">
                    <h2>Suas Estatísticas</h2>
                    <div class="grid grid-3 stats-summary">
                        <div class="card stat-card">
                            <h3>Agendadas</h3>
                            <p class="stat-number-small"><?php echo $estatisticas['agendadas']; ?></p>
                        </div>
                        <div class="card stat-card">
                            <h3>Concluídas</h3>
                            <p class="stat-number-small"><?php echo $estatisticas['concluidas']; ?></p>
                        </div>
                        <div class="card stat-card">
                            <h3>Canceladas</h3>
                            <p class="stat-number-small"><?php echo $estatisticas['canceladas']; ?></p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($agendamentos)): ?>
                    <div class="card" style="margin-top: 2rem;">
                        <h2>Próximas Sessões</h2>
                        <div class="grid">
                            <?php foreach ($agendamentos as $agendamento): ?>
                                <div class="card appointment-card">
                                    <h3><?php echo htmlspecialchars($agendamento['assunto']); ?></h3>
                                    <p><strong>Tutor:</strong> <?php echo htmlspecialchars($agendamento['tutor_nome']); ?></p>
                                    <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($agendamento['data'])); ?></p>
                                    <p><strong>Horário:</strong> 
                                        <?php echo date('H:i', strtotime($agendamento['horario_inicio'])); ?> - 
                                        <?php echo date('H:i', strtotime($agendamento['horario_termino'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($sessoes_para_avaliar)): ?>
                    <div class="card" style="margin-top: 2rem;">
                        <h2>Sessões Concluídas para Avaliar</h2>
                        <div class="grid">
                            <?php foreach ($sessoes_para_avaliar as $sessao): ?>
                                <div class="card appointment-card">
                                    <h3><?php echo htmlspecialchars($sessao['assunto']); ?></h3>
                                    <p><strong>Tutor:</strong> <?php echo htmlspecialchars($sessao['tutor_nome']); ?></p>
                                    <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($sessao['data'])); ?></p>
                                    <p><strong>Horário:</strong> 
                                        <?php echo date('H:i', strtotime($sessao['horario_inicio'])); ?> - 
                                        <?php echo date('H:i', strtotime($sessao['horario_termino'])); ?>
                                    </p>
                                    <a href="avaliar.php?id=<?php echo $sessao['id']; ?>" class="btn">
                                        Avaliar Sessão
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-top: 2rem;">
                    <h2 class="collapsible-header" id="historyHeader">Histórico de Sessões <span class="arrow">&#9660;</span></h2>
                    <div class="collapsible-content" id="historyContent" style="display: none;">
                        <?php if (empty($historico_sessoes)): ?>
                            <p>Nenhuma sessão concluída.</p>
                        <?php else: ?>
                            <div class="grid">
                                <?php foreach ($historico_sessoes as $sessao): ?>
                                    <div class="card appointment-card">
                                        <h3><?php echo htmlspecialchars($sessao['assunto']); ?></h3>
                                        <p><strong>Tutor:</strong> <?php echo htmlspecialchars($sessao['tutor_nome']); ?></p>
                                        <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($sessao['data'])); ?></p>
                                        <p><strong>Horário:</strong> 
                                            <?php echo date('H:i', strtotime($sessao['horario_inicio'])); ?> - 
                                            <?php echo date('H:i', strtotime($sessao['horario_termino'])); ?>
                                        </p>
                                        <?php if ($sessao['nota']): ?>
                                            <p><strong>Avaliação:</strong> <?php echo $sessao['nota']; ?>/5</p>
                                            <?php if ($sessao['comentario']): ?>
                                                <p><strong>Comentário:</strong> <?php echo htmlspecialchars($sessao['comentario']); ?></p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="avaliar.php?id=<?php echo $sessao['id']; ?>" class="btn">
                                                Avaliar Sessão
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card" style="margin-top: 30px; text-align: center;">
                    <h3>Precisa de ajuda?</h3>
                    <p>Encontre um tutor disponível para te ajudar!</p>
                    <a href="buscar_tutores.php" class="btn" style="margin-top: 15px;">
                        Buscar Tutores
                    </a>
                </div>

                <script>
                    document.getElementById('historyHeader').addEventListener('click', function() {
                        var content = document.getElementById('historyContent');
                        var arrow = this.querySelector('.arrow');
                        if (content.style.display === 'none') {
                            content.style.display = 'block';
                            arrow.innerHTML = '&#9650;'; // Seta para cima
                        } else {
                            content.style.display = 'none';
                            arrow.innerHTML = '&#9660;'; // Seta para baixo
                        }
                    });
                </script>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
