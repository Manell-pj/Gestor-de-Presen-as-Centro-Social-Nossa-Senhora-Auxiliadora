<?php

function escala_mensal_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function escala_mensal_redirect($type, $message, $params = [])
{
    $params = array_merge($params, [
        'type' => $type,
        'message' => $message,
    ]);

    header('Location: escala_mensal.php?' . http_build_query($params));
    exit;
}

function escala_mensal_table_exists($conn, $table)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function escala_mensal_month_name($month)
{
    $months = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Marco',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    return $months[(int) $month] ?? '';
}

function escala_mensal_weekday_short($date)
{
    $weekdays = [
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
        6 => 'Sab',
        7 => 'Dom',
    ];

    return $weekdays[(int) date('N', strtotime($date))] ?? '';
}

function escala_mensal_tipo_label($tipo)
{
    $labels = [
        'turno' => 'Turno',
        'folga' => 'Folga',
        'ferias' => 'Férias',
        'falta' => 'Falta',
        'baixa' => 'Baixa',
        'substituicao' => 'Substituicao',
        'licenca_amamentacao' => 'Lic. amamentação',
    ];

    return $labels[$tipo] ?? $tipo;
}

if (!function_exists('e')) {
    function e($value)
    {
        return escala_mensal_e($value);
    }
}

if (!function_exists('month_name')) {
    function month_name($month)
    {
        return escala_mensal_month_name($month);
    }
}

if (!function_exists('weekday_short')) {
    function weekday_short($date)
    {
        return escala_mensal_weekday_short($date);
    }
}

if (!function_exists('tipo_label')) {
    function tipo_label($tipo)
    {
        return escala_mensal_tipo_label($tipo);
    }
}

function escala_mensal_tipos_dia()
{
    return ['turno', 'folga', 'ferias', 'falta', 'baixa', 'substituicao', 'licenca_amamentacao'];
}

function escala_mensal_contexto_request()
{
    $anoAtual = (int) date('Y');
    $mesAtual = (int) date('n');
    $ano = (int) ($_REQUEST['ano'] ?? $anoAtual);
    $mes = (int) ($_REQUEST['mes'] ?? $mesAtual);
    $equipaId = (int) ($_REQUEST['equipa_id'] ?? 0);

    if ($ano < 2000 || $ano > 2100) {
        $ano = $anoAtual;
    }

    if ($mes < 1 || $mes > 12) {
        $mes = $mesAtual;
    }

    return [
        'ano' => $ano,
        'mes' => $mes,
        'setor_id' => 0,
        'equipa_id' => $equipaId,
        'dias_no_mes' => cal_days_in_month(CAL_GREGORIAN, $mes, $ano),
    ];
}

function escala_mensal_base_params($contexto)
{
    return [
        'ano' => $contexto['ano'],
        'mes' => $contexto['mes'],
        'equipa_id' => $contexto['equipa_id'],
    ];
}

function escala_mensal_tabelas_em_falta($conn)
{
    $requiredTables = ['funcionarios', 'equipas', 'turnos', 'escala_funcionarios'];
    $missingTables = [];

    foreach ($requiredTables as $table) {
        if (!escala_mensal_table_exists($conn, $table)) {
            $missingTables[] = $table;
        }
    }

    return $missingTables;
}

