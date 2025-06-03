<?php
require_once 'config.php';
require_once 'functions.php';
requireAuth();

if ($_SESSION['user_type'] !== 'estudante') {
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();

// Buscar todas as áreas
$stmt = $conn->query("SELECT id, nome FROM areas ORDER BY nome");
$areas = $stmt->fetchAll();

// Processar filtros
$area_id = isset($_GET['area']) ? (int)$_GET['area'] : null;
$dia = isset($_GET['dia']) ? $_GET['dia'] : null;

// Construir a query base
$query = "
    SELECT DISTINCT t.*, GROUP_CONCAT(a.nome) as areas_nome
    FROM tutores t
    LEFT JOIN areas_tutor at ON t.id = at.tutor_id
    LEFT JOIN areas a ON at.area_id = a.id
    WHERE t.id IS NOT NULL
";

$params = [];

if ($area_id) {
    $query .= " AND at.area_id = ?";
    $params[] = $area_id;
}

if ($dia) {
    $query .= " AND t.dia_semana = ?";
    $params[] = $dia;
}

$query .= " GROUP BY t.id ORDER BY t.nome";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $tutores = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erro ao buscar tutores: " . $e->getMessage();
    $tutores = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Tutores - <?php echo SITE_NAME; ?></title>
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

        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-brown);
            font-weight: bold;
        }

        .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid rgba(139, 90, 60, 0.2);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(232, 146, 74, 0.2);
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
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232, 146, 74, 0.6);
            background: linear-gradient(135deg, var(--secondary-orange) 0%, var(--primary-orange) 100%);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .alert-error {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.2);
            color: #d32f2f;
        }

        .card h3 {
            color: var(--brown);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: bold;
        }

        .card p {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .card strong {
            color: var(--dark-brown);
        }

        @media (max-width: 768px) {
            .grid-2 {
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
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="logout.php" class="nav-link">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1 class="dashboard-title">Buscar Tutores</h1>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="GET" class="grid grid-2" style="margin-bottom: 30px;">
                <div class="form-group">
                    <label for="area">Área:</label>
                    <select name="area" id="area">
                        <option value="">Todas as áreas</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo $area['id']; ?>" 
                                    <?php echo $area_id == $area['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="dia">Dia da Semana:</label>
                    <select name="dia" id="dia">
                        <option value="">Todos os dias</option>
                        <option value="Segunda" <?php echo $dia === 'Segunda' ? 'selected' : ''; ?>>Segunda-feira</option>
                        <option value="Terca" <?php echo $dia === 'Terca' ? 'selected' : ''; ?>>Terça-feira</option>
                        <option value="Quarta" <?php echo $dia === 'Quarta' ? 'selected' : ''; ?>>Quarta-feira</option>
                        <option value="Quinta" <?php echo $dia === 'Quinta' ? 'selected' : ''; ?>>Quinta-feira</option>
                        <option value="Sexta" <?php echo $dia === 'Sexta' ? 'selected' : ''; ?>>Sexta-feira</option>
                        <option value="Sabado" <?php echo $dia === 'Sabado' ? 'selected' : ''; ?>>Sábado</option>
                        <option value="Domingo" <?php echo $dia === 'Domingo' ? 'selected' : ''; ?>>Domingo</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <button type="submit" class="btn">Filtrar</button>
                </div>
            </form>

            <?php if (empty($tutores)): ?>
                <p>Nenhum tutor encontrado com os filtros selecionados.</p>
            <?php else: ?>
                <div class="grid grid-2">
                    <?php foreach ($tutores as $tutor): ?>
                        <div class="card">
                            <h3><?php echo htmlspecialchars($tutor['nome']); ?></h3>
                            <p><strong>Áreas:</strong> <?php echo htmlspecialchars($tutor['areas_nome'] ?? 'Não especificado'); ?></p>
                            <p><strong>Dia:</strong> <?php echo htmlspecialchars($tutor['dia_semana'] ?? 'Não especificado'); ?></p>
                            <p><strong>Horário:</strong> 
                                <?php 
                                if (!empty($tutor['horario_inicio']) && !empty($tutor['horario_termino'])) {
                                    echo date('H:i', strtotime($tutor['horario_inicio'])) . ' - ' . 
                                         date('H:i', strtotime($tutor['horario_termino']));
                                } else {
                                    echo 'Não especificado';
                                }
                                ?>
                            </p>
                            <a href="agendar.php?tutor_id=<?php echo $tutor['id']; ?>" 
                               class="btn" style="margin-top: 15px;">
                                Agendar Sessão
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 