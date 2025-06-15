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
        SELECT a.*, t.nome as tutor_nome, t.email as tutor_email,
               e.nome as estudante_nome, e.email as estudante_email
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

    // Enviar emails
    sendEmailNotification($appointment['tutor_email'], $tutorSubject, $tutorMessage);
    sendEmailNotification($appointment['estudante_email'], $studentSubject, $studentMessage);

    return true;
}

function notifyAppointmentStatus($appointment_id, $status) {
    global $conn;
    
    // Buscar informações do agendamento
    $stmt = $conn->prepare("
        SELECT a.*, t.nome as tutor_nome, t.email as tutor_email,
               e.nome as estudante_nome, e.email as estudante_email
        FROM agendamentos a
        JOIN tutores t ON a.tutor_id = t.id
        JOIN estudantes e ON a.estudante_id = e.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) return false;

    $statusText = $status === 'confirmado' ? 'confirmada' : 'recusada';
    
    // Email para o estudante
    $subject = "Solicitação de agendamento {$statusText}";
    $message = "
        <h2>Solicitação de agendamento {$statusText}</h2>
        <p>Olá {$appointment['estudante_nome']},</p>
        <p>Sua solicitação de agendamento foi {$statusText}:</p>
        <ul>
            <li><strong>Tutor:</strong> {$appointment['tutor_nome']}</li>
            <li><strong>Data:</strong> " . date('d/m/Y', strtotime($appointment['data'])) . "</li>
            <li><strong>Horário:</strong> {$appointment['horario_inicio']} - {$appointment['horario_termino']}</li>
            <li><strong>Assunto:</strong> {$appointment['assunto']}</li>
            <li><strong>Local:</strong> {$appointment['local']}</li>
        </ul>
    ";

    if ($status === 'recusado') {
        $message .= "<p>O tutor recusou sua solicitação. Por favor, tente agendar em outro horário.</p>";
    } else {
        $message .= "<p>O tutor confirmou sua solicitação. A sessão está agendada!</p>";
    }

    return sendEmailNotification($appointment['estudante_email'], $subject, $message);
} 