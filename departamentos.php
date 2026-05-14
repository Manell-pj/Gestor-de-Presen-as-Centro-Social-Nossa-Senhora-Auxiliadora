<?php
require_once 'config.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: departamentos.php?' . http_build_query([
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $codigo = nullable_text($_POST['codigo'] ?? '');
        $descricao = nullable_text($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '') {
            redirect_with_message('danger', 'Preencha o nome do departamento.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'INSERT INTO departamentos (nome, codigo, descricao, ativo) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssi', $nome, $codigo, $descricao, $ativo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Departamento criado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel criar o departamento. Verifique se o codigo ja existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $codigo = nullable_text($_POST['codigo'] ?? '');
        $descricao = nullable_text($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($id <= 0 || $nome === '') {
            redirect_with_message('danger', 'Preencha os campos obrigatorios.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE departamentos SET nome = ?, codigo = ?, descricao = ?, ativo = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'sssii', $nome, $codigo, $descricao, $ativo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Departamento atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel atualizar o departamento. Verifique se o codigo ja existe.');
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Departamento invalido.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM utilizadores WHERE departamento_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) $row['total'] > 0) {
            redirect_with_message('danger', 'Nao e possivel remover este departamento porque existem utilizadores associados.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM departamentos WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Departamento removido com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel remover o departamento.');
        }
    }
}

$departamentos = [];
$sql = "SELECT
            d.id,
            d.nome,
            d.codigo,
            d.descricao,
            d.ativo,
            d.created_at,
            COUNT(u.id) AS total_utilizadores
        FROM departamentos d
        LEFT JOIN utilizadores u ON u.departamento_id = d.id
        GROUP BY d.id, d.nome, d.codigo, d.descricao, d.ativo, d.created_at
        ORDER BY d.nome ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $departamentos[] = $row;
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
                        <h3 class="fw-bold mb-3">Departamentos</h3>
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
                                <a href="departamentos.php">Departamentos</a>
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
                                <h4 class="card-title">Lista de departamentos</h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalCriarDepartamento">
                                    <i class="fa fa-plus"></i>
                                    Adicionar departamento
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-departamentos" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Codigo</th>
                                            <th>Nome</th>
                                            <th>Descricao</th>
                                            <th>Utilizadores</th>
                                            <th>Estado</th>
                                            <th style="width: 120px">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departamentos as $departamento): ?>
                                            <tr>
                                                <td><?php echo e($departamento['codigo'] ?: '-'); ?></td>
                                                <td><?php echo e($departamento['nome']); ?></td>
                                                <td><?php echo e($departamento['descricao'] ?: '-'); ?></td>
                                                <td><?php echo (int) $departamento['total_utilizadores']; ?></td>
                                                <td>
                                                    <?php if ((int) $departamento['ativo'] === 1): ?>
                                                        <span class="badge badge-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarDepartamento<?php echo (int) $departamento['id']; ?>" title="Editar">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-danger" data-bs-toggle="modal" data-bs-target="#modalRemoverDepartamento<?php echo (int) $departamento['id']; ?>" title="Remover">
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

    <div class="modal fade" id="modalCriarDepartamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar departamento</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                        <div class="invalid-feedback">Indique o nome do departamento.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Codigo</label>
                        <input type="text" name="codigo" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descricao</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ativo" id="criarAtivo" checked>
                        <label class="form-check-label" for="criarAtivo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($departamentos as $departamento): ?>
        <div class="modal fade" id="modalEditarDepartamento<?php echo (int) $departamento['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $departamento['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar departamento</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo e($departamento['nome']); ?>" required>
                            <div class="invalid-feedback">Indique o nome do departamento.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Codigo</label>
                            <input type="text" name="codigo" class="form-control" value="<?php echo e($departamento['codigo']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descricao</label>
                            <textarea name="descricao" class="form-control" rows="3"><?php echo e($departamento['descricao']); ?></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ativo" id="editarAtivo<?php echo (int) $departamento['id']; ?>" <?php echo (int) $departamento['ativo'] === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="editarAtivo<?php echo (int) $departamento['id']; ?>">Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar alteracoes</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalRemoverDepartamento<?php echo (int) $departamento['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content">
                    <input type="hidden" name="acao" value="remover">
                    <input type="hidden" name="id" value="<?php echo (int) $departamento['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Remover departamento</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php if ((int) $departamento['total_utilizadores'] > 0): ?>
                            <p class="mb-0">
                                Este departamento tem <strong><?php echo (int) $departamento['total_utilizadores']; ?></strong>
                                utilizador(es) associado(s), por isso nao pode ser removido.
                            </p>
                        <?php else: ?>
                            <p class="mb-0">Tem a certeza que pretende remover <strong><?php echo e($departamento['nome']); ?></strong>?</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <?php if ((int) $departamento['total_utilizadores'] === 0): ?>
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
            $('#tabela-departamentos').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum departamento encontrado',
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