function escala_mensal_processar_post($conn, $contexto, $missingTables)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['acao'] ?? '') !== 'guardar') {
        return;
    }

    $baseParams = escala_mensal_base_params($contexto);

    if (!empty($missingTables)) {
        escala_mensal_redirect('danger', 'Execute primeiro a migration SQL da adaptacao para lar de idosos.', $baseParams);
    }

    $escala = $_POST['escala'] ?? [];

    if (!is_array($escala)) {
        escala_mensal_redirect('danger', 'Dados da escala inválidos.', $baseParams);
    }

    mysqli_begin_transaction($conn);

    try {
        $stmtFuncionario = mysqli_prepare($conn, 'SELECT id, utilizador_id, equipa_id FROM funcionarios WHERE id = ? AND estado = "ativo" LIMIT 1');
        $stmtGuardar = mysqli_prepare($conn, "INSERT INTO escala_funcionarios
            (funcionario_id, utilizador_id, setor_id, equipa_id, ano, mes, data_escala, dia, tipo_dia, turno_id, substitui_funcionario_id, folga_trabalhada, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                utilizador_id = VALUES(utilizador_id),
                setor_id = VALUES(setor_id),
                equipa_id = VALUES(equipa_id),
                ano = VALUES(ano),
                mes = VALUES(mes),
                dia = VALUES(dia),
                tipo_dia = VALUES(tipo_dia),
                turno_id = VALUES(turno_id),
                substitui_funcionario_id = VALUES(substitui_funcionario_id),
                folga_trabalhada = VALUES(folga_trabalhada),
                observacoes = VALUES(observacoes)");

        foreach ($escala as $funcionarioId => $dias) {
            escala_mensal_guardar_funcionario($conn, $stmtFuncionario, $stmtGuardar, $contexto, (int) $funcionarioId, $dias);
        }

        mysqli_stmt_close($stmtFuncionario);
        mysqli_stmt_close($stmtGuardar);
        mysqli_commit($conn);

        escala_mensal_redirect('success', 'Escala mensal guardada com sucesso.', $baseParams);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        escala_mensal_redirect('danger', 'Não foi possível guardar a escala mensal.', $baseParams);
    }
}

function escala_mensal_guardar_funcionario($conn, $stmtFuncionario, $stmtGuardar, $contexto, $funcionarioId, $dias)
{
    if ($funcionarioId <= 0 || !is_array($dias)) {
        return;
    }

    mysqli_stmt_bind_param($stmtFuncionario, 'i', $funcionarioId);
    mysqli_stmt_execute($stmtFuncionario);
    $resultFuncionario = mysqli_stmt_get_result($stmtFuncionario);
    $funcionario = mysqli_fetch_assoc($resultFuncionario);

    if (!$funcionario) {
        return;
    }

    foreach ($dias as $dia => $dadosDia) {
        escala_mensal_guardar_dia($stmtGuardar, $contexto, $funcionarioId, $funcionario, (int) $dia, $dadosDia);
    }
}

function escala_mensal_guardar_dia($stmtGuardar, $contexto, $funcionarioId, $funcionario, $dia, $dadosDia)
{
    if ($dia < 1 || $dia > $contexto['dias_no_mes'] || !is_array($dadosDia)) {
        return;
    }

    $tiposDia = escala_mensal_tipos_dia();
    $tipoDia = $dadosDia['tipo_dia'] ?? 'turno';

    if (!in_array($tipoDia, $tiposDia, true)) {
        $tipoDia = 'turno';
    }

    $turnoId = isset($dadosDia['turno_id']) && (int) $dadosDia['turno_id'] > 0 ? (int) $dadosDia['turno_id'] : null;
    $substituiFuncionarioId = isset($dadosDia['substitui_funcionario_id']) && (int) $dadosDia['substitui_funcionario_id'] > 0 ? (int) $dadosDia['substitui_funcionario_id'] : null;
    $folgaTrabalhada = isset($dadosDia['folga_trabalhada']) ? 1 : 0;
    $observacoes = trim($dadosDia['observacoes'] ?? '');
    $observacoes = $observacoes === '' ? null : $observacoes;
    $dataEscala = sprintf('%04d-%02d-%02d', $contexto['ano'], $contexto['mes'], $dia);
    $utilizadorId = $funcionario['utilizador_id'] === null ? null : (int) $funcionario['utilizador_id'];
    $setorFuncionarioId = null;
    $equipaFuncionarioId = $funcionario['equipa_id'] === null ? null : (int) $funcionario['equipa_id'];

    if ($tipoDia !== 'turno' && $tipoDia !== 'substituicao' && $folgaTrabalhada === 0) {
        $turnoId = null;
    }

    if ($tipoDia !== 'substituicao') {
        $substituiFuncionarioId = null;
    }

    mysqli_stmt_bind_param(
        $stmtGuardar,
        'iiiiiisisiiis',
        $funcionarioId,
        $utilizadorId,
        $setorFuncionarioId,
        $equipaFuncionarioId,
        $contexto['ano'],
        $contexto['mes'],
        $dataEscala,
        $dia,
        $tipoDia,
        $turnoId,
        $substituiFuncionarioId,
        $folgaTrabalhada,
        $observacoes
    );
    mysqli_stmt_execute($stmtGuardar);
}

function escala_mensal_carregar_dados($conn, $contexto, $missingTables)
{
    $dados = [
        'equipas' => [],
        'turnos' => [],
        'funcionarios' => [],
        'escala_guardada' => [],
    ];

    if (!empty($missingTables)) {
        return $dados;
    }

    $dados['equipas'] = escala_mensal_carregar_equipas($conn);
    $dados['turnos'] = escala_mensal_carregar_turnos($conn);
    $dados['funcionarios'] = escala_mensal_carregar_funcionarios($conn, $contexto['equipa_id']);
    $dados['escala_guardada'] = escala_mensal_carregar_escala_guardada($conn, $contexto['ano'], $contexto['mes']);

    return $dados;
}

function escala_mensal_carregar_equipas($conn)
{
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, nome FROM equipas WHERE ativo = 1 ORDER BY nome ASC');

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_turnos($conn)
{
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, nome, codigo, hora_entrada, hora_saida FROM turnos WHERE ativo = 1 ORDER BY hora_entrada ASC, nome ASC');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_funcionarios($conn, $equipaId)
{
    $rows = [];
    $sql = "SELECT f.id, f.utilizador_id, f.nome, f.numero_mecanografico, f.funcao, f.equipa_id,
                   e.nome AS equipa_nome
            FROM funcionarios f
            LEFT JOIN equipas e ON e.id = f.equipa_id
            WHERE f.estado = 'ativo'";

    if ($equipaId > 0) {
        $sql .= ' AND f.equipa_id = ?';
    }

    $sql .= ' ORDER BY e.nome ASC, f.nome ASC';
    $stmt = mysqli_prepare($conn, $sql);

    if ($equipaId > 0) {
        mysqli_stmt_bind_param($stmt, 'i', $equipaId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function escala_mensal_carregar_escala_guardada($conn, $ano, $mes)
{
    $escala = [];
    $stmt = mysqli_prepare($conn, 'SELECT * FROM escala_funcionarios WHERE ano = ? AND mes = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $ano, $mes);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $escala[(int) $row['funcionario_id']][(int) $row['dia']] = $row;
    }

    mysqli_stmt_close($stmt);
    return $escala;
}
