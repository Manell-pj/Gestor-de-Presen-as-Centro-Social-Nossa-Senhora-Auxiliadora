<?php

function ht_formatar_minutos($minutos)
{
    $minutos = max(0, (int) $minutos);
    return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
}

function ht_minutos_entre($inicio, $fim)
{
    return max(0, (int) round((strtotime($fim) - strtotime($inicio)) / 60));
}

function ht_calcular_minutos_movimentos($movimentos)
{
    $total = 0;
    $inicioIntervalo = null;

    foreach ($movimentos as $movimento) {
        $tipo = $movimento['tipo'];
        $dataHora = $movimento['data_hora'];

        if ($tipo === 'entrada' || $tipo === 'fim_pausa') {
            if ($inicioIntervalo === null) {
                $inicioIntervalo = $dataHora;
            }
            continue;
        }

        if (($tipo === 'inicio_pausa' || $tipo === 'saida') && $inicioIntervalo !== null) {
            $total += ht_minutos_entre($inicioIntervalo, $dataHora);
            $inicioIntervalo = null;
        }
    }

    return $total;
}

function ht_periodo_mensal($ano, $mes)
{
    $inicio = sprintf('%04d-%02d-01', $ano, $mes);
    $fim = date('Y-m-t', strtotime($inicio));

    return [$inicio, $fim];
}

function ht_stmt_bind_params($stmt, $types, $params)
{
    if ($types === '') {
        return;
    }

    $refs = [$types];

    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function ht_carregar_funcionarios($conn, $funcionarioId = null)
{
    $funcionarios = [];
    $temEquipas = fe_table_exists($conn, 'equipas') && fe_column_exists($conn, 'funcionarios', 'equipa_id');
    $selectEquipa = $temEquipas ? 'e.nome AS equipa_nome' : 'NULL AS equipa_nome';
    $joinEquipa = $temEquipas ? 'LEFT JOIN equipas e ON e.id = f.equipa_id' : '';

    $sql = "SELECT f.id, f.nome, f.numero_mecanografico, f.email, f.telefone, f.funcao, $selectEquipa
            FROM funcionarios f
            $joinEquipa
            WHERE f.estado <> 'inativo'";

    if ($funcionarioId !== null) {
        $sql .= ' AND f.id = ?';
    }

    $sql .= ' ORDER BY f.nome ASC';
    $stmt = mysqli_prepare($conn, $sql);

    if ($funcionarioId !== null) {
        mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $row['minutos_trabalhados'] = 0;
        $row['dias_trabalhados'] = 0;
        $row['primeira_picagem'] = null;
        $row['ultima_picagem'] = null;
        $row['dias'] = [];
        $funcionarios[(int) $row['id']] = $row;
    }

    mysqli_stmt_close($stmt);

    return $funcionarios;
}

function ht_carregar_horas_trabalhadas($conn, $dataInicio = null, $dataFim = null, $funcionarioId = null)
{
    $funcionarios = ht_carregar_funcionarios($conn, $funcionarioId);

    if (empty($funcionarios) || !fe_table_exists($conn, 'registos_ponto') || !fe_column_exists($conn, 'registos_ponto', 'funcionario_id')) {
        return $funcionarios;
    }

    $temDataReferencia = fe_column_exists($conn, 'registos_ponto', 'data_referencia');
    $dataExpr = $temDataReferencia ? 'COALESCE(data_referencia, DATE(data_hora))' : 'DATE(data_hora)';
    $where = ['estado IN (\'valido\', \'corrigido\')', 'funcionario_id IS NOT NULL'];
    $params = [];
    $types = '';

    if ($funcionarioId !== null) {
        $where[] = 'funcionario_id = ?';
        $params[] = $funcionarioId;
        $types .= 'i';
    }

    if ($dataInicio !== null) {
        $where[] = "$dataExpr >= ?";
        $params[] = $dataInicio;
        $types .= 's';
    }

    if ($dataFim !== null) {
        $where[] = "$dataExpr <= ?";
        $params[] = $dataFim;
        $types .= 's';
    }

    $sql = "SELECT funcionario_id, tipo, data_hora, $dataExpr AS data_registo
            FROM registos_ponto
            WHERE " . implode(' AND ', $where) . "
            ORDER BY funcionario_id ASC, data_registo ASC, data_hora ASC, id ASC";
    $stmt = mysqli_prepare($conn, $sql);

    ht_stmt_bind_params($stmt, $types, $params);

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $registosPorDia = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $fid = (int) $row['funcionario_id'];

        if (!isset($funcionarios[$fid])) {
            continue;
        }

        $registosPorDia[$fid][$row['data_registo']][] = $row;

        if ($funcionarios[$fid]['primeira_picagem'] === null || $row['data_hora'] < $funcionarios[$fid]['primeira_picagem']) {
            $funcionarios[$fid]['primeira_picagem'] = $row['data_hora'];
        }

        if ($funcionarios[$fid]['ultima_picagem'] === null || $row['data_hora'] > $funcionarios[$fid]['ultima_picagem']) {
            $funcionarios[$fid]['ultima_picagem'] = $row['data_hora'];
        }
    }

    mysqli_stmt_close($stmt);

    foreach ($registosPorDia as $fid => $dias) {
        foreach ($dias as $data => $movimentos) {
            $minutos = ht_calcular_minutos_movimentos($movimentos);
            $funcionarios[$fid]['dias'][$data] = [
                'data' => $data,
                'minutos_trabalhados' => $minutos,
                'picagens' => count($movimentos),
                'primeira_picagem' => $movimentos[0]['data_hora'] ?? null,
                'ultima_picagem' => $movimentos[count($movimentos) - 1]['data_hora'] ?? null,
            ];

            if ($minutos > 0) {
                $funcionarios[$fid]['minutos_trabalhados'] += $minutos;
                $funcionarios[$fid]['dias_trabalhados']++;
            }
        }
    }

    return $funcionarios;
}
