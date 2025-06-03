<?php
require_once 'config.php';
require_once 'functions.php';
requireAuth();

if ($_SESSION['user_type'] !== 'tutor') {
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();

// Processar atualização de horários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dia_semana = $_POST['dia_semana'];
    $horario_inicio = $_POST['horario_inicio'];
    $horario_termino = $_POST['horario_termino'];

    // Validar horários
    $hora_inicio = new DateTime($horario_inicio);
    $hora_termino = new DateTime($horario_termino);

    if ($hora_inicio >= $hora_termino) {
        setFlashMessage('error', 'O horário de início deve ser anterior ao horário de término.');
    } else {
        // Verificar se já existe agendamento no horário
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM agendamentos
            WHERE tutor_id = ? AND status = 'agendado'
            AND (
                (horario_inicio <= ? AND horario_termino > ?) OR
                (horario_inicio < ? AND horario_termino >= ?) OR
                (horario_inicio >= ? AND horario_termino <= ?)
            )
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $horario_inicio, $horario_inicio,
            $horario_termino, $horario_termino,
            $horario_inicio, $horario_termino
        ]);

        if ($stmt->fetchColumn() > 0) {
            setFlashMessage('error', 'Já existem agendamentos neste horário.');
        } else {
            $stmt = $conn->prepare("
                UPDATE tutores 
                SET dia_semana = ?, horario_inicio = ?, horario_termino = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$dia_semana, $horario_inicio, $horario_termino, $_SESSION['user_id']])) {
                setFlashMessage('success', 'Horários atualizados com sucesso!');
            } else {
                setFlashMessage('error', 'Erro ao atualizar horários.');
            }
        }
    }
}

// Buscar horários atuais do tutor
$stmt = $conn->prepare("
    SELECT dia_semana, horario_inicio, horario_termino
    FROM tutores
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$horarios = $stmt->fetch();

// Lista de dias da semana
$dias_semana = [
    'Segunda' => 'Segunda-feira',
    'Terca' => 'Terça-feira',
    'Quarta' => 'Quarta-feira',
    'Quinta' => 'Quinta-feira',
    'Sexta' => 'Sexta-feira',
    'Sabado' => 'Sábado',
    'Domingo' => 'Domingo'
];

// Gerar horários disponíveis
$horarios_disponiveis = [];
$hora_atual = new DateTime('00:00');
$hora_fim = new DateTime('23:59');
$intervalo = new DateInterval('PT30M'); // Intervalo de 30 minutos

while ($hora_atual < $hora_fim) {
    $horarios_disponiveis[] = $hora_atual->format('H:i');
    $hora_atual->add($intervalo);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Horários - <?php echo SITE_NAME; ?></title>
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
            <h1 class="dashboard-title">Gerenciar Horários de Disponibilidade</h1>

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

            <div class="grid grid-2">
                <div class="card">
                    <h3>Horários Atuais</h3>
                    <?php if ($horarios): ?>
                        <p><strong>Dia da Semana:</strong> <?php echo $dias_semana[$horarios['dia_semana']]; ?></p>
                        <p><strong>Horário de Início:</strong> <?php echo date('H:i', strtotime($horarios['horario_inicio'])); ?></p>
                        <p><strong>Horário de Término:</strong> <?php echo date('H:i', strtotime($horarios['horario_termino'])); ?></p>
                    <?php else: ?>
                        <p>Você ainda não definiu seus horários de disponibilidade.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Atualizar Horários</h3>
                    <form method="POST" class="grid grid-1">
                        <div class="form-group">
                            <label for="dia_semana">Dia da Semana:</label>
                            <select name="dia_semana" id="dia_semana" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($dias_semana as $valor => $nome): ?>
                                    <option value="<?php echo $valor; ?>" 
                                            <?php echo $horarios && $horarios['dia_semana'] === $valor ? 'selected' : ''; ?>>
                                        <?php echo $nome; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="horario_inicio">Horário de Início:</label>
                            <select name="horario_inicio" id="horario_inicio" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($horarios_disponiveis as $horario): ?>
                                    <option value="<?php echo $horario; ?>"
                                            <?php echo $horarios && $horarios['horario_inicio'] === $horario ? 'selected' : ''; ?>>
                                        <?php echo $horario; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="horario_termino">Horário de Término:</label>
                            <select name="horario_termino" id="horario_termino" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($horarios_disponiveis as $horario): ?>
                                    <option value="<?php echo $horario; ?>"
                                            <?php echo $horarios && $horarios['horario_termino'] === $horario ? 'selected' : ''; ?>>
                                        <?php echo $horario; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn">Atualizar Horários</button>
                        </div>
                    </form>
                </div>
            </div>

            <div style="margin-top: 30px; text-align: center;">
                <a href="dashboard.php" class="btn btn-secondary">Voltar ao Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        // Validação do horário de término
        document.getElementById('horario_inicio').addEventListener('change', function() {
            const horaInicio = this.value;
            const horaTermino = document.getElementById('horario_termino');
            const options = horaTermino.options;
            
            for (let i = 0; i < options.length; i++) {
                if (options[i].value <= horaInicio) {
                    options[i].disabled = true;
                } else {
                    options[i].disabled = false;
                }
            }
        });
    </script>
</body>
</html> 