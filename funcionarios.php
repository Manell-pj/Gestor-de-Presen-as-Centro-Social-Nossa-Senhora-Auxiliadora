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
    header('Location: funcionarios.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function get_post_value($key)
{
    return trim($_POST[$key] ?? '');
}

function nullable_text($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function nullable_int($value)
{
    return $value === '' ? null : (int) $value;
}

function nullable_date($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function funcionario_estado_badge($estado)
{
    if ($estado === 'ativo') {
        return 'success';
    }

    if ($estado === 'suspenso') {
        return 'warning';
    }

    return 'secondary';
}

function funcionario_tem_dependencias($conn, $funcionarioId)
{
    $checks = [
        ['registos_ponto', 'funcionario_id'],
        ['horarios_turno', 'funcionario_id'],
        ['banco_horas', 'funcionario_id'],
        ['escala_funcionarios', 'funcionario_id'],
        ['ferias_ausencias', 'funcionario_id'],
    ];

    foreach ($checks as [$table, $column]) {
        if (!fe_table_exists($conn, $table) || !fe_column_exists($conn, $table, $column)) {
            continue;
        }

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM $table WHERE $column = ?");
        mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) ($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

$missingTables = [];
if (!fe_table_exists($conn, 'funcionarios')) {
    $missingTables[] = 'funcionarios';
}

$temEquipas = fe_table_exists($conn, 'equipas') && fe_column_exists($conn, 'funcionarios', 'equipa_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($missingTables)) {
        redirect_with_message('danger', 'Execute a migration antes de gerir funcionários.');
    }

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar' || $acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $numeroMecanografico = nullable_text($_POST['numero_mecanografico'] ?? '');
        $email = nullable_text($_POST['email'] ?? '');
        $telefone = nullable_text($_POST['telefone'] ?? '');
        $funcao = nullable_text($_POST['funcao'] ?? '');
        $categoria = null;
        $setorId = null;
        $equipaId = $temEquipas ? nullable_int($_POST['equipa_id'] ?? '') : null;
        $dataAdmissao = nullable_date($_POST['data_admissao'] ?? '');
        $dataCessacao = nullable_date($_POST['data_cessacao'] ?? '');
        $tipoContrato = nullable_text($_POST['tipo_contrato'] ?? '');
        $cargaHoraria = (float) str_replace(',', '.', $_POST['carga_horaria_semanal'] ?? '40');
        $pinPonto = nullable_text($_POST['pin_ponto'] ?? '');
        $codigoCartao = nullable_text($_POST['codigo_cartao'] ?? '');
        $codigoBiometrico = nullable_text($_POST['codigo_biometrico'] ?? '');
        $estado = get_post_value('estado') ?: 'ativo';
        $observacoes = nullable_text($_POST['observacoes'] ?? '');

        if ($nome === '') {
            redirect_with_message('danger', 'Preencha o nome do funcionário.');
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message('danger', 'Introduza um email válido.');
        }

        if ($cargaHoraria <= 0) {
            redirect_with_message('danger', 'A carga horaria semanal deve ser superior a zero.');
        }

        if (!in_array($estado, ['ativo', 'suspenso', 'inativo'], true)) {
            $estado = 'ativo';
        }

        try {
            if ($acao === 'criar') {
                $stmt = mysqli_prepare($conn, "INSERT INTO funcionarios
                    (setor_id, equipa_id, numero_mecanografico, nome, email, telefone, funcao, categoria_profissional,
                     data_admissao, data_cessacao, tipo_contrato, carga_horaria_semanal, pin_ponto, codigo_cartao,
                     codigo_biometrico, estado, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param(
                    $stmt,
                    'iisssssssssdsssss',
                    $setorId,
                    $equipaId,
                    $numeroMecanografico,
                    $nome,
                    $email,
                    $telefone,
                    $funcao,
                    $categoria,
                    $dataAdmissao,
                    $dataCessacao,
                    $tipoContrato,
                    $cargaHoraria,
                    $pinPonto,
                    $codigoCartao,
                    $codigoBiometrico,
                    $estado,
                    $observacoes
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                redirect_with_message('success', 'Funcionário criado com sucesso.');
            }

            if ($id <= 0) {
                redirect_with_message('danger', 'Funcionário inválido.');
            }

            $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET
                    setor_id = ?, equipa_id = ?, numero_mecanografico = ?, nome = ?, email = ?, telefone = ?,
                    funcao = ?, categoria_profissional = ?, data_admissao = ?, data_cessacao = ?,
                    tipo_contrato = ?, carga_horaria_semanal = ?, pin_ponto = ?, codigo_cartao = ?,
                    codigo_biometrico = ?, estado = ?, observacoes = ?
                WHERE id = ?");
            mysqli_stmt_bind_param(
                $stmt,
                'iisssssssssdsssssi',
                $setorId,
                $equipaId,
                $numeroMecanografico,
                $nome,
                $email,
                $telefone,
                $funcao,
                $categoria,
                $dataAdmissao,
                $dataCessacao,
                $tipoContrato,
                $cargaHoraria,
                $pinPonto,
                $codigoCartao,
                $codigoBiometrico,
                $estado,
                $observacoes,
                $id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Funcionário atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível guardar o funcionário. Verifique campos unicos como número mecanográfico, PIN, cartao ou código biométrico.');
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Funcionário inválido.');
        }

        if (funcionario_tem_dependencias($conn, $id)) {
            redirect_with_message('danger', 'Não é possível remover este funcionário porque já tem registos associados.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM funcionarios WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Funcionário removido com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível remover o funcionário.');
        }
    }
}

$equipas = [];
if ($temEquipas) {
    $stmt = mysqli_prepare($conn, 'SELECT id, nome FROM equipas WHERE ativo = 1 ORDER BY nome ASC');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $equipas[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$funcionarios = [];
if (empty($missingTables)) {
    $selectEquipa = $temEquipas ? 'eq.nome AS equipa_nome' : 'NULL AS equipa_nome';
    $joinEquipa = $temEquipas ? 'LEFT JOIN equipas eq ON eq.id = f.equipa_id' : '';

    $sql = "SELECT f.*, $selectEquipa
        FROM funcionarios f
        $joinEquipa
        ORDER BY f.estado = 'ativo' DESC, f.nome ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[] = $row;
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
                        <h3 class="fw-bold mb-3">Funcionários</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="principal.php"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="funcionarios.php">Funcionários</a></li>
                        </ul>
                    </div>

                    <?php if ($alertMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($alertType ?: 'info'); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($alertMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($missingTables)): ?>
                        <div class="alert alert-warning" role="alert">
                            Execute a migration <code>database/2026_05_15_lar_idosos_assiduidade.sql</code> antes de gerir funcionários.
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">Lista de funcionários</h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalCriarFuncionario" <?php echo !empty($missingTables) ? 'disabled' : ''; ?>>
                                    <i class="fa fa-plus"></i>
                                    Adicionar funcionário
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-funcionarios" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>N.º mec.</th>
                                            <th>Nome</th>
                                            <th>Função</th>
                                            <th>Equipa</th>
                                            <th>Biometria</th>
                                            <th>Estado</th>
                                            <th style="width: 120px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($funcionarios as $funcionario): ?>
                                            <tr>
                                                <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                                                    <small class="text-muted"><?php echo e($funcionario['email'] ?: $funcionario['telefone'] ?: 'Sem contacto'); ?></small>
                                                </td>
                                                <td><?php echo e($funcionario['funcao'] ?: '-'); ?></td>
                                                <td><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                                                <td><?php echo e($funcionario['codigo_biometrico'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(funcionario_estado_badge($funcionario['estado'])); ?>">
                                                        <?php echo e(ucfirst($funcionario['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarFuncionario<?php echo (int) $funcionario['id']; ?>" title="Editar">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-danger" data-bs-toggle="modal" data-bs-target="#modalRemoverFuncionario<?php echo (int) $funcionario['id']; ?>" title="Remover">
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

    <div class="modal fade" id="modalCriarFuncionario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar funcionário</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php
                    $funcionarioForm = [
                        'id' => 0,
                        'nome' => '',
                        'numero_mecanografico' => '',
                        'email' => '',
                        'telefone' => '',
                        'funcao' => '',
                        'equipa_id' => '',
                        'data_admissao' => '',
                        'data_cessacao' => '',
                        'tipo_contrato' => '',
                        'carga_horaria_semanal' => '40.00',
                        'pin_ponto' => '',
                        'codigo_cartao' => '',
                        'codigo_biometrico' => '',
                        'estado' => 'ativo',
                        'observacoes' => '',
                    ];
                    include __DIR__ . '/includes/funcionario_form_campos.php';
                    ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($funcionarios as $funcionario): ?>
        <div class="modal fade" id="modalEditarFuncionario<?php echo (int) $funcionario['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar funcionário</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php
                        $funcionarioForm = $funcionario;
                        include __DIR__ . '/includes/funcionario_form_campos.php';
                        ?>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalRemoverFuncionario<?php echo (int) $funcionario['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content">
                    <input type="hidden" name="acao" value="remover">
                    <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Remover funcionário</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Tem a certeza que pretende remover <strong><?php echo e($funcionario['nome']); ?></strong>?</p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-danger">Remover</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-funcionarios').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum funcionário encontrado',
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

