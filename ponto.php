<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';

$utilizadorSessao = require_login($conn);

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: ponto.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function movimento_label($tipo)
{
    $labels = [
        'entrada' => 'Entrada',
        'saida' => 'Saída',
        'inicio_pausa' => 'Início de pausa',
        'fim_pausa' => 'Fim de pausa',
    ];

    return $labels[$tipo] ?? $tipo;
}

function movimento_badge($tipo)
{
    $classes = [
        'entrada' => 'success',
        'saida' => 'danger',
        'inicio_pausa' => 'warning',
        'fim_pausa' => 'info',
    ];

    return $classes[$tipo] ?? 'secondary';
}

$missingTables = [];

foreach (['funcionarios', 'registos_ponto'] as $table) {
    if (!fe_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}

$temFuncionarioRegisto = empty($missingTables) && fe_column_exists($conn, 'registos_ponto', 'funcionario_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'registar_ponto') {
    if (!$temFuncionarioRegisto) {
        redirect_with_message('danger', 'Execute a migration antes de registar ponto por funcionário.');
    }

    $funcionarioId = (int) ($_POST['funcionario_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $dataHora = trim($_POST['data_hora'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $tiposPermitidos = ['entrada', 'saida', 'inicio_pausa', 'fim_pausa'];

    if ($funcionarioId <= 0 || !in_array($tipo, $tiposPermitidos, true) || $dataHora === '') {
        redirect_with_message('danger', 'Preencha funcionário, movimento e data/hora.');
    }

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dataHora);
    if (!$dt) {
        redirect_with_message('danger', 'Data/hora inválida.');
    }

    $dataHoraSql = $dt->format('Y-m-d H:i:s');
    $dataReferencia = $dt->format('Y-m-d');
    $observacoes = $observacoes === '' ? null : $observacoes;

    $stmt = mysqli_prepare($conn, "SELECT id FROM funcionarios WHERE id = ? AND estado = 'ativo' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcionarioExiste = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcionarioExiste) {
        redirect_with_message('danger', 'Funcionário inválido ou inativo.');
    }

    $temDataReferencia = fe_column_exists($conn, 'registos_ponto', 'data_referencia');

    if ($temDataReferencia) {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, data_referencia, origem, estado, observacoes)
            VALUES (?, ?, ?, ?, 'manual', 'valido', ?)");
        mysqli_stmt_bind_param($stmt, 'issss', $funcionarioId, $tipo, $dataHoraSql, $dataReferencia, $observacoes);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto
            (funcionario_id, tipo, data_hora, origem, estado, observacoes)
            VALUES (?, ?, ?, 'manual', 'valido', ?)");
        mysqli_stmt_bind_param($stmt, 'isss', $funcionarioId, $tipo, $dataHoraSql, $observacoes);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    redirect_with_message('success', movimento_label($tipo) . ' registada com sucesso.');
}

$funcionarios = [];
$registos = [];

if (empty($missingTables)) {
    $stmt = mysqli_prepare($conn, "SELECT id, numero_mecanografico, nome, funcao, codigo_biometrico
        FROM funcionarios
        WHERE estado = 'ativo'
        ORDER BY nome ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[] = $row;
    }
    mysqli_stmt_close($stmt);
}

if ($temFuncionarioRegisto) {
    $stmt = mysqli_prepare($conn, "SELECT rp.id, rp.tipo, rp.data_hora, rp.origem, rp.estado, rp.observacoes,
               f.nome AS funcionario_nome, f.numero_mecanografico
        FROM registos_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
        WHERE DATE(rp.data_hora) = CURDATE()
        ORDER BY rp.data_hora DESC, rp.id DESC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $registos[] = $row;
    }
    mysqli_stmt_close($stmt);
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
                        <h3 class="fw-bold mb-3">Registo de Ponto</h3>
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
                                <a href="ponto.php">Ponto</a>
                            </li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($missingTables) || !$temFuncionarioRegisto): ?>
                        <div class="alert alert-warning" role="alert">
                            Execute a migration <code>database/2026_05_15_lar_idosos_assiduidade.sql</code> para ativar registos por funcionário.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Correção manual</h4>
                                </div>
                                <div class="card-body">
                                    <form method="post" class="needs-validation" novalidate>
                                        <input type="hidden" name="acao" value="registar_ponto">
                                        <div class="mb-3">
                                            <label class="form-label">Funcionário *</label>
                                            <select name="funcionario_id" class="form-select" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                                <option value="">Selecionar funcionário</option>
                                                <?php foreach ($funcionarios as $funcionario): ?>
                                                    <option value="<?php echo (int) $funcionario['id']; ?>">
                                                        <?php echo e($funcionario['nome']); ?>
                                                        <?php if ($funcionario['numero_mecanografico']): ?>
                                                            (<?php echo e($funcionario['numero_mecanografico']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Selecione um funcionário.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Movimento *</label>
                                            <select name="tipo" class="form-select" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                                <option value="">Selecionar movimento</option>
                                                <option value="entrada">Entrada</option>
                                                <option value="saida">Saída</option>
                                                <option value="inicio_pausa">Início de pausa</option>
                                                <option value="fim_pausa">Fim de pausa</option>
                                            </select>
                                            <div class="invalid-feedback">Selecione o movimento.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Data/hora *</label>
                                            <input type="datetime-local" name="data_hora" class="form-control" value="<?php echo e(date('Y-m-d\TH:i')); ?>" required <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                            <div class="invalid-feedback">Indique a data/hora.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacoes" class="form-control" rows="3" <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100" <?php echo !$temFuncionarioRegisto ? 'disabled' : ''; ?>>
                                            <i class="fa fa-save"></i>
                                            Guardar movimento
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Registos de hoje</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="tabela-ponto" class="display table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Funcionário</th>
                                                    <th>N.º mec.</th>
                                                    <th>Hora</th>
                                                    <th>Movimento</th>
                                                    <th>Origem</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($registos as $registo): ?>
                                                    <tr>
                                                        <td><?php echo e($registo['funcionario_nome']); ?></td>
                                                        <td><?php echo e($registo['numero_mecanografico'] ?: '-'); ?></td>
                                                        <td><?php echo e(date('H:i:s', strtotime($registo['data_hora']))); ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo e(movimento_badge($registo['tipo'])); ?>">
                                                                <?php echo e(movimento_label($registo['tipo'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo e(ucfirst($registo['origem'])); ?></td>
                                                        <td><?php echo e(ucfirst($registo['estado'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-ponto').DataTable({
                pageLength: 25,
                order: [[2, 'desc']],
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum registo encontrado',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Último',
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
