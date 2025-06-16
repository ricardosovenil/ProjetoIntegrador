<?php
require_once 'config.php';
require_once 'functions.php';
requireAuth();

if ($_SESSION['user_type'] !== 'tutor') {
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();

// Limpar duplicatas na tabela areas
$stmt = $conn->prepare("
    DELETE a1 FROM areas a1
    INNER JOIN areas a2
    WHERE a1.nome = a2.nome
    AND a1.id > a2.id
");
$stmt->execute();

// Limpar duplicatas na tabela areas_tutor
$stmt = $conn->prepare("
    DELETE t1 FROM areas_tutor t1
    INNER JOIN areas_tutor t2
    WHERE t1.tutor_id = t2.tutor_id 
    AND t1.area_id = t2.area_id
    AND t1.tutor_id = ?
    AND t1.area_id > t2.area_id
");
$stmt->execute([$_SESSION['user_id']]);

// Buscar áreas do tutor
$stmt = $conn->prepare("
    SELECT a.* 
    FROM areas a
    INNER JOIN areas_tutor at ON a.id = at.area_id
    WHERE at.tutor_id = ?
    GROUP BY a.id
    ORDER BY a.nome
");
$stmt->execute([$_SESSION['user_id']]);
$areas_tutor = $stmt->fetchAll();

// Buscar todas as áreas disponíveis
$stmt = $conn->prepare("
    SELECT a.* 
    FROM areas a
    WHERE a.id NOT IN (
        SELECT area_id 
        FROM areas_tutor 
        WHERE tutor_id = ?
    )
    GROUP BY a.id
    ORDER BY a.nome
");
$stmt->execute([$_SESSION['user_id']]);
$areas_disponiveis = $stmt->fetchAll();

// Processar adição/remoção de áreas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['adicionar_area'])) {
        $area_id = (int)$_POST['area_id'];
        
        // Verificar se a área já está associada ao tutor
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM areas_tutor 
            WHERE tutor_id = ? AND area_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $area_id]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $conn->prepare("
                INSERT INTO areas_tutor (tutor_id, area_id) 
                VALUES (?, ?)
            ");
            
            if ($stmt->execute([$_SESSION['user_id'], $area_id])) {
                setFlashMessage('success', 'Área adicionada com sucesso!');
            } else {
                setFlashMessage('error', 'Erro ao adicionar área.');
            }
        } else {
            setFlashMessage('error', 'Esta área já está associada ao seu perfil.');
        }
    } elseif (isset($_POST['remover_area'])) {
        $area_id = (int)$_POST['area_id'];
        
        $stmt = $conn->prepare("
            DELETE FROM areas_tutor 
            WHERE tutor_id = ? AND area_id = ?
        ");
        
        if ($stmt->execute([$_SESSION['user_id'], $area_id])) {
            setFlashMessage('success', 'Área removida com sucesso!');
        } else {
            setFlashMessage('error', 'Erro ao remover área.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Áreas - <?php echo SITE_NAME; ?></title>
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

        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid-1 {
            grid-template-columns: 1fr;
        }

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

        h3 {
            color: var(--dark-brown);
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
        }

        h4 {
            color: var(--brown);
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        p {
            color: var(--text-dark);
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .grid-2 {
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
            <h1 class="dashboard-title">Gerenciar Áreas de Atuação</h1>

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

            <div class="grid grid-2">
                <div class="card" style="background: var(--warm-cream);">
                    <h3>Suas Áreas</h3>
                    <?php if (empty($areas_tutor)): ?>
                        <p>Você ainda não possui áreas cadastradas.</p>
                    <?php else: ?>
                        <div class="grid grid-1">
                            <?php foreach ($areas_tutor as $area): ?>
                                <div class="card" style="background: white;">
                                    <div class="grid grid-2">
                                        <div>
                                            <h4><?php echo htmlspecialchars($area['nome'] ?? ''); ?></h4>
                                            <p><?php echo htmlspecialchars($area['descricao'] ?? ''); ?></p>
                                        </div>
                                        <div style="text-align: right; display: flex; align-items: center; justify-content: flex-end;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="area_id" value="<?php echo $area['id']; ?>">
                                                <button type="submit" name="remover_area" class="btn btn-secondary">
                                                    Remover
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card" style="background: var(--warm-cream);">
                    <h3>Adicionar Nova Área</h3>
                    <?php if (empty($areas_disponiveis)): ?>
                        <p>Você já possui todas as áreas disponíveis.</p>
                    <?php else: ?>
                        <div class="grid grid-1">
                            <?php foreach ($areas_disponiveis as $area): ?>
                                <div class="card" style="background: white;">
                                    <div class="grid grid-2">
                                        <div>
                                            <h4><?php echo htmlspecialchars($area['nome'] ?? ''); ?></h4>
                                            <p><?php echo htmlspecialchars($area['descricao'] ?? ''); ?></p>
                                        </div>
                                        <div style="text-align: right; display: flex; align-items: center; justify-content: flex-end;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="area_id" value="<?php echo $area['id']; ?>">
                                                <button type="submit" name="adicionar_area" class="btn">
                                                    Adicionar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 30px; text-align: center;">
                <a href="dashboard.php" class="btn btn-secondary">Voltar ao Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html> 