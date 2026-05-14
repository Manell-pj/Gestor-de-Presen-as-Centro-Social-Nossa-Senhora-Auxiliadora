<?php
require_once 'config.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: turnos.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function get_post_value($key)
{
    return trim($_POST[$key] ?? '');
}

function nullable_time($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function nullable_int($value)
{
    return $value === '' ? null : (int) $value;
}

function dia_semana_nome($dia)
{
    $dias = [
        1 => 'Segunda-feira',
        2 => 'Terca-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    return $dias[(int) $dia] ?? 'Todos os dias';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $codigo = get_post_value('codigo') ?: null;
        $horaEntrada = get_post_value('hora_entrada');
        $horaSaida = get_post_value('hora_saida');
        $inicioPausa = nullable_time($_POST['inicio_pausa'] ?? '');
        $fimPausa = nullable_time($_POST['fim_pausa'] ?? '');
        $toleranciaAtraso = (int) ($_POST['tolerancia_entrada_min'] ?? 0);
        $toleranciaSaida = (int) ($_POST['tolerancia_saida_min'] ?? 0);
        $horasPrevistas = (float) ($_POST['horas_previstas'] ?? 8);
        $turnoNoturno = isset($_POST['turno_noturno']) ? 1 : 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '' || $horaEntrada === '' || $horaSaida === '') {
            redirect_with_message('danger', 'Preencha o nome, hora de entrada e hora de saida.');
        }

        if (($inicioPausa === null && $fimPausa !== null) || ($inicioPausa !== null && $fimPausa === null)) {
            redirect_with_message('danger', 'Preencha o inicio e o fim da pausa, ou deixe ambos vazios.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'INSERT INTO turnos (nome, codigo, hora_entrada, hora_saida, inicio_pausa, fim_pausa, tolerancia_entrada_min, tolerancia_saida_min, horas_previstas, turno_noturno, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssssssiidii', $nome, $codigo, $horaEntrada, $horaSaida, $inicioPausa, $fimPausa, $toleranciaAtraso, $toleranciaSaida, $horasPrevistas, $turnoNoturno, $ativo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Turno criado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel criar o turno. Verifique se o codigo ja existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $codigo = get_post_value('codigo') ?: null;
        $horaEntrada = get_post_value('hora_entrada');
        $horaSaida = get_post_value('hora_saida');
        $inicioPausa = nullable_time($_POST['inicio_pausa'] ?? '');
        $fimPausa = nullable_time($_POST['fim_pausa'] ?? '');
        $toleranciaAtraso = (int) ($_POST['tolerancia_entrada_min'] ?? 0);
        $toleranciaSaida = (int) ($_POST['tolerancia_saida_min'] ?? 0);
        $horasPrevistas = (float) ($_POST['horas_previstas'] ?? 8);
        $turnoNoturno = isset($_POST['turno_noturno']) ? 1 : 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($id <= 0 || $nome === '' || $horaEntrada === '' || $horaSaida === '') {
            redirect_with_message('danger', 'Preencha os campos obrigatorios.');
        }

        if (($inicioPausa === null && $fimPausa !== null) || ($inicioPausa !== null && $fimPausa === null)) {
            redirect_with_message('danger', 'Preencha o inicio e o fim da pausa, ou deixe ambos vazios.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE turnos SET nome = ?, codigo = ?, hora_entrada = ?, hora_saida = ?, inicio_pausa = ?, fim_pausa = ?, tolerancia_entrada_min = ?, tolerancia_saida_min = ?, horas_previstas = ?, turno_noturno = ?, ativo = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'ssssssiidiii', $nome, $codigo, $horaEntrada, $horaSaida, $inicioPausa, $fimPausa, $toleranciaAtraso, $toleranciaSaida, $horasPrevistas, $turnoNoturno, $ativo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Turno atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel atualizar o turno. Verifique se o codigo ja existe.');
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Turno invalido.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM horarios_turno WHERE turno_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) $row['total'] > 0) {
            redirect_with_message('danger', 'Nao e possivel remover este turno porque existem utilizadores associados.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM turnos WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Turno removido com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel remover o turno.');
        }
    }

    if ($acao === 'associar') {
        $turnoId = (int) ($_POST['turno_id'] ?? 0);
        $utilizadorId = (int) ($_POST['utilizador_id'] ?? 0);
        $diaSemana = nullable_int($_POST['dia_semana'] ?? '');
        $dataInicio = get_post_value('data_inicio');
        $dataFim = get_post_value('data_fim') ?: null;

        if ($turnoId <= 0 || $utilizadorId <= 0 || $dataInicio === '') {
            redirect_with_message('danger', 'Preencha o utilizador e a data de inicio.');
        }

        if ($diaSemana !== null && ($diaSemana < 1 || $diaSemana > 7)) {
            redirect_with_message('danger', 'Dia da semana invalido.');
        }

        if ($dataFim !== null && $dataFim < $dataInicio) {
            redirect_with_message('danger', 'A data fim nao pode ser anterior a data inicio.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'INSERT INTO horarios_turno (utilizador_id, turno_id, data_inicio, data_fim, dia_semana, ativo) VALUES (?, ?, ?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'iissi', $utilizadorId, $turnoId, $dataInicio, $dataFim, $diaSemana);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Turno associado ao utilizador com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel associar o turno ao utilizador.');
        }
    }

    if ($acao === 'remover_associacao') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Associacao invalida.');
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM horarios_turno WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        redirect_with_message('success', 'Associacao removida com sucesso.');
    }
}

$utilizadores = [];
$stmt = mysqli_prepare($conn, "SELECT id, nome FROM utilizadores WHERE estado = 'ativo' ORDER BY nome ASC");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $utilizadores[] = $row;
}
mysqli_stmt_close($stmt);

$turnos = [];
$sql = "SELECT
            t.*,
            COUNT(ht.id) AS total_associacoes
        FROM turnos t
        LEFT JOIN horarios_turno ht ON ht.turno_id = t.id
        GROUP BY t.id, t.nome, t.codigo, t.hora_entrada, t.hora_saida, t.inicio_pausa, t.fim_pausa,
                 t.tolerancia_entrada_min, t.tolerancia_saida_min, t.horas_previstas, t.turno_noturno,
                 t.ativo, t.created_at, t.updated_at
        ORDER BY t.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $turnos[] = $row;
}
mysqli_stmt_close($stmt);

