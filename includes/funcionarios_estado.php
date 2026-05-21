<?php

function fe_table_exists($conn, $table)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function fe_column_exists($conn, $table, $column)
{
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function fe_estado_trabalho($funcionario)
{
    if (($funcionario['estado'] ?? '') !== 'ativo') {
        return 'inativo';
    }

    $ultimoTipo = $funcionario['ultimo_tipo'] ?? null;

    if (in_array($ultimoTipo, ['entrada', 'fim_pausa'], true)) {
        return 'a_trabalhar';
    }

    if ($ultimoTipo === 'inicio_pausa') {
        return 'em_pausa';
    }

    return 'nao_trabalhar';
}

function fe_estado_label($estado)
{
    $labels = [
        'a_trabalhar' => 'A trabalhar',
        'em_pausa' => 'Em pausa',
        'nao_trabalhar' => 'Não está a trabalhar',
        'inativo' => 'Inativo',
    ];

    return $labels[$estado] ?? $estado;
}

function fe_estado_badge($estado)
{
    $classes = [
        'a_trabalhar' => 'success',
        'em_pausa' => 'warning',
        'nao_trabalhar' => 'secondary',
        'inativo' => 'danger',
    ];

    return $classes[$estado] ?? 'secondary';
}

function fe_movimento_label($tipo)
{
    $labels = [
        'entrada' => 'Entrada',
        'saida' => 'Saída',
        'inicio_pausa' => 'Início de pausa',
        'fim_pausa' => 'Fim de pausa',
    ];

    return $labels[$tipo] ?? 'Sem registo hoje';
}

function fe_carregar_funcionarios_estado($conn)
{
    if (!fe_table_exists($conn, 'funcionarios')) {
        return [
            'missing_tables' => ['funcionarios'],
            'funcionarios' => [],
            'totais' => fe_totais_vazios(),
        ];
    }

    $hasEquipas = fe_table_exists($conn, 'equipas') && fe_column_exists($conn, 'funcionarios', 'equipa_id');
    $hasRegistos = fe_table_exists($conn, 'registos_ponto');
    $hasFuncionarioRegisto = $hasRegistos && fe_column_exists($conn, 'registos_ponto', 'funcionario_id');
    $hasUtilizadorLigacao = $hasRegistos
        && fe_column_exists($conn, 'funcionarios', 'utilizador_id')
        && fe_column_exists($conn, 'registos_ponto', 'utilizador_id');

    $selectEquipa = $hasEquipas ? 'eq.nome AS equipa_nome' : 'NULL AS equipa_nome';
    $joinEquipa = $hasEquipas ? ' LEFT JOIN equipas eq ON eq.id = f.equipa_id' : '';
    $ultimoTipo = 'NULL AS ultimo_tipo';
    $ultimoData = 'NULL AS ultimo_data_hora';

    if ($hasRegistos && ($hasFuncionarioRegisto || $hasUtilizadorLigacao)) {
        $condicoes = [];

        if ($hasFuncionarioRegisto) {
            $condicoes[] = 'rp.funcionario_id = f.id';
        }

        if ($hasUtilizadorLigacao) {
            $condicoes[] = '(f.utilizador_id IS NOT NULL AND rp.utilizador_id = f.utilizador_id)';
        }

        $whereLigacao = implode(' OR ', $condicoes);
        $ultimoTipo = "(SELECT rp.tipo
            FROM registos_ponto rp
            WHERE rp.estado IN ('valido', 'corrigido')
              AND DATE(rp.data_hora) = CURDATE()
              AND ($whereLigacao)
            ORDER BY rp.data_hora DESC, rp.id DESC
            LIMIT 1) AS ultimo_tipo";
        $ultimoData = "(SELECT rp.data_hora
            FROM registos_ponto rp
            WHERE rp.estado IN ('valido', 'corrigido')
              AND DATE(rp.data_hora) = CURDATE()
              AND ($whereLigacao)
            ORDER BY rp.data_hora DESC, rp.id DESC
            LIMIT 1) AS ultimo_data_hora";
    }

    $sql = "SELECT
            f.id,
            f.numero_mecanografico,
            f.nome,
            f.email,
            f.telefone,
            f.funcao,
            f.estado,
            $selectEquipa,
            $ultimoTipo,
            $ultimoData
        FROM funcionarios f
        $joinEquipa
        ORDER BY f.estado = 'ativo' DESC, f.nome ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $funcionarios = [];
    $totais = fe_totais_vazios();

    while ($row = mysqli_fetch_assoc($result)) {
        $row['estado_trabalho'] = fe_estado_trabalho($row);
        $funcionarios[] = $row;

        if ($row['estado'] === 'ativo') {
            $totais['ativos']++;
        }

        if (isset($totais[$row['estado_trabalho']])) {
            $totais[$row['estado_trabalho']]++;
        }
    }

    mysqli_stmt_close($stmt);
    $totais['total'] = count($funcionarios);

    return [
        'missing_tables' => [],
        'funcionarios' => $funcionarios,
        'totais' => $totais,
    ];
}

function fe_totais_vazios()
{
    return [
        'total' => 0,
        'ativos' => 0,
        'a_trabalhar' => 0,
        'em_pausa' => 0,
        'nao_trabalhar' => 0,
        'inativo' => 0,
    ];
}
