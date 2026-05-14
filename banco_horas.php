<?php
session_start();
require_once 'config.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: banco_horas.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function formatar_minutos($minutos)
{
    $sinal = $minutos < 0 ? '-' : '+';
    $abs = abs((int) $minutos);
    $horas = intdiv($abs, 60);
    $mins = $abs % 60;

    return $sinal . sprintf('%02d:%02d', $horas, $mins);
}

function badge_saldo($minutos)
{
    if ($minutos > 0) {
        return 'success';
    }

    if ($minutos < 0) {
        return 'danger';
    }

    return 'secondary';
}

function diff_minutos($inicio, $fim)
{
    return max(0, (int) round((strtotime($fim) - strtotime($inicio)) / 60));
}

function calcular_minutos_trabalhados($movimentos)
{
    $total = 0;
    $entrada = null;
    $inicioPausa = null;
    $pausas = 0;

    foreach ($movimentos as $movimento) {
        $tipo = $movimento['tipo'];
        $dataHora = $movimento['data_hora'];

        if ($tipo === 'entrada') {
            $entrada = $dataHora;
            $inicioPausa = null;
            $pausas = 0;
            continue;
        }

        if ($tipo === 'inicio_pausa' && $entrada !== null) {
            $inicioPausa = $dataHora;
            continue;
        }

        if ($tipo === 'fim_pausa' && $entrada !== null && $inicioPausa !== null) {
            $pausas += diff_minutos($inicioPausa, $dataHora);
            $inicioPausa = null;
            continue;
        }

        if ($tipo === 'saida' && $entrada !== null) {
            $total += max(0, diff_minutos($entrada, $dataHora) - $pausas);
            $entrada = null;
            $inicioPausa = null;
            $pausas = 0;
        }
    }

    return $total;
}

function turno_para_data($horarios, $utilizadorId, $data)
{
    $diaSemana = (int) date('N', strtotime($data));
    $turnoEncontrado = null;

    foreach ($horarios as $horario) {
        if ((int) $horario['utilizador_id'] !== (int) $utilizadorId) {
            continue;
        }

        if ($horario['data_inicio'] > $data) {
            continue;
        }

        if ($horario['data_fim'] !== null && $horario['data_fim'] < $data) {
            continue;
        }

        if ($horario['dia_semana'] !== null && (int) $horario['dia_semana'] !== $diaSemana) {
            continue;
        }

        if ($turnoEncontrado === null) {
            $turnoEncontrado = $horario;
            continue;
        }

        $horarioEspecifico = $horario['dia_semana'] !== null;
        $turnoAtualGenerico = $turnoEncontrado['dia_semana'] === null;

        if ($horarioEspecifico && $turnoAtualGenerico) {
            $turnoEncontrado = $horario;
            continue;
        }

        if ($horario['data_inicio'] > $turnoEncontrado['data_inicio']) {
            $turnoEncontrado = $horario;
        }
    }

    return $turnoEncontrado;
}

