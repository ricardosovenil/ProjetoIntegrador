<?php
require_once 'config.php';
require_once 'functions.php';
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

    // Buscar agendamentos futuros
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
} else {
    // Buscar agendamentos futuros
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

    // Buscar total de sessões concluídas
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM agendamentos
        WHERE estudante_id = ? AND status = 'concluido'
    ");
    $stmt->execute([$user_id]);
    $sessoes = $stmt->fetch();
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

            <?php if ($user_type === 'tutor'): ?>
                <div class="grid grid-3" style="margin-bottom: 30px;">
                    <div class="card">
                        <h3>Áreas de Atuação</h3>
                        <p class="stat"><?php echo count($areas); ?></p>
                        <a href="gerenciar_areas.php" class="btn btn-secondary" style="width: 100%;">
                            Gerenciar Áreas
                        </a>
                    </div>
                    <div class="card">
                        <h3>Média de Avaliações</h3>
                        <p class="stat"><?php echo $avaliacoes['media'] ? number_format($avaliacoes['media'], 1) : '0.0'; ?>/5</p>
                        <p style="text-align: center; color: var(--text-color);">
                            <?php echo $avaliacoes['total']; ?> avaliações
                        </p>
                    </div>
                    <div class="card">
                        <h3>Próximos Agendamentos</h3>
                        <p class="stat"><?php echo count($agendamentos); ?></p>
                        <a href="gerenciar_agendamentos.php" class="btn btn-secondary" style="width: 100%;">
                            Ver Todos
                        </a>
                    </div>
                </div>

                <?php if (!empty($areas)): ?>
                    <div class="card" style="margin-bottom: 30px;">
                        <h3>Suas Áreas de Atuação</h3>
                        <div class="grid grid-3">
                            <?php foreach ($areas as $area): ?>
                                <div class="card">
                                    <p style="text-align: center;"><?php echo htmlspecialchars($area); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($agendamentos)): ?>
                    <div class="card">
                        <h3>Próximos Agendamentos</h3>
                        <div class="grid grid-1">
                            <?php foreach ($agendamentos as $agendamento): ?>
                                <div class="card appointment-card">
                                    <div class="grid grid-2">
                                        <div>
                                            <h4><?php echo htmlspecialchars($agendamento['assunto']); ?></h4>
                                            <p><strong>Estudante:</strong> <?php echo htmlspecialchars($agendamento['estudante_nome']); ?></p>
                                            <p><strong>Curso:</strong> <?php echo htmlspecialchars($agendamento['estudante_curso']); ?></p>
                                            <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($agendamento['data'])); ?></p>
                                            <p><strong>Horário:</strong> 
                                                <?php echo date('H:i', strtotime($agendamento['horario_inicio'])); ?> - 
                                                <?php echo date('H:i', strtotime($agendamento['horario_termino'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p><strong>Descrição:</strong></p>
                                            <p><?php echo nl2br(htmlspecialchars($agendamento['descricao'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="grid grid-2" style="margin-bottom: 30px;">
                    <div class="card">
                        <h3>Total de Sessões</h3>
                        <p class="stat"><?php echo $sessoes['total']; ?></p>
                    </div>
                    <div class="card">
                        <h3>Próximos Agendamentos</h3>
                        <p class="stat"><?php echo count($agendamentos); ?></p>
                    </div>
                </div>

                <?php if (!empty($agendamentos)): ?>
                    <div class="card">
                        <h3>Próximos Agendamentos</h3>
                        <div class="grid grid-1">
                            <?php foreach ($agendamentos as $agendamento): ?>
                                <div class="card appointment-card">
                                    <div class="grid grid-2">
                                        <div>
                                            <h4><?php echo htmlspecialchars($agendamento['assunto']); ?></h4>
                                            <p><strong>Tutor:</strong> <?php echo htmlspecialchars($agendamento['tutor_nome']); ?></p>
                                            <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($agendamento['data'])); ?></p>
                                            <p><strong>Horário:</strong> 
                                                <?php echo date('H:i', strtotime($agendamento['horario_inicio'])); ?> - 
                                                <?php echo date('H:i', strtotime($agendamento['horario_termino'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p><strong>Descrição:</strong></p>
                                            <p><?php echo nl2br(htmlspecialchars($agendamento['descricao'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-top: 30px; text-align: center;">
                    <h3>Precisa de ajuda?</h3>
                    <p>Encontre um tutor disponível para te ajudar!</p>
                    <a href="buscar_tutores.php" class="btn" style="margin-top: 15px;">
                        Buscar Tutores
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
