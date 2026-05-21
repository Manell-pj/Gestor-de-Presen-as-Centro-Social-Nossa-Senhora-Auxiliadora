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

function equipa_setor_padrao($conn)
{
    if (!fe_table_exists($conn, 'setores')) {
        return null;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id FROM setores WHERE ativo = 1 ORDER BY id ASC LIMIT 1');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return isset($row['id']) ? (int) $row['id'] : null;
}

$temEquipas = fe_table_exists($conn, 'equipas');
$temSetorEquipa = $temEquipas && fe_column_exists($conn, 'equipas', 'setor_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = get_post_value('nome');
        $codigo = nullable_text($_POST['codigo'] ?? '');
        $descricao = nullable_text($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '') {
            redirect_with_message('danger', 'Preencha o nome da equipa.');
        }

        try {
            if ($temSetorEquipa) {
                $setorPadraoId = equipa_setor_padrao($conn);
                $stmt = mysqli_prepare($conn, 'INSERT INTO equipas (setor_id, nome, codigo, descricao, ativo) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'isssi', $setorPadraoId, $nome, $codigo, $descricao, $ativo);
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO equipas (nome, codigo, descricao, ativo) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'sssi', $nome, $codigo, $descricao, $ativo);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Equipa criada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível criar a equipa. Verifique se o código já existe.');
        }
    }

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $codigo = nullable_text($_POST['codigo'] ?? '');
        $descricao = nullable_text($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($id <= 0 || $nome === '') {
            redirect_with_message('danger', 'Preencha os campos obrigatórios.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'UPDATE equipas SET nome = ?, codigo = ?, descricao = ?, ativo = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'sssii', $nome, $codigo, $descricao, $ativo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Equipa atualizada com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível atualizar a equipa. Verifique se o código já existe.');
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            redirect_with_message('danger', 'Equipa inválida.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM funcionarios WHERE equipa_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) $row['total'] > 0) {
            redirect_with_message('danger', 'Não é possível remover esta equipa porque existem funcionários associados.');
        }

        try {
            $stmt = mysqli_prepare($conn, 'DELETE FROM equipas WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Equipa removida com sucesso.');
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Não foi possível remover a equipa.');
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
            COUNT(u.id) AS total_funcionarios
        FROM equipas d
        LEFT JOIN funcionarios u ON u.equipa_id = d.id
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
                        <h3 class="fw-bold mb-3">Equipas</h3>
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
                                <a href="departamentos.php">Equipas</a>
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
                                <h4 class="card-title">Lista de equipas</h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalCriarEquipa">
                                    <i class="fa fa-plus"></i>
                                    Adicionar equipa
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-equipas" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nome</th>
                                            <th>Descrição</th>
                                            <th>Funcionários</th>
                                            <th>Estado</th>
                                            <th style="width: 120px">Ações</th>
                                        </tr>
                                    </thead> 
                                    <tbody>
                                        <?php foreach ($departamentos as $departamento): ?>
                                            <tr>
                                                <td><?php echo e($departamento['codigo'] ?: '-'); ?></td>
                                                <td><?php echo e($departamento['nome']); ?></td>
                                                <td><?php echo e($departamento['descricao'] ?: '-'); ?></td>
                                                <td><?php echo (int) $departamento['total_funcionarios']; ?></td>
                                                <td>
                                                    <?php if ((int) $departamento['ativo'] === 1): ?>
                                                        <span class="badge badge-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="form-button-action">
                                                        <button type="button" class="btn btn-link btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalEditarEquipa<?php echo (int) $departamento['id']; ?>" title="Editar">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-link btn-danger" data-bs-toggle="modal" data-bs-target="#modalRemoverEquipa<?php echo (int) $departamento['id']; ?>" title="Remover">
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

    <div class="modal fade" id="modalCriarEquipa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="criar">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Adicionar equipa</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required>
                        <div class="invalid-feedback">Indique o nome da equipa.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
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
        <div class="modal fade" id="modalEditarEquipa<?php echo (int) $departamento['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content needs-validation" novalidate>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo (int) $departamento['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Editar equipa</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo e($departamento['nome']); ?>" required>
                            <div class="invalid-feedback">Indique o nome da equipa.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control" value="<?php echo e($departamento['codigo']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3"><?php echo e($departamento['descricao']); ?></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ativo" id="editarAtivo<?php echo (int) $departamento['id']; ?>" <?php echo (int) $departamento['ativo'] === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="editarAtivo<?php echo (int) $departamento['id']; ?>">Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Guardar alterações</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalRemoverEquipa<?php echo (int) $departamento['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" class="modal-content">
                    <input type="hidden" name="acao" value="remover">
                    <input type="hidden" name="id" value="<?php echo (int) $departamento['id']; ?>">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Remover equipa</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php if ((int) $departamento['total_funcionarios'] > 0): ?>
                            <p class="mb-0">
                                Esta equipa tem <strong><?php echo (int) $departamento['total_funcionarios']; ?></strong>
                                funcionário(s) associado(s), por isso não pode ser removida.
                            </p>
                        <?php else: ?>
                            <p class="mb-0">Tem a certeza que pretende remover <strong><?php echo e($departamento['nome']); ?></strong>?</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <?php if ((int) $departamento['total_funcionarios'] === 0): ?>
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
            $('#tabela-equipas').DataTable({
                pageLength: 10,
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhuma equipa encontrada',
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
