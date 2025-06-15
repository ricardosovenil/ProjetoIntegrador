<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sessao_id'])) {
    $sessao_id = filter_input(INPUT_POST, 'sessao_id', FILTER_VALIDATE_INT);
    
    if ($sessao_id) {
        // Verifica se o usuário tem permissão para cancelar a sessão
        $sql = "SELECT * FROM agendamentos WHERE id = ? AND status = 'pendente' AND ";
        $sql .= $user_type === 'estudante' ? "estudante_id = ?" : "tutor_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$sessao_id, $user_id]);
        $sessao = $stmt->fetch();
        
        if ($sessao) {
            // Atualiza o status da sessão para cancelado
            $sql = "UPDATE agendamentos SET status = 'cancelado' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$sessao_id])) {
                $mensagem = "Sessão cancelada com sucesso!";
            } else {
                $mensagem = "Erro ao cancelar a sessão. Tente novamente.";
            }
        } else {
            $mensagem = "Sessão não encontrada ou você não tem permissão para cancelá-la.";
        }
    }
}

// Redireciona de volta para o histórico
header('Location: historico_sessoes.php');
exit(); 