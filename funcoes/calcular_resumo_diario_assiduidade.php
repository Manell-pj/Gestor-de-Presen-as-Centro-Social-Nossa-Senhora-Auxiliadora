<?php
/**
 * Calcula e grava o resumo diario de assiduidade.
 *
 * Uso a partir de outro ficheiro:
 * require_once 'config.php';
 * require_once 'funcoes/calcular_resumo_diario_assiduidade.php';
 * $resultado = calcular_resumo_diario_assiduidade($conn, '2026-05-15');
 */

function assiduidade_validar_data($data)
{
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    return $dt && $dt->format('Y-m-d') === $data;
}

function assiduidade_datetime($data, $hora, $adicionarDia = false)
{
    $dt = new DateTime($data . ' ' . $hora);

    if ($adicionarDia) {
        $dt->modify('+1 day');
    }

    return $dt;
}

function assiduidade_minutos_entre($inicio, $fim)
{
    if (!$inicio || !$fim) {
        return 0;
    }

    return max(0, (int) floor(($fim->getTimestamp() - $inicio->getTimestamp()) / 60));
}

function assiduidade_dt_sql($dt)
{
    return $dt instanceof DateTime ? $dt->format('Y-m-d H:i:s') : null;
}

function assiduidade_obter_turno_periodos($conn, $turnoId)
{
    if (!$turnoId) {
        return [];
    }

    $periodos = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, sequencia, hora_inicio, hora_fim, cruza_dia, tolerancia_antes_min, tolerancia_depois_min, minutos_previstos FROM turno_periodos WHERE turno_id = ? AND ativo = 1 ORDER BY sequencia ASC');
    mysqli_stmt_bind_param($stmt, 'i', $turnoId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $periodos[] = $row;
    }

    mysqli_stmt_close($stmt);

    if (!empty($periodos)) {
        return $periodos;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, hora_entrada, hora_saida, tolerancia_entrada_min, tolerancia_saida_min, horas_previstas FROM turnos WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $turnoId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $turno = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$turno) {
        return [];
    }

    return [[
        'id' => null,
        'sequencia' => 1,
        'hora_inicio' => $turno['hora_entrada'],
        'hora_fim' => $turno['hora_saida'],
        'cruza_dia' => $turno['hora_saida'] <= $turno['hora_entrada'] ? 1 : 0,
        'tolerancia_antes_min' => $turno['tolerancia_entrada_min'] ?? 15,
        'tolerancia_depois_min' => $turno['tolerancia_saida_min'] ?? 15,
        'minutos_previstos' => (int) round(((float) $turno['horas_previstas']) * 60),
    ]];
}

function assiduidade_calcular_previsto($data, $periodos)
{
    $entradaPrevista = null;
    $saidaPrevista = null;
    $minutosPrevistos = 0;

    foreach ($periodos as $periodo) {
        $horaInicio = $periodo['hora_inicio'];
        $horaFim = $periodo['hora_fim'];
        $cruzaDia = (int) ($periodo['cruza_dia'] ?? 0) === 1 || $horaFim <= $horaInicio;
        $inicio = assiduidade_datetime($data, $horaInicio, false);
        $fim = assiduidade_datetime($data, $horaFim, $cruzaDia);

        if (!$entradaPrevista || $inicio < $entradaPrevista) {
            $entradaPrevista = $inicio;
        }

        if (!$saidaPrevista || $fim > $saidaPrevista) {
            $saidaPrevista = $fim;
        }

        $minutos = (int) ($periodo['minutos_previstos'] ?? 0);
        $minutosPrevistos += $minutos > 0 ? $minutos : assiduidade_minutos_entre($inicio, $fim);
    }

    return [
        'entrada_prevista' => $entradaPrevista,
        'saida_prevista' => $saidaPrevista,
        'minutos_previstos' => $minutosPrevistos,
    ];
}

function assiduidade_obter_registos_ponto($conn, $funcionarioId, $utilizadorId, $data, $saidaPrevista = null)
{
    $inicioJanela = $data . ' 00:00:00';
    $fimJanelaDt = new DateTime($data . ' 23:59:59');

    if ($saidaPrevista instanceof DateTime && $saidaPrevista->format('Y-m-d') !== $data) {
        $fimJanelaDt = clone $saidaPrevista;
        $fimJanelaDt->modify('+4 hours');
    }

    $fimJanela = $fimJanelaDt->format('Y-m-d H:i:s');
    $registos = [];

    $sql = "SELECT id, tipo, data_hora
            FROM registos_ponto
            WHERE estado IN ('valido', 'corrigido')
              AND (
                    (funcionario_id = ?)
                    OR (? IS NOT NULL AND utilizador_id = ?)
              )
              AND (
                    data_referencia = ?
                    OR (data_referencia IS NULL AND data_hora BETWEEN ? AND ?)
              )
            ORDER BY data_hora ASC, id ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iiisss', $funcionarioId, $utilizadorId, $utilizadorId, $data, $inicioJanela, $fimJanela);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $registos[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $registos;
}

