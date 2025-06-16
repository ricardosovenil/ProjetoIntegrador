<?php
require_once 'config.php';
require_once 'functions.php';

function sendEmailNotification($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . SITE_EMAIL . "\r\n";

    return mail($to, $subject, $message, $headers);
}

function notifyNewAppointment($appointment_id) {
    global $conn;
    
    // Buscar informações do agendamento
    $stmt = $conn->prepare("
        SELECT a.*, t.nome as tutor_nome, t.email as tutor_email, t.id as tutor_id,
               e.nome as estudante_nome, e.email as estudante_email, e.id as estudante_id
        FROM agendamentos a
        JOIN tutores t ON a.tutor_id = t.id
        JOIN estudantes e ON a.estudante_id = e.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) return false;

    // Email para o tutor
    $tutorSubject = "Nova solicitação de agendamento";
    $tutorMessage = "
        <h2>Nova solicitação de agendamento</h2>
        <p>Olá {$appointment['tutor_nome']},</p>
        <p>Você recebeu uma nova solicitação de agendamento:</p>
        <ul>
            <li><strong>Estudante:</strong> {$appointment['estudante_nome']}</li>
            <li><strong>Data:</strong> " . date('d/m/Y', strtotime($appointment['data'])) . "</li>
            <li><strong>Horário:</strong> {$appointment['horario_inicio']} - {$appointment['horario_termino']}</li>
            <li><strong>Assunto:</strong> {$appointment['assunto']}</li>
            <li><strong>Local:</strong> {$appointment['local']}</li>
        </ul>
        <p>Acesse o sistema para aceitar ou recusar esta solicitação.</p>
    ";

    // Notificação in-app para o tutor
    $notificationTutorMessage = "Você recebeu uma nova solicitação de agendamento de " . htmlspecialchars($appointment['estudante_nome']) . " para o dia " . date('d/m/Y', strtotime($appointment['data'])) . ".";
    createNotification($appointment['tutor_id'], 'tutor', $notificationTutorMessage);

    // Email para o estudante
    $studentSubject = "Solicitação de agendamento enviada";
    $studentMessage = "
        <h2>Solicitação de agendamento enviada</h2>
        <p>Olá {$appointment['estudante_nome']},</p>
        <p>Sua solicitação de agendamento foi enviada com sucesso:</p>
        <ul>
            <li><strong>Tutor:</strong> {$appointment['tutor_nome']}</li>
            <li><strong>Data:</strong> " . date('d/m/Y', strtotime($appointment['data'])) . "</li>
            <li><strong>Horário:</strong> {$appointment['horario_inicio']} - {$appointment['horario_termino']}</li>
            <li><strong>Assunto:</strong> {$appointment['assunto']}</li>
            <li><strong>Local:</strong> {$appointment['local']}</li>
        </ul>
        <p>Você receberá uma notificação quando o tutor responder à sua solicitação.</p>
    ";

    // Não é necessário notificação in-app para o estudante aqui, pois ele receberá ao mudar o status

    // Enviar emails
    sendEmailNotification($appointment['tutor_email'], $tutorSubject, $tutorMessage);
    sendEmailNotification($appointment['estudante_email'], $studentSubject, $studentMessage);

    return true;
}

