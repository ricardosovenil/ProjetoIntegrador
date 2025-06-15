<?php
require_once 'config.php';
require_once 'functions.php';
requireAuth();

if ($_SESSION['user_type'] !== 'tutor') {
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();

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

// Buscar áreas do tutor
$stmt = $conn->prepare("
    SELECT a.* 
    FROM areas a
    JOIN areas_tutor at ON a.id = at.area_id
    WHERE at.tutor_id = ?
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
    ORDER BY a.nome
");
$stmt->execute([$_SESSION['user_id']]);
$areas_disponiveis = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Áreas - <?php echo SITE_NAME; ?></title>
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
                <div class="card">
                    <h3>Suas Áreas</h3>
                    <?php if (empty($areas_tutor)): ?>
                        <p>Você ainda não possui áreas cadastradas.</p>
                    <?php else: ?>
                        <div class="grid grid-1">
                            <?php foreach ($areas_tutor as $area): ?>
                                <div class="card">
                                    <div class="grid grid-2">
                                        <div>
                                            <h4><?php echo htmlspecialchars($area['nome'] ?? ''); ?></h4>
                                            <p><?php echo htmlspecialchars($area['descricao'] ?? ''); ?></p>
                                        </div>
                                        <div style="text-align: right;">
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

                <div class="card">
                    <h3>Adicionar Nova Área</h3>
                    <?php if (empty($areas_disponiveis)): ?>
                        <p>Você já possui todas as áreas disponíveis.</p>
                    <?php else: ?>
                        <div class="grid grid-1">
                            <?php foreach ($areas_disponiveis as $area): ?>
                                <div class="card">
                                    <div class="grid grid-2">
                                        <div>
                                            <h4><?php echo htmlspecialchars($area['nome'] ?? ''); ?></h4>
                                            <p><?php echo htmlspecialchars($area['descricao'] ?? ''); ?></p>
                                        </div>
                                        <div style="text-align: right;">
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