function assiduidade_calcular_realizado($registos)
{
    $entradaReal = null;
    $saidaReal = null;
    $inicioIntervalo = null;
    $minutosTrabalhados = 0;

    foreach ($registos as $registo) {
        $momento = new DateTime($registo['data_hora']);
        $tipo = $registo['tipo'];

        if (!$entradaReal && ($tipo === 'entrada' || $tipo === 'fim_pausa')) {
            $entradaReal = clone $momento;
        }

        if ($tipo === 'entrada') {
            if (!$inicioIntervalo) {
                $inicioIntervalo = clone $momento;
            }
            continue;
        }

        if ($tipo === 'inicio_pausa') {
            if ($inicioIntervalo) {
                $minutosTrabalhados += assiduidade_minutos_entre($inicioIntervalo, $momento);
                $inicioIntervalo = null;
            }
            continue;
        }

        if ($tipo === 'fim_pausa') {
            if (!$inicioIntervalo) {
                $inicioIntervalo = clone $momento;
            }
            continue;
        }

        if ($tipo === 'saida') {
            $saidaReal = clone $momento;

            if ($inicioIntervalo) {
                $minutosTrabalhados += assiduidade_minutos_entre($inicioIntervalo, $momento);
                $inicioIntervalo = null;
            }
        }
    }

    if (!$entradaReal && !empty($registos)) {
        $entradaReal = new DateTime($registos[0]['data_hora']);
    }

    if (!$saidaReal && count($registos) > 1) {
        $ultimo = end($registos);
        $saidaReal = new DateTime($ultimo['data_hora']);
    }

    return [
        'entrada_real' => $entradaReal,
        'saida_real' => $saidaReal,
        'minutos_trabalhados' => $minutosTrabalhados,
    ];
}

function assiduidade_estado_resumo($tipoDia, $temRegistos, $minutosPrevistos, $minutosTrabalhados)
{
    if ($tipoDia === 'ferias') {
        return 'ferias';
    }

    if ($tipoDia === 'folga' && !$temRegistos) {
        return 'folga';
    }

    if (in_array($tipoDia, ['falta', 'baixa', 'licenca_amamentacao'], true) && !$temRegistos) {
        return 'ausente';
    }

    if ($minutosPrevistos > 0 && !$temRegistos) {
        return 'ausente';
    }

    if ($temRegistos && $minutosTrabalhados <= 0) {
        return 'incompleto';
    }

    if ($temRegistos) {
        return 'presente';
    }

    return 'previsto';
}

