<?php
require_once 'config.php';
require_once 'functions.php';
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
                    horario_termino, assunto, descricao, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')
            ");
            
            if ($stmt->execute([
                $tutor_id, $_SESSION['user_id'], $data, $horario_inicio,
                $horario_termino, $assunto, $descricao
            ])) {
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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Sessão - <?php echo SITE_NAME; ?></title>
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid rgba(139, 90, 60, 0.2);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(232, 146, 74, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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

        // Validação do horário de início
        document.getElementById('horario_termino').addEventListener('change', function() {
            const horaTermino = this.value;
            const horaInicio = document.getElementById('horario_inicio');
            
            if (horaTermino <= horaInicio.value) {
                alert('O horário de término deve ser posterior ao horário de início.');
                this.value = '';
            }
        });
    </script>
</body>
</html> 