$associacoes = [];
$sql = "SELECT ht.id, ht.turno_id, ht.data_inicio, ht.data_fim, ht.dia_semana, ht.ativo,
               u.nome AS utilizador_nome
        FROM horarios_turno ht
        INNER JOIN utilizadores u ON u.id = ht.utilizador_id
        ORDER BY ht.data_inicio DESC, u.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $associacoes[(int) $row['turno_id']][] = $row;
}
mysqli_stmt_close($stmt);

$alertType = $_GET['type'] ?? '';
$alertMessage = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt">
<?php include 'includes/head.php'; ?>

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-panel">
            <div class="main-header">
                <?php include 'includes/header.php'; ?>
            </div>

            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">Turnos</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="principal.php">
                                    <i class="icon-home"></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"></i>
                            </li>
                            <li class="nav-item">
                                <a href="turnos.php">Turnos</a>
                            </li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Lista de turnos</h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalCriarTurno">
                                    <i class="fa fa-plus"></i>
                                    Adicionar turno
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-turnos" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Codigo</th>
                                            <th>Nome</th>
                                            <th>Entrada</th>
                                            <th>Saida</th>
                                            <th>Pausa</th>
                                            <th>Tolerancia</th>
                                            <th>Associacoes</th>
                                            <th>Estado</th>
                                            <th style="width: 160px">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($turnos as $turno): ?>
                                            <tr>
                                                <td><?php echo e($turno['codigo'] ?: '-'); ?></td>
                                                <td><?php echo e($turno['nome']); ?></td>
                                                <td><?php echo e(substr($turno['hora_entrada'], 0, 5)); ?></td>
                                                <td><?php echo e(substr($turno['hora_saida'], 0, 5)); ?></td>
                                                <td>
                                                    <?php if ($turno['inicio_pausa'] && $turno['fim_pausa']): ?>
                                                        <?php echo e(substr($turno['inicio_pausa'], 0, 5)); ?> - <?php echo e(substr($turno['fim_pausa'], 0, 5)); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo (int) $turno['tolerancia_entrada_min']; ?> min</td>
                                                <td><?php echo (int) $turno['total_associacoes']; ?></td>
                                                <td>
                                                    <?php if ((int) $turno['ativo'] === 1): ?>
                                                        <span class="badge badge-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#modalAssociarTurno<?php echo (int) $turno['id']; ?>" title="Associar">
                                                            <i class="fa fa-user-plus"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarTurno<?php echo (int) $turno['id']; ?>" title="Editar">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-danger" data-bs-toggle="modal" data-bs-target="#modalRemoverTurno<?php echo (int) $turno['id']; ?>" title="Remover">
                                                            <i class="fa fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <div class="modal fade" id="modalCriarTurno" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar turno</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php include __DIR__ . '/turnos_form_campos.php'; ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($turnos as $turno): ?>
        <div class="modal fade" id="modalEditarTurno<?php echo (int) $turno['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $turno['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar turno</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo e($turno['nome']); ?>" required>
                                <div class="invalid-feedback">Indique o nome do turno.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Codigo</label>
                                <input type="text" name="codigo" class="form-control" value="<?php echo e($turno['codigo']); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Hora de entrada *</label>
                                <input type="time" name="hora_entrada" class="form-control" value="<?php echo e(substr($turno['hora_entrada'], 0, 5)); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Hora de saida *</label>
                                <input type="time" name="hora_saida" class="form-control" value="<?php echo e(substr($turno['hora_saida'], 0, 5)); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Inicio pausa</label>
                                <input type="time" name="inicio_pausa" class="form-control" value="<?php echo e($turno['inicio_pausa'] ? substr($turno['inicio_pausa'], 0, 5) : ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fim pausa</label>
                                <input type="time" name="fim_pausa" class="form-control" value="<?php echo e($turno['fim_pausa'] ? substr($turno['fim_pausa'], 0, 5) : ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tolerancia de atraso (min)</label>
                                <input type="number" name="tolerancia_entrada_min" class="form-control" min="0" value="<?php echo (int) $turno['tolerancia_entrada_min']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tolerancia de saida (min)</label>
                                <input type="number" name="tolerancia_saida_min" class="form-control" min="0" value="<?php echo (int) $turno['tolerancia_saida_min']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Horas previstas</label>
                                <input type="number" step="0.25" name="horas_previstas" class="form-control" min="0" value="<?php echo e($turno['horas_previstas']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="turno_noturno" id="editarNoturno<?php echo (int) $turno['id']; ?>" <?php echo (int) $turno['turno_noturno'] === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="editarNoturno<?php echo (int) $turno['id']; ?>">Turno noturno</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="editarAtivo<?php echo (int) $turno['id']; ?>" <?php echo (int) $turno['ativo'] === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="editarAtivo<?php echo (int) $turno['id']; ?>">Ativo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar alteracoes</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalAssociarTurno<?php echo (int) $turno['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Associar turno: <?php echo e($turno['nome']); ?></h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="post" class="needs-validation mb-4" novalidate>
                            <input type="hidden" name="acao" value="associar">
                            <input type="hidden" name="turno_id" value="<?php echo (int) $turno['id']; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Utilizador *</label>
                                    <select name="utilizador_id" class="form-select" required>
                                        <option value="">Selecionar utilizador</option>
                                        <?php foreach ($utilizadores as $utilizador): ?>
                                            <option value="<?php echo (int) $utilizador['id']; ?>"><?php echo e($utilizador['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Selecione um utilizador.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dia da semana</label>
                                    <select name="dia_semana" class="form-select">
                                        <option value="">Todos os dias</option>
                                        <?php for ($dia = 1; $dia <= 7; $dia++): ?>
                                            <option value="<?php echo $dia; ?>"><?php echo e(dia_semana_nome($dia)); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data inicio *</label>
                                    <input type="date" name="data_inicio" class="form-control" required>
                                    <div class="invalid-feedback">Indique a data de inicio.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data fim</label>
                                    <input type="date" name="data_fim" class="form-control">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-user-plus"></i>
                                Associar
                            </button>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Utilizador</th>
                                        <th>Dia</th>
                                        <th>Inicio</th>
                                        <th>Fim</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($associacoes[(int) $turno['id']] ?? []) as $associacao): ?>
                                        <tr>
                                            <td><?php echo e($associacao['utilizador_nome']); ?></td>
                                            <td><?php echo e(dia_semana_nome($associacao['dia_semana'])); ?></td>
                                            <td><?php echo e(date('d/m/Y', strtotime($associacao['data_inicio']))); ?></td>
                                            <td><?php echo $associacao['data_fim'] ? e(date('d/m/Y', strtotime($associacao['data_fim']))) : '-'; ?></td>
                                            <td class="text-end">
                                                <form method="post" onsubmit="return confirm('Remover esta associacao?');">
                                                    <input type="hidden" name="acao" value="remover_associacao">
                                                    <input type="hidden" name="id" value="<?php echo (int) $associacao['id']; ?>">
                                                    <button type="submit" class="btn btn-link btn-danger btn-sm">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($associacoes[(int) $turno['id']])): ?>
                                        <tr>
                                            <td colspan="5" class="text-muted">Sem associacoes registadas.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalRemoverTurno<?php echo (int) $turno['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content">
                    <input type="hidden" name="acao" value="remover">
                    <input type="hidden" name="id" value="<?php echo (int) $turno['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Remover turno</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php if ((int) $turno['total_associacoes'] > 0): ?>
                            <p class="mb-0">
                                Este turno tem <strong><?php echo (int) $turno['total_associacoes']; ?></strong>
                                associacao(oes), por isso nao pode ser removido.
                            </p>
                        <?php else: ?>
                            <p class="mb-0">Tem a certeza que pretende remover <strong><?php echo e($turno['nome']); ?></strong>?</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <?php if ((int) $turno['total_associacoes'] === 0): ?>
                            <button type="submit" class="btn btn-danger">Remover</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-turnos').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum turno encontrado',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Ultimo',
                        next: 'Seguinte',
                        previous: 'Anterior'
                    }
                }
            });

            $('.needs-validation').on('submit', function (event) {
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                $(this).addClass('was-validated');
            });
        });
    </script>
</body>

</html>