function utilizador_tem_papel($conn, $utilizadorId, $slugs)
{
    $slugsPermitidos = [];

    foreach ($slugs as $slug) {
        $slugsPermitidos[] = "'" . mysqli_real_escape_string($conn, $slug) . "'";
    }

    if (empty($slugsPermitidos)) {
        return false;
    }

    $listaSlugs = implode(',', $slugsPermitidos);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total
        FROM utilizador_papeis up
        INNER JOIN papeis p ON p.id = up.papel_id
        WHERE up.utilizador_id = ? AND p.slug IN ($listaSlugs)");
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

$utilizadorAutenticadoId = (int) ($_SESSION['utilizador_id'] ?? $_SESSION['user_id'] ?? 0);
$utilizadorAutenticado = null;
$isAdmin = false;

if ($utilizadorAutenticadoId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, nome, email, estado FROM utilizadores WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $utilizadorAutenticado = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($utilizadorAutenticado) {
        $isAdmin = utilizador_tem_papel($conn, $utilizadorAutenticadoId, ['administrador']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'ajustar') {
        if (!$utilizadorAutenticado || !$isAdmin) {
            redirect_with_message('danger', 'Apenas administradores podem efetuar ajustes manuais.');
        }

        $utilizadorId = (int) ($_POST['utilizador_id'] ?? 0);
        $tipoMovimento = $_POST['tipo_movimento'] ?? '';
        $horas = (float) str_replace(',', '.', $_POST['horas'] ?? '0');
        $motivo = trim($_POST['motivo'] ?? '');

        if ($utilizadorId <= 0 || !in_array($tipoMovimento, ['credito', 'debito'], true) || $horas <= 0 || $motivo === '') {
            redirect_with_message('danger', 'Preencha utilizador, tipo, horas e motivo do ajuste.');
        }

        $minutos = (int) round($horas * 60);

        if ($tipoMovimento === 'debito') {
            $minutos *= -1;
        }

        mysqli_begin_transaction($conn);

        try {
            $stmt = mysqli_prepare($conn, "INSERT INTO banco_horas
                (utilizador_id, data_movimento, tipo_movimento, minutos, origem, descricao, criado_por)
                VALUES (?, CURDATE(), 'ajuste', ?, 'manual', ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iisi', $utilizadorId, $minutos, $motivo, $utilizadorAutenticadoId);
            mysqli_stmt_execute($stmt);
            $movimentoId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $dadosNovos = json_encode([
                'banco_horas_id' => $movimentoId,
                'utilizador_id' => $utilizadorId,
                'minutos' => $minutos,
                'motivo' => $motivo,
            ], JSON_UNESCAPED_UNICODE);

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $descricaoLog = 'Ajuste manual de banco de horas';

            $stmt = mysqli_prepare($conn, "INSERT INTO logs_sistema
                (utilizador_id, acao, modulo, tabela, registo_id, descricao, ip, user_agent, dados_novos)
                VALUES (?, 'ajuste_banco_horas', 'banco_horas', 'banco_horas', ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iissss', $utilizadorAutenticadoId, $movimentoId, $descricaoLog, $ip, $userAgent, $dadosNovos);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            redirect_with_message('success', 'Ajuste manual registado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            redirect_with_message('danger', 'Nao foi possivel registar o ajuste manual.');
        }
    }
}

$utilizadores = [];

if ($isAdmin) {
    $stmt = mysqli_prepare($conn, "SELECT id, nome, email FROM utilizadores WHERE estado <> 'inativo' ORDER BY nome ASC");
} elseif ($utilizadorAutenticado) {
    $stmt = mysqli_prepare($conn, 'SELECT id, nome, email FROM utilizadores WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
} else {
    $stmt = null;
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $row['minutos_calculados'] = 0;
        $row['minutos_ajustes'] = 0;
        $row['saldo_total'] = 0;
        $row['dias_calculados'] = 0;
        $utilizadores[(int) $row['id']] = $row;
    }
    mysqli_stmt_close($stmt);
}

$horarios = [];
$stmt = mysqli_prepare($conn, "SELECT ht.utilizador_id, ht.data_inicio, ht.data_fim, ht.dia_semana, t.horas_previstas
    FROM horarios_turno ht
    INNER JOIN turnos t ON t.id = ht.turno_id
    WHERE ht.ativo = 1 AND t.ativo = 1
    ORDER BY ht.utilizador_id ASC, ht.data_inicio ASC, ht.dia_semana DESC");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $horarios[] = $row;
}
mysqli_stmt_close($stmt);

$registosPorDia = [];

if (!empty($utilizadores)) {
    $ids = implode(',', array_map('intval', array_keys($utilizadores)));

    $stmt = mysqli_prepare($conn, "SELECT utilizador_id, tipo, data_hora, DATE(data_hora) AS data_registo
        FROM registos_ponto
        WHERE estado IN ('valido', 'corrigido') AND utilizador_id IN ($ids)
        ORDER BY utilizador_id ASC, data_hora ASC, id ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $registosPorDia[(int) $row['utilizador_id']][$row['data_registo']][] = $row;
    }
    mysqli_stmt_close($stmt);
}

foreach ($registosPorDia as $utilizadorId => $dias) {
    foreach ($dias as $data => $movimentos) {
        $minutosTrabalhados = calcular_minutos_trabalhados($movimentos);
        $turno = turno_para_data($horarios, $utilizadorId, $data);
        $minutosPrevistos = $turno ? (int) round(((float) $turno['horas_previstas']) * 60) : 0;
        $diferenca = $minutosPrevistos > 0 ? $minutosTrabalhados - $minutosPrevistos : $minutosTrabalhados;

        if (isset($utilizadores[$utilizadorId])) {
            $utilizadores[$utilizadorId]['minutos_calculados'] += $diferenca;
            $utilizadores[$utilizadorId]['dias_calculados']++;
        }
    }
}

$historico = [];

if (!empty($utilizadores)) {
    $ids = implode(',', array_map('intval', array_keys($utilizadores)));

    $stmt = mysqli_prepare($conn, "SELECT bh.*, u.nome AS utilizador_nome, criador.nome AS criado_por_nome
        FROM banco_horas bh
        INNER JOIN utilizadores u ON u.id = bh.utilizador_id
        LEFT JOIN utilizadores criador ON criador.id = bh.criado_por
        WHERE bh.utilizador_id IN ($ids)
        ORDER BY bh.created_at DESC, bh.id DESC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $historico[] = $row;

        $utilizadorId = (int) $row['utilizador_id'];
        if (isset($utilizadores[$utilizadorId])) {
            $utilizadores[$utilizadorId]['minutos_ajustes'] += (int) $row['minutos'];
        }
    }
    mysqli_stmt_close($stmt);
}

foreach ($utilizadores as $id => $utilizador) {
    $utilizadores[$id]['saldo_total'] = $utilizador['minutos_calculados'] + $utilizador['minutos_ajustes'];
}

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
                        <h3 class="fw-bold mb-3">Banco de Horas</h3>
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
                                <a href="banco_horas.php">Banco de Horas</a>
                            </li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!$utilizadorAutenticado): ?>
                        <div class="alert alert-warning" role="alert">
                            Nao existe utilizador autenticado na sessao. Depois de criares o login, define
                            <strong>$_SESSION['utilizador_id']</strong> com o ID do utilizador autenticado.
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Saldos por utilizador</h4>
                                <?php if ($isAdmin): ?>
                                    <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalAjusteBancoHoras">
                                        <i class="fa fa-plus"></i>
                                        Ajuste manual
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-banco-horas" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Utilizador</th>
                                            <th>Email</th>
                                            <th>Dias calculados</th>
                                            <th>Saldo por ponto</th>
                                            <th>Ajustes manuais</th>
                                            <th>Saldo total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($utilizadores as $utilizador): ?>
                                            <tr>
                                                <td><?php echo e($utilizador['nome']); ?></td>
                                                <td><?php echo e($utilizador['email']); ?></td>
                                                <td><?php echo (int) $utilizador['dias_calculados']; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(badge_saldo($utilizador['minutos_calculados'])); ?>">
                                                        <?php echo e(formatar_minutos($utilizador['minutos_calculados'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(badge_saldo($utilizador['minutos_ajustes'])); ?>">
                                                        <?php echo e(formatar_minutos($utilizador['minutos_ajustes'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(badge_saldo($utilizador['saldo_total'])); ?>">
                                                        <?php echo e(formatar_minutos($utilizador['saldo_total'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Historico de ajustes</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-historico-banco-horas" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Utilizador</th>
                                            <th>Movimento</th>
                                            <th>Minutos</th>
                                            <th>Motivo</th>
                                            <th>Criado por</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historico as $movimento): ?>
                                            <tr>
                                                <td><?php echo e(date('d/m/Y H:i', strtotime($movimento['created_at']))); ?></td>
                                                <td><?php echo e($movimento['utilizador_nome']); ?></td>
                                                <td><?php echo e(ucfirst($movimento['tipo_movimento'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(badge_saldo((int) $movimento['minutos'])); ?>">
                                                        <?php echo e(formatar_minutos((int) $movimento['minutos'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo e($movimento['descricao']); ?></td>
                                                <td><?php echo e($movimento['criado_por_nome'] ?: '-'); ?></td>
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

    <?php if ($isAdmin): ?>
        <div class="modal fade" id="modalAjusteBancoHoras" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="ajustar">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Ajuste manual de banco de horas</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Utilizador *</label>
                            <select name="utilizador_id" class="form-select" required>
                                <option value="">Selecionar utilizador</option>
                                <?php foreach ($utilizadores as $utilizador): ?>
                                    <option value="<?php echo (int) $utilizador['id']; ?>"><?php echo e($utilizador['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um utilizador.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo_movimento" class="form-select" required>
                                <option value="">Selecionar tipo</option>
                                <option value="credito">Credito</option>
                                <option value="debito">Debito</option>
                            </select>
                            <div class="invalid-feedback">Selecione o tipo de ajuste.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Horas *</label>
                            <input type="number" step="0.25" min="0.25" name="horas" class="form-control" required>
                            <div class="invalid-feedback">Indique o numero de horas.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo *</label>
                            <textarea name="motivo" class="form-control" rows="4" required></textarea>
                            <div class="invalid-feedback">Indique o motivo do ajuste.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar ajuste</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-banco-horas').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum saldo encontrado',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Ultimo',
                        next: 'Seguinte',
                        previous: 'Anterior'
                    }
                }
            });

            $('#tabela-historico-banco-horas').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum ajuste encontrado',
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
