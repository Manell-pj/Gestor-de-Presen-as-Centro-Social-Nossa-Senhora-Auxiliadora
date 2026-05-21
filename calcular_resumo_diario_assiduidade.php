<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/funcoes/calcular_resumo_diario_assiduidade.php';

$utilizadorSessao = require_login($conn);

$data = $_GET['data'] ?? date('Y-m-d');
$funcionarioId = isset($_GET['funcionario_id']) && (int) $_GET['funcionario_id'] > 0 ? (int) $_GET['funcionario_id'] : null;

header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode(calcular_resumo_diario_assiduidade($conn, $data, $funcionarioId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'erro' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