function assiduidade_guardar_resumo($conn, $resumo)
{
    $sql = "INSERT INTO resumo_diario_assiduidade
            (funcionario_id, utilizador_id, setor_id, equipa_id, data, turno_id,
             minutos_previstos, minutos_trabalhados, horas_previstas, horas_realizadas, minutos_ausencia_justificada,
             minutos_atraso, minutos_saida_antecipada, minutos_extra, minutos_saldo,
             dentro_tolerancia, estado, entrada_prevista, saida_prevista, entrada_real, saida_real,
             falta, folga_trabalhada, substituicao, substitui_funcionario_id, licenca_amamentacao,
             primeira_entrada, ultima_saida, observacoes, calculado_at)
            VALUES (?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                utilizador_id = VALUES(utilizador_id),
                setor_id = VALUES(setor_id),
                equipa_id = VALUES(equipa_id),
                turno_id = VALUES(turno_id),
                minutos_previstos = VALUES(minutos_previstos),
                minutos_trabalhados = VALUES(minutos_trabalhados),
                horas_previstas = VALUES(horas_previstas),
                horas_realizadas = VALUES(horas_realizadas),
                minutos_ausencia_justificada = VALUES(minutos_ausencia_justificada),
                minutos_atraso = VALUES(minutos_atraso),
                minutos_saida_antecipada = VALUES(minutos_saida_antecipada),
                minutos_extra = VALUES(minutos_extra),
                minutos_saldo = VALUES(minutos_saldo),
                dentro_tolerancia = VALUES(dentro_tolerancia),
                estado = VALUES(estado),
                entrada_prevista = VALUES(entrada_prevista),
                saida_prevista = VALUES(saida_prevista),
                entrada_real = VALUES(entrada_real),
                saida_real = VALUES(saida_real),
                falta = VALUES(falta),
                folga_trabalhada = VALUES(folga_trabalhada),
                substituicao = VALUES(substituicao),
                substitui_funcionario_id = VALUES(substitui_funcionario_id),
                licenca_amamentacao = VALUES(licenca_amamentacao),
                primeira_entrada = VALUES(primeira_entrada),
                ultima_saida = VALUES(ultima_saida),
                observacoes = VALUES(observacoes),
                calculado_at = NOW()";
    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        'iiiisiiiddiiiiiisssssiiiiisss',
        $resumo['funcionario_id'],
        $resumo['utilizador_id'],
        $resumo['setor_id'],
        $resumo['equipa_id'],
        $resumo['data'],
        $resumo['turno_id'],
        $resumo['minutos_previstos'],
        $resumo['minutos_trabalhados'],
        $resumo['horas_previstas'],
        $resumo['horas_realizadas'],
        $resumo['minutos_ausencia_justificada'],
        $resumo['minutos_atraso'],
        $resumo['minutos_saida_antecipada'],
        $resumo['minutos_extra'],
        $resumo['minutos_saldo'],
        $resumo['dentro_tolerancia'],
        $resumo['estado'],
        $resumo['entrada_prevista'],
        $resumo['saida_prevista'],
        $resumo['entrada_real'],
        $resumo['saida_real'],
        $resumo['falta'],
        $resumo['folga_trabalhada'],
        $resumo['substituicao'],
        $resumo['substitui_funcionario_id'],
        $resumo['licenca_amamentacao'],
        $resumo['primeira_entrada'],
        $resumo['ultima_saida'],
        $resumo['observacoes']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function calcular_resumo_diario_assiduidade($conn, $data, $funcionarioId = null)
{
    if (!assiduidade_validar_data($data)) {
        throw new InvalidArgumentException('Data inválida. Use o formato YYYY-MM-DD.');
    }

    $sql = "SELECT ef.*, f.utilizador_id, f.setor_id AS funcionario_setor_id, f.equipa_id AS funcionario_equipa_id
            FROM escala_funcionarios ef
            INNER JOIN funcionarios f ON f.id = ef.funcionario_id
            WHERE ef.data_escala = ?";

    if ($funcionarioId !== null) {
        $sql .= ' AND ef.funcionario_id = ?';
    }

    $sql .= ' ORDER BY ef.funcionario_id ASC';
    $stmt = mysqli_prepare($conn, $sql);

    if ($funcionarioId !== null) {
        $funcionarioId = (int) $funcionarioId;
        mysqli_stmt_bind_param($stmt, 'si', $data, $funcionarioId);
    } else {
        mysqli_stmt_bind_param($stmt, 's', $data);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $linhas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $linhas[] = $row;
    }
    mysqli_stmt_close($stmt);

    $total = 0;
    $erros = [];

    mysqli_begin_transaction($conn);

    try {
        foreach ($linhas as $linha) {
            $tipoDia = $linha['tipo_dia'];
            $turnoId = $linha['turno_id'] ? (int) $linha['turno_id'] : null;
            $folgaTrabalhada = (int) $linha['folga_trabalhada'] === 1;
            $substituicao = $tipoDia === 'substituicao';
            $licencaAmamentacao = $tipoDia === 'licenca_amamentacao';
            $deveCompararTurno = $turnoId && ($tipoDia === 'turno' || $tipoDia === 'substituicao' || $folgaTrabalhada);

            $periodos = $deveCompararTurno ? assiduidade_obter_turno_periodos($conn, $turnoId) : [];
            $previsto = assiduidade_calcular_previsto($data, $periodos);
            $registos = assiduidade_obter_registos_ponto(
                $conn,
                (int) $linha['funcionario_id'],
                $linha['utilizador_id'] === null ? null : (int) $linha['utilizador_id'],
                $data,
                $previsto['saida_prevista']
            );
            $realizado = assiduidade_calcular_realizado($registos);

            $temRegistos = !empty($registos);
            $minutosPrevistos = $previsto['minutos_previstos'];
            $minutosTrabalhados = $realizado['minutos_trabalhados'];
            $minutosAtraso = 0;
            $minutosSaidaAntecipada = 0;
            $toleranciaMin = 15;

            if ($previsto['entrada_prevista'] && $realizado['entrada_real']) {
                $desvioEntrada = assiduidade_minutos_entre($previsto['entrada_prevista'], $realizado['entrada_real']);
                $minutosAtraso = max(0, $desvioEntrada - $toleranciaMin);
            }

            if ($previsto['saida_prevista'] && $realizado['saida_real'] && $realizado['saida_real'] < $previsto['saida_prevista']) {
                $desvioSaida = assiduidade_minutos_entre($realizado['saida_real'], $previsto['saida_prevista']);
                $minutosSaidaAntecipada = max(0, $desvioSaida - $toleranciaMin);
            }

            $minutosExtra = max(0, $minutosTrabalhados - $minutosPrevistos);
            $minutosSaldo = $minutosTrabalhados - $minutosPrevistos;
            $horasPrevistas = round($minutosPrevistos / 60, 2);
            $horasRealizadas = round($minutosTrabalhados / 60, 2);
            $falta = ($tipoDia === 'falta') || ($minutosPrevistos > 0 && !$temRegistos && !in_array($tipoDia, ['ferias', 'baixa', 'licenca_amamentacao'], true));
            $minutosAusenciaJustificada = in_array($tipoDia, ['ferias', 'baixa', 'licenca_amamentacao'], true) ? $minutosPrevistos : 0;
            $dentroTolerancia = ($minutosAtraso === 0 && $minutosSaidaAntecipada === 0) ? 1 : 0;
            $estado = assiduidade_estado_resumo($tipoDia, $temRegistos, $minutosPrevistos, $minutosTrabalhados);

            if ($folgaTrabalhada && $temRegistos) {
                $estado = 'presente';
            }

            $observacoes = [];

            if ($folgaTrabalhada) {
                $observacoes[] = 'Folga trabalhada';
            }

            if ($substituicao && $linha['substitui_funcionario_id']) {
                $observacoes[] = 'Substituição registada';
            }

            if ($licencaAmamentacao) {
                $observacoes[] = 'Licença de amamentação';
            }

            if ($linha['observacoes']) {
                $observacoes[] = $linha['observacoes'];
            }

            assiduidade_guardar_resumo($conn, [
                'funcionario_id' => (int) $linha['funcionario_id'],
                'utilizador_id' => $linha['utilizador_id'] === null ? null : (int) $linha['utilizador_id'],
                'setor_id' => $linha['setor_id'] === null ? ($linha['funcionario_setor_id'] === null ? null : (int) $linha['funcionario_setor_id']) : (int) $linha['setor_id'],
                'equipa_id' => $linha['equipa_id'] === null ? ($linha['funcionario_equipa_id'] === null ? null : (int) $linha['funcionario_equipa_id']) : (int) $linha['equipa_id'],
                'data' => $data,
                'turno_id' => $turnoId,
                'minutos_previstos' => $minutosPrevistos,
                'minutos_trabalhados' => $minutosTrabalhados,
                'horas_previstas' => $horasPrevistas,
                'horas_realizadas' => $horasRealizadas,
                'minutos_ausencia_justificada' => $minutosAusenciaJustificada,
                'minutos_atraso' => $minutosAtraso,
                'minutos_saida_antecipada' => $minutosSaidaAntecipada,
                'minutos_extra' => $minutosExtra,
                'minutos_saldo' => $minutosSaldo,
                'dentro_tolerancia' => $dentroTolerancia,
                'estado' => $estado,
                'entrada_prevista' => assiduidade_dt_sql($previsto['entrada_prevista']),
                'saida_prevista' => assiduidade_dt_sql($previsto['saida_prevista']),
                'entrada_real' => assiduidade_dt_sql($realizado['entrada_real']),
                'saida_real' => assiduidade_dt_sql($realizado['saida_real']),
                'falta' => $falta ? 1 : 0,
                'folga_trabalhada' => $folgaTrabalhada ? 1 : 0,
                'substituicao' => $substituicao ? 1 : 0,
                'substitui_funcionario_id' => $linha['substitui_funcionario_id'] === null ? null : (int) $linha['substitui_funcionario_id'],
                'licenca_amamentacao' => $licencaAmamentacao ? 1 : 0,
                'primeira_entrada' => assiduidade_dt_sql($realizado['entrada_real']),
                'ultima_saida' => assiduidade_dt_sql($realizado['saida_real']),
                'observacoes' => empty($observacoes) ? null : implode(' | ', $observacoes),
            ]);

            $total++;
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $erros[] = $e->getMessage();
    }

    return [
        'data' => $data,
        'funcionario_id' => $funcionarioId,
        'processados' => $total,
        'erros' => $erros,
    ];
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../config.php';

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
}
