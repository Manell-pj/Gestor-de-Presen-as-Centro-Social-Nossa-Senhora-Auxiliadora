<?php
require_once 'config.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: utilizadores.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function nullable_int($value)
{
    return $value === '' ? null : (int) $value;
}

function nullable_text($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function get_post_value($key)
{
    return trim($_POST[$key] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $email = get_post_value('email');
        $password = $_POST['password'] ?? '';
        $departamentoId = nullable_int($_POST['departamento_id'] ?? '');
        $papelId = nullable_int($_POST['papel_id'] ?? '');
        $numeroMecanografico = nullable_text($_POST['numero_mecanografico'] ?? '');
        $telefone = get_post_value('telefone');
        $cargo = get_post_value('cargo');
        $estado = get_post_value('estado') ?: 'ativo';

        if ($nome === '' || $email === '' || $password === '' || $papelId === null) {
            redirect_with_message('danger', 'Preencha os campos obrigatorios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message('danger', 'Introduza um email valido.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        mysqli_begin_transaction($conn);

        try {
            $stmt = mysqli_prepare($conn, 'INSERT INTO utilizadores (departamento_id, numero_mecanografico, nome, email, password_hash, telefone, cargo, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'isssssss', $departamentoId, $numeroMecanografico, $nome, $email, $passwordHash, $telefone, $cargo, $estado);
            mysqli_stmt_execute($stmt);
            $utilizadorId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'INSERT INTO utilizador_papeis (utilizador_id, papel_id) VALUES (?, ?)');
            mysqli_stmt_bind_param($stmt, 'ii', $utilizadorId, $papelId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            redirect_with_message('success', 'Utilizador criado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            redirect_with_message('danger', 'Nao foi possivel criar o utilizador. Verifique se o email ou numero mecanografico ja existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $email = get_post_value('email');
        $password = $_POST['password'] ?? '';
        $departamentoId = nullable_int($_POST['departamento_id'] ?? '');
        $papelId = nullable_int($_POST['papel_id'] ?? '');
        $numeroMecanografico = nullable_text($_POST['numero_mecanografico'] ?? '');
        $telefone = get_post_value('telefone');
        $cargo = get_post_value('cargo');
        $estado = get_post_value('estado') ?: 'ativo';

        if ($id <= 0 || $nome === '' || $email === '' || $papelId === null) {
            redirect_with_message('danger', 'Preencha os campos obrigatorios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_with_message('danger', 'Introduza um email valido.');
        }

        mysqli_begin_transaction($conn);

        try {
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET departamento_id = ?, numero_mecanografico = ?, nome = ?, email = ?, password_hash = ?, telefone = ?, cargo = ?, estado = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'isssssssi', $departamentoId, $numeroMecanografico, $nome, $email, $passwordHash, $telefone, $cargo, $estado, $id);
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET departamento_id = ?, numero_mecanografico = ?, nome = ?, email = ?, telefone = ?, cargo = ?, estado = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'issssssi', $departamentoId, $numeroMecanografico, $nome, $email, $telefone, $cargo, $estado, $id);
            }

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'DELETE FROM utilizador_papeis WHERE utilizador_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'INSERT INTO utilizador_papeis (utilizador_id, papel_id) VALUES (?, ?)');
            mysqli_stmt_bind_param($stmt, 'ii', $id, $papelId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            redirect_with_message('success', 'Utilizador atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            redirect_with_message('danger', 'Nao foi possivel atualizar o utilizador. Verifique se o email ou numero mecanografico ja existe.');
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Utilizador invalido.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM utilizadores WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Utilizador removido com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel remover este utilizador porque ja tem registos associados.');
        }
    }
}

$departamentos = [];
$stmt = mysqli_prepare($conn, 'SELECT id, nome FROM departamentos WHERE ativo = 1 ORDER BY nome ASC');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $departamentos[] = $row;
}
mysqli_stmt_close($stmt);

$papeis = [];
$stmt = mysqli_prepare($conn, 'SELECT id, nome FROM papeis WHERE ativo = 1 ORDER BY nome ASC');
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $papeis[] = $row;
}
mysqli_stmt_close($stmt);

$utilizadores = [];
$sql = "SELECT
            u.id,
            u.numero_mecanografico,
            u.nome,
            u.email,
            u.telefone,
            u.cargo,
            u.estado,
            u.departamento_id,
            d.nome AS departamento_nome,
            p.id AS papel_id,
            p.nome AS papel_nome
        FROM utilizadores u
        LEFT JOIN departamentos d ON d.id = u.departamento_id
        LEFT JOIN utilizador_papeis up ON up.utilizador_id = u.id
        LEFT JOIN papeis p ON p.id = up.papel_id
        ORDER BY u.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $utilizadores[] = $row;
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
                        <h3 class="fw-bold mb-3">Utilizadores</h3>
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
                                <a href="utilizadores.php">Utilizadores</a>
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
                                <h4 class="card-title">Lista de utilizadores</h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalCriarUtilizador">
                                    <i class="fa fa-plus"></i>
                                    Adicionar utilizador
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-utilizadores" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>N. mec.</th>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Departamento</th>
                                            <th>Papel</th>
                                            <th>Estado</th>
                                            <th style="width: 120px">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($utilizadores as $utilizador): ?>
                                            <tr>
                                                <td><?php echo e($utilizador['numero_mecanografico'] ?: '-'); ?></td>
                                                <td><?php echo e($utilizador['nome']); ?></td>
                                                <td><?php echo e($utilizador['email']); ?></td>
                                                <td><?php echo e($utilizador['departamento_nome'] ?: 'Sem departamento'); ?></td>
                                                <td><?php echo e($utilizador['papel_nome'] ?: 'Sem papel'); ?></td>
                                                <td>
                                                    <?php
                                                    $badgeClass = $utilizador['estado'] === 'ativo' ? 'success' : ($utilizador['estado'] === 'suspenso' ? 'warning' : 'secondary');
                                                    ?>
                                                    <span class="badge badge-<?php echo e($badgeClass); ?>"><?php echo e(ucfirst($utilizador['estado'])); ?></span>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarUtilizador<?php echo (int) $utilizador['id']; ?>" title="Editar">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-danger" data-bs-toggle="modal" data-bs-target="#modalRemoverUtilizador<?php echo (int) $utilizador['id']; ?>" title="Remover">
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

    <div class="modal fade" id="modalCriarUtilizador" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar utilizador</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" required>
                            <div class="invalid-feedback">Indique o nome.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                            <div class="invalid-feedback">Indique um email valido.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                            <div class="invalid-feedback">Indique uma password.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Papel *</label>
                            <select name="papel_id" class="form-select" required>
                                <option value="">Selecionar papel</option>
                                <?php foreach ($papeis as $papel): ?>
                                    <option value="<?php echo (int) $papel['id']; ?>"><?php echo e($papel['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione um papel.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departamento</label>
                            <select name="departamento_id" class="form-select">
                                <option value="">Sem departamento</option>
                                <?php foreach ($departamentos as $departamento): ?>
                                    <option value="<?php echo (int) $departamento['id']; ?>"><?php echo e($departamento['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Numero mecanografico</label>
                            <input type="text" name="numero_mecanografico" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cargo</label>
                            <input type="text" name="cargo" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="ativo">Ativo</option>
                                <option value="suspenso">Suspenso</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($utilizadores as $utilizador): ?>
        <div class="modal fade" id="modalEditarUtilizador<?php echo (int) $utilizador['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $utilizador['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar utilizador</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo e($utilizador['nome']); ?>" required>
                                <div class="invalid-feedback">Indique o nome.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" value="<?php echo e($utilizador['email']); ?>" required>
                                <div class="invalid-feedback">Indique um email valido.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nova password</label>
                                <input type="password" name="password" class="form-control" placeholder="Manter atual se ficar vazio">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Papel *</label>
                                <select name="papel_id" class="form-select" required>
                                    <option value="">Selecionar papel</option>
                                    <?php foreach ($papeis as $papel): ?>
                                        <option value="<?php echo (int) $papel['id']; ?>" <?php echo ((int) $utilizador['papel_id'] === (int) $papel['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($papel['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Selecione um papel.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Departamento</label>
                                <select name="departamento_id" class="form-select">
                                    <option value="">Sem departamento</option>
                                    <?php foreach ($departamentos as $departamento): ?>
                                        <option value="<?php echo (int) $departamento['id']; ?>" <?php echo ((int) $utilizador['departamento_id'] === (int) $departamento['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($departamento['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Numero mecanografico</label>
                                <input type="text" name="numero_mecanografico" class="form-control" value="<?php echo e($utilizador['numero_mecanografico']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" class="form-control" value="<?php echo e($utilizador['telefone']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cargo</label>
                                <input type="text" name="cargo" class="form-control" value="<?php echo e($utilizador['cargo']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="ativo" <?php echo $utilizador['estado'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="suspenso" <?php echo $utilizador['estado'] === 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                                    <option value="inativo" <?php echo $utilizador['estado'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                                </select>
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

        <div class="modal fade" id="modalRemoverUtilizador<?php echo (int) $utilizador['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content">
                    <input type="hidden" name="acao" value="remover">
                    <input type="hidden" name="id" value="<?php echo (int) $utilizador['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Remover utilizador</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Tem a certeza que pretende remover <strong><?php echo e($utilizador['nome']); ?></strong>?</p>
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
            $('#tabela-utilizadores').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum utilizador encontrado',
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