function notifyAppointmentStatus($appointment_id, $status) {
    global $conn;
    
    // Buscar informações do agendamento
    $stmt = $conn->prepare("
        SELECT a.*, t.nome as tutor_nome, t.email as tutor_email, t.id as tutor_id,
               e.nome as estudante_nome, e.email as estudante_email, e.id as estudante_id
        FROM agendamentos a
        JOIN tutores t ON a.tutor_id = t.id
        JOIN estudantes e ON a.estudante_id = e.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) return false;

    $statusText = '';
    $notificationMessage = '';
    $subject = '';
    $message = '';

    if ($status === 'confirmado') {
        $statusText = 'confirmada';
        $notificationMessage = "Sua sessão com " . htmlspecialchars($appointment['tutor_nome']) . " para o dia " . date('d/m/Y', strtotime($appointment['data'])) . " foi confirmada!";
        $subject = "Solicitação de agendamento confirmada";
        $message = "
            <h2>Solicitação de agendamento confirmada</h2>
            <p>Olá {$appointment['estudante_nome']},</p>
            <p>Sua solicitação de agendamento com <strong>{$appointment['tutor_nome']}</strong> foi confirmada para:</p>
            <ul>
                <li><strong>Data:</strong> " . date('d/m/Y', strtotime($appointment['data'])) . "</li>
                <li><strong>Horário:</strong> {$appointment['horario_inicio']} - {$appointment['horario_termino']}</li>
                <li><strong>Assunto:</strong> {$appointment['assunto']}</li>
                <li><strong>Local:</strong> {$appointment['local']}</li>
            </ul>
            <p>Prepare-se para a sua sessão!</p>
        ";
    } elseif ($status === 'recusado') {
        $statusText = 'recusada';
        $notificationMessage = "Sua solicitação de sessão com " . htmlspecialchars($appointment['tutor_nome']) . " para o dia " . date('d/m/Y', strtotime($appointment['data'])) . " foi recusada.";
        $subject = "Solicitação de agendamento recusada";
        $message = "
            <h2>Solicitação de agendamento recusada</h2>
            <p>Olá {$appointment['estudante_nome']},</p>
            <p>Sua solicitação de agendamento com <strong>{$appointment['tutor_nome']}</strong> para:</p>
            <ul>
                <li><strong>Data:</strong> " . date('d/m/Y', strtotime($appointment['data'])) . "</li>
                <li><strong>Horário:</strong> {$appointment['horario_inicio']} - {$appointment['horario_termino']}</li>
                <li><strong>Assunto:</strong> {$appointment['assunto']}</li>
                <li><strong>Local:</strong> {$appointment['local']}</li>
            </ul>
            <p>Foi recusada pelo tutor. Por favor, tente agendar em outro horário ou com outro tutor.</p>
        ";
    } elseif ($status === 'cancelado') {
        $statusText = 'cancelada';
        $notificationMessage = "Sua sessão com " . htmlspecialchars($appointment['tutor_nome']) . " para o dia " . date('d/m/Y', strtotime($appointment['data'])) . " foi cancelada.";
        $subject = "Sessão de agendamento cancelada";
        $message = "
            <h2>Sessão de agendamento cancelada</h2>
            <p>Olá {$appointment['estudante_nome']},</p>
            <p>Sua sessão com <strong>{$appointment['tutor_nome']}</strong> para:</p>
            <ul>
                <li><strong>Data:</strong> " . date('d/m/Y', strtotime($appointment['data'])) . "</li>
                <li><strong>Horário:</strong> {$appointment['horario_inicio']} - {$appointment['horario_termino']}</li>
                <li><strong>Assunto:</strong> {$appointment['assunto']}</li>
                <li><strong>Local:</strong> {$appointment['local']}</li>
            </ul>
            <p>Foi cancelada. Por favor, verifique o sistema para mais detalhes.</p>
        ";
        // Enviar notificação também para o tutor se o estudante cancelar
        createNotification($appointment['tutor_id'], 'tutor', "A sessão com " . htmlspecialchars($appointment['estudante_nome']) . " para o dia " . date('d/m/Y', strtotime($appointment['data'])) . " foi cancelada.");
    }

    if (!empty($notificationMessage)) {
        createNotification($appointment['estudante_id'], 'estudante', $notificationMessage);
    }

    if (!empty($subject) && !empty($message)) {
        return sendEmailNotification($appointment['estudante_email'], $subject, $message);
    } else {
        return false; // Não há email para enviar se o status não for 'confirmado', 'recusado' ou 'cancelado'
    }
}

// Função para buscar notificações de um usuário
function getUserNotifications($userId, $userType, $limit = 10, $offset = 0) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            SELECT id, message, is_read, created_at
            FROM notifications
            WHERE user_id = ? AND user_type = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userType, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erro ao buscar notificações: " . $e->getMessage());
        return [];
    }
}

// Função para marcar notificação como lida
function markNotificationAsRead($notificationId, $userId, $userType) {
    global $conn;
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ? AND user_type = ?");
        return $stmt->execute([$notificationId, $userId, $userType]);
    } catch (PDOException $e) {
        error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
        return false;
    }
} 