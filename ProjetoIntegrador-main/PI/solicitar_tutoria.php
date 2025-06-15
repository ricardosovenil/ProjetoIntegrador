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

// Processa o formulário de solicitação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tutor_id = filter_input(INPUT_POST, 'tutor_id', FILTER_VALIDATE_INT);
    $descricao = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_STRING);
    $urgencia = filter_input(INPUT_POST, 'urgencia', FILTER_SANITIZE_STRING);

    if ($tutor_id && $descricao && $urgencia) {
        $sql = "INSERT INTO solicitacoes_tutoria (estudante_id, tutor_id, descricao, urgencia) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$estudante_id, $tutor_id, $descricao, $urgencia])) {
            $mensagem = "Solicitação enviada com sucesso!";
        } else {
            $mensagem = "Erro ao enviar solicitação. Tente novamente.";
        }
    }
}

// Busca tutores disponíveis
$sql = "SELECT t.*, GROUP_CONCAT(a.nome) as areas 
        FROM tutores t 
        LEFT JOIN areas_tutor at ON t.id = at.tutor_id 
        LEFT JOIN areas a ON at.area_id = a.id 
        GROUP BY t.id";
$stmt = $conn->query($sql);
$tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Tutoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Solicitar Sessão de Tutoria</h2>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-info"><?php echo $mensagem; ?></div>
        <?php endif; ?>

        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label for="tutor" class="form-label">Selecione o Tutor:</label>
                <select name="tutor_id" id="tutor" class="form-select" required>
                    <option value="">Selecione um tutor...</option>
                    <?php foreach ($tutores as $tutor): ?>
                        <option value="<?php echo $tutor['id']; ?>">
                            <?php echo htmlspecialchars($tutor['nome']); ?> 
                            (Áreas: <?php echo htmlspecialchars($tutor['areas']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição da Tutoria:</label>
                <textarea name="descricao" id="descricao" class="form-control" rows="4" required
                    placeholder="Descreva o assunto ou tópico que você precisa de ajuda..."></textarea>
            </div>

            <div class="mb-3">
                <label for="urgencia" class="form-label">Nível de Urgência:</label>
                <select name="urgencia" id="urgencia" class="form-select" required>
                    <option value="baixa">Baixa</option>
                    <option value="media">Média</option>
                    <option value="alta">Alta</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Enviar Solicitação</button>
            <a href="dashboard.php" class="btn btn-secondary">Voltar</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 