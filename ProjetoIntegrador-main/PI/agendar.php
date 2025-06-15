<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'notifications.php';
requireAuth();

if ($_SESSION['user_type'] !== 'estudante') {
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();
$tutor_id = isset($_GET['tutor_id']) ? (int)$_GET['tutor_id'] : 0;

// Buscar informações do tutor
$stmt = $conn->prepare("
    SELECT t.*, GROUP_CONCAT(a.nome) as areas_nome
    FROM tutores t
    LEFT JOIN areas_tutor at ON t.id = at.tutor_id
    LEFT JOIN areas a ON at.area_id = a.id
    WHERE t.id = ?
    GROUP BY t.id
");
$stmt->execute([$tutor_id]);
$tutor = $stmt->fetch();

if (!$tutor) {
    header('Location: buscar_tutores.php');
    exit;
}

// Debug: Verificar dados do tutor
error_log("Dados do tutor: " . print_r($tutor, true));

// Buscar agendamentos existentes para o tutor
$stmt = $conn->prepare("
    SELECT data, horario_inicio, horario_termino
    FROM agendamentos
    WHERE tutor_id = ? AND status IN ('agendado', 'pendente')
    AND data >= CURDATE()
");
$stmt->execute([$tutor_id]);
$agendamentos = $stmt->fetchAll();

// Processar o formulário de agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'];
    $horario_inicio = $_POST['horario_inicio'];
    $horario_termino = $_POST['horario_termino'];
    $assunto = sanitizeInput($_POST['assunto']);
    $descricao = sanitizeInput($_POST['descricao']);
    $local = sanitizeInput($_POST['local']);
    $link_videoconferencia = sanitizeInput($_POST['link_videoconferencia']);

    // Validar horário
    $horario_inicio_obj = new DateTime($horario_inicio);
    $horario_termino_obj = new DateTime($horario_termino);
    $horario_inicio_tutor = new DateTime($tutor['horario_inicio']);
    $horario_termino_tutor = new DateTime($tutor['horario_termino']);

    if ($horario_inicio_obj >= $horario_termino_obj) {
        setFlashMessage('error', 'O horário de início deve ser anterior ao horário de término.');
    } elseif ($horario_inicio_obj < $horario_inicio_tutor || $horario_termino_obj > $horario_termino_tutor) {
        setFlashMessage('error', 'O horário selecionado está fora do horário de disponibilidade do tutor.');
    } else {
        // Verificar se já existe agendamento no mesmo horário
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM agendamentos
            WHERE tutor_id = ? AND data = ? AND status IN ('agendado', 'pendente')
            AND (
                (horario_inicio <= ? AND horario_termino > ?) OR
                (horario_inicio < ? AND horario_termino >= ?) OR
                (horario_inicio >= ? AND horario_termino <= ?)
            )
        ");
        $stmt->execute([
            $tutor_id, $data, $horario_inicio, $horario_inicio,
            $horario_termino, $horario_termino, $horario_inicio, $horario_termino
        ]);
        
        if ($stmt->fetchColumn() > 0) {
            setFlashMessage('error', 'Já existe um agendamento neste horário.');
        } else {
            // Criar o agendamento
            $stmt = $conn->prepare("
                INSERT INTO agendamentos (
                    tutor_id, estudante_id, data, horario_inicio, 
                    horario_termino, assunto, descricao, local, link_videoconferencia, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
            ");
            
            if ($stmt->execute([
                $tutor_id, $_SESSION['user_id'], $data, $horario_inicio,
                $horario_termino, $assunto, $descricao, $local, $link_videoconferencia
            ])) {
                $appointment_id = $conn->lastInsertId();
                notifyNewAppointment($appointment_id);
                setFlashMessage('success', 'Sessão solicitada com sucesso! Aguarde a confirmação do tutor.');
                header('Location: dashboard.php');
                exit;
            } else {
                setFlashMessage('error', 'Erro ao solicitar a sessão. Tente novamente.');
            }
        }
    }
}

// Gerar horários disponíveis
$horarios_disponiveis = [];
if (!empty($tutor['horario_inicio']) && !empty($tutor['horario_termino'])) {
    $hora_atual = new DateTime($tutor['horario_inicio']);
    $hora_fim = new DateTime($tutor['horario_termino']);
    $intervalo = new DateInterval('PT30M'); // Intervalo de 30 minutos

    while ($hora_atual < $hora_fim) {
        $horarios_disponiveis[] = $hora_atual->format('H:i');
        $hora_atual->add($intervalo);
    }
}

// Debug: Verificar horários gerados
error_log("Horários disponíveis: " . print_r($horarios_disponiveis, true));

// Preparar dados para o calendário
$agendamentos_json = json_encode(array_map(function($agendamento) {
    return [
        'date' => $agendamento['data'],
        'start' => $agendamento['horario_inicio'],
        'end' => $agendamento['horario_termino']
    ];
}, $agendamentos));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Sessão - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/locales-all.min.js"></script>
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
            <h1 class="dashboard-title">Agendar Sessão</h1>

            <div class="card" style="margin-bottom: 30px;">
                <h3>Informações do Tutor</h3>
                <p><strong>Nome:</strong> <?php echo htmlspecialchars($tutor['nome']); ?></p>
                <p><strong>Áreas:</strong> <?php echo htmlspecialchars($tutor['areas_nome'] ?? 'Não especificado'); ?></p>
                <p><strong>Dia:</strong> <?php echo htmlspecialchars($tutor['dia_semana'] ?? 'Não especificado'); ?></p>
                <p><strong>Horário Disponível:</strong> 
                    <?php 
                    if (!empty($tutor['horario_inicio']) && !empty($tutor['horario_termino'])) {
                        echo date('H:i', strtotime($tutor['horario_inicio'])) . ' - ' . 
                             date('H:i', strtotime($tutor['horario_termino']));
                    } else {
                        echo 'Não especificado';
                    }
                    ?>
                </p>
            </div>

            <?php if (hasFlashMessage('error')): ?>
                <div class="alert alert-error">
                    <?php echo getFlashMessage()['message']; ?>
                </div>
            <?php endif; ?>

            <div class="calendar-container" style="margin-bottom: 30px;">
                <div id="calendar"></div>
            </div>

            <form method="POST" class="grid grid-2">
                <div class="form-group">
                    <label for="data">Data:</label>
                    <input type="date" id="data" name="data" required
                           min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="horario_inicio">Horário de Início:</label>
                    <select name="horario_inicio" id="horario_inicio" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($horarios_disponiveis as $horario): ?>
                            <option value="<?php echo $horario; ?>">
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
                            <option value="<?php echo $horario; ?>">
                                <?php echo $horario; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assunto">Assunto:</label>
                    <input type="text" id="assunto" name="assunto" required
                           placeholder="Ex: Revisão de Cálculo 1">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="descricao">Descrição:</label>
                    <textarea id="descricao" name="descricao" rows="4" required
                              placeholder="Descreva o que você gostaria de abordar na sessão..."></textarea>
                </div>

                <div class="form-group">
                    <label for="local">Local:</label>
                    <input type="text" id="local" name="local" required
                           placeholder="Ex: Sala 101, Biblioteca">
                </div>

                <div class="form-group">
                    <label for="link_videoconferencia">Link da Videoconferência (opcional):</label>
                    <input type="url" id="link_videoconferencia" name="link_videoconferencia"
                           placeholder="Ex: https://meet.google.com/xxx-yyyy-zzz">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <button type="submit" class="btn">Solicitar Agendamento</button>
                    <a href="buscar_tutores.php" class="btn btn-secondary" style="margin-left: 10px;">
                        Voltar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: <?php echo $agendamentos_json; ?>,
                eventColor: '#378006',
                selectable: true,
                select: function(info) {
                    document.getElementById('data').value = info.startStr;
                }
            });
            calendar.render();

            // Validação do horário de término
            document.getElementById('horario_inicio').addEventListener('change', function() {
                const horaInicio = this.value;
                const horaTermino = document.getElementById('horario_termino');
                const options = horaTermino.options;
                
                // Habilitar todas as opções primeiro
                for (let i = 0; i < options.length; i++) {
                    options[i].disabled = false;
                }
                
                // Desabilitar horários anteriores ou iguais ao início
                for (let i = 0; i < options.length; i++) {
                    if (options[i].value <= horaInicio) {
                        options[i].disabled = true;
                    }
                }
                
                // Se o horário de término selecionado for inválido, limpar a seleção
                if (horaTermino.value <= horaInicio) {
                    horaTermino.value = '';
                }
            });
        });
    </script>
</body>
</html> 