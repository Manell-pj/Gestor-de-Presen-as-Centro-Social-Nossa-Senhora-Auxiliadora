<?php
session_start();
require_once 'config.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($type, $message)
{
    header('Location: ausencias.php?' . http_build_query([
        'type' => $type,
        'message' => $message,
    ]));
    exit;
}

function estado_label($estado)
{
    $labels = [
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovado',
        'rejeitado' => 'Recusado',
        'cancelado' => 'Cancelado',
    ];

    return $labels[$estado] ?? ucfirst((string) $estado);
}

function estado_badge($estado)
{
    $classes = [
        'pendente' => 'warning',
        'aprovado' => 'success',
        'rejeitado' => 'danger',
        'cancelado' => 'secondary',
    ];

    return $classes[$estado] ?? 'secondary';
}

function pode_aprovar($conn, $utilizadorId)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total
        FROM utilizador_papeis up
        INNER JOIN papeis p ON p.id = up.papel_id
        WHERE up.utilizador_id = ? AND p.slug IN ('administrador', 'chefia', 'recursos-humanos')");
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function guardar_anexo_seguro($file)
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nao foi possivel carregar o anexo.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('O anexo nao pode exceder 5 MB.');
    }

    $extensoesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    $mimePermitidos = ['application/pdf', 'image/jpeg', 'image/png'];
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        throw new RuntimeException('Formato de anexo invalido. Use PDF, JPG ou PNG.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $mimePermitidos, true)) {
        throw new RuntimeException('O conteudo do ficheiro nao corresponde a um formato permitido.');
    }

    $diretorio = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ausencias';

    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true)) {
        throw new RuntimeException('Nao foi possivel preparar a pasta de anexos.');
    }

    $nomeSeguro = bin2hex(random_bytes(16)) . '.' . $extensao;
    $destino = $diretorio . DIRECTORY_SEPARATOR . $nomeSeguro;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        throw new RuntimeException('Nao foi possivel guardar o anexo.');
    }

    return 'uploads/ausencias/' . $nomeSeguro;
}

function garantir_tipos_ausencia($conn)
{
    $tipos = [
        ['Ferias', 'ferias', 'Pedido de ferias', 1, 1, 0],
        ['Falta Justificada', 'falta-justificada', 'Falta com justificacao', 1, 0, 1],
        ['Baixa Medica', 'baixa-medica', 'Baixa por motivo de saude', 1, 0, 1],
        ['Folga', 'folga', 'Pedido de folga', 1, 0, 0],
    ];

    foreach ($tipos as $tipo) {
        [$nome, $slug, $descricao, $remunerada, $descontaFerias, $exigeJustificativo] = $tipo;

        $stmt = mysqli_prepare($conn, 'SELECT id FROM tipos_ausencia WHERE slug = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $slug);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existe = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$existe) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO tipos_ausencia (nome, slug, descricao, remunerada, desconta_ferias, exige_justificativo) VALUES (?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssiii', $nome, $slug, $descricao, $remunerada, $descontaFerias, $exigeJustificativo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

$utilizadorAutenticadoId = (int) ($_SESSION['utilizador_id'] ?? $_SESSION['user_id'] ?? 0);
$utilizadorAutenticado = null;
$temPermissaoAprovar = false;

if ($utilizadorAutenticadoId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, nome, email, estado FROM utilizadores WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $utilizadorAutenticado = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($utilizadorAutenticado) {
        $temPermissaoAprovar = pode_aprovar($conn, $utilizadorAutenticadoId);
    }
}

garantir_tipos_ausencia($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!$utilizadorAutenticado || $utilizadorAutenticado['estado'] !== 'ativo') {
        redirect_with_message('danger', 'Precisa de estar autenticado com um utilizador ativo.');
    }

    if ($acao === 'pedir') {
        $tipoAusenciaId = (int) ($_POST['tipo_ausencia_id'] ?? 0);
        $dataInicio = trim($_POST['data_inicio'] ?? '');
        $dataFim = trim($_POST['data_fim'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');

        if ($tipoAusenciaId <= 0 || $dataInicio === '' || $dataFim === '' || $motivo === '') {
            redirect_with_message('danger', 'Preencha todos os campos obrigatorios.');
        }

        if ($dataFim < $dataInicio) {
            redirect_with_message('danger', 'A data fim nao pode ser anterior a data inicio.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT id FROM tipos_ausencia WHERE id = ? AND ativo = 1 LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $tipoAusenciaId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tipoExiste = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$tipoExiste) {
            redirect_with_message('danger', 'Tipo de ausencia invalido.');
        }

        try {
            $anexo = guardar_anexo_seguro($_FILES['anexo'] ?? null);
            $dataInicioObj = new DateTime($dataInicio);
            $dataFimObj = new DateTime($dataFim);
            $totalDias = (float) $dataInicioObj->diff($dataFimObj)->days + 1;

            $stmt = mysqli_prepare($conn, "INSERT INTO pedidos_ausencia
                (utilizador_id, tipo_ausencia_id, data_inicio, data_fim, total_dias, motivo, ficheiro_justificativo, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')");
            mysqli_stmt_bind_param($stmt, 'iissdss', $utilizadorAutenticadoId, $tipoAusenciaId, $dataInicio, $dataFim, $totalDias, $motivo, $anexo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirect_with_message('success', 'Pedido registado com sucesso. Ficou pendente de aprovacao.');
        } catch (RuntimeException $e) {
            redirect_with_message('danger', $e->getMessage());
        } catch (mysqli_sql_exception $e) {
            redirect_with_message('danger', 'Nao foi possivel registar o pedido.');
        }
    }

    if ($acao === 'aprovar' || $acao === 'recusar') {
        if (!$temPermissaoAprovar) {
            redirect_with_message('danger', 'Nao tem permissao para aprovar ou recusar pedidos.');
        }

        $pedidoId = (int) ($_POST['id'] ?? 0);
        $novoEstado = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
        $observacoes = trim($_POST['observacoes_aprovacao'] ?? '');

        if ($pedidoId <= 0) {
            redirect_with_message('danger', 'Pedido invalido.');
        }

        $stmt = mysqli_prepare($conn, "UPDATE pedidos_ausencia
            SET estado = ?, aprovado_por = ?, aprovado_at = NOW(), observacoes_aprovacao = ?
            WHERE id = ? AND estado = 'pendente'");
        mysqli_stmt_bind_param($stmt, 'sisi', $novoEstado, $utilizadorAutenticadoId, $observacoes, $pedidoId);
        mysqli_stmt_execute($stmt);
        $linhasAfetadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($linhasAfetadas < 1) {
            redirect_with_message('danger', 'O pedido ja foi tratado ou nao existe.');
        }

        redirect_with_message('success', $acao === 'aprovar' ? 'Pedido aprovado com sucesso.' : 'Pedido recusado com sucesso.');
    }
}

$tiposAusencia = [];
$stmt = mysqli_prepare($conn, "SELECT id, nome, slug
    FROM tipos_ausencia
    WHERE ativo = 1 AND slug IN ('ferias', 'falta-justificada', 'baixa-medica', 'folga')
    ORDER BY FIELD(slug, 'ferias', 'falta-justificada', 'baixa-medica', 'folga')");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $tiposAusencia[] = $row;
}
mysqli_stmt_close($stmt);

$pedidos = [];

if ($utilizadorAutenticado) {
    if ($temPermissaoAprovar) {
        $sql = "SELECT pa.*, ta.nome AS tipo_nome, u.nome AS utilizador_nome, aprovador.nome AS aprovador_nome
            FROM pedidos_ausencia pa
            INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id
            INNER JOIN utilizadores u ON u.id = pa.utilizador_id
            LEFT JOIN utilizadores aprovador ON aprovador.id = pa.aprovado_por
            ORDER BY pa.created_at DESC, pa.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
    } else {
        $sql = "SELECT pa.*, ta.nome AS tipo_nome, u.nome AS utilizador_nome, aprovador.nome AS aprovador_nome
            FROM pedidos_ausencia pa
            INNER JOIN tipos_ausencia ta ON ta.id = pa.tipo_ausencia_id
            INNER JOIN utilizadores u ON u.id = pa.utilizador_id
            LEFT JOIN utilizadores aprovador ON aprovador.id = pa.aprovado_por
            WHERE pa.utilizador_id = ?
            ORDER BY pa.created_at DESC, pa.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $pedidos[] = $row;
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
                        <h3 class="fw-bold mb-3">Ausencias</h3>
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
                                <a href="ausencias.php">Ausencias</a>
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
                                <h4 class="card-title">
                                    Historico de pedidos
                                    <?php if ($temPermissaoAprovar): ?>
                                        <span class="badge badge-primary ms-2">Aprovacao</span>
                                    <?php endif; ?>
                                </h4>
                                <button class="btn btn-primary btn-round ms-auto" data-bs-toggle="modal" data-bs-target="#modalPedirAusencia" <?php echo !$utilizadorAutenticado ? 'disabled' : ''; ?>>
                                    <i class="fa fa-plus"></i>
                                    Novo pedido
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-ausencias" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <?php if ($temPermissaoAprovar): ?>
                                                <th>Colaborador</th>
                                            <?php endif; ?>
                                            <th>Tipo</th>
                                            <th>Inicio</th>
                                            <th>Fim</th>
                                            <th>Dias</th>
                                            <th>Estado</th>
                                            <th>Anexo</th>
                                            <?php if ($temPermissaoAprovar): ?>
                                                <th style="width: 130px">Acoes</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $pedido): ?>
                                            <tr>
                                                <?php if ($temPermissaoAprovar): ?>
                                                    <td><?php echo e($pedido['utilizador_nome']); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo e($pedido['tipo_nome']); ?></td>
                                                <td><?php echo e(date('d/m/Y', strtotime($pedido['data_inicio']))); ?></td>
                                                <td><?php echo e(date('d/m/Y', strtotime($pedido['data_fim']))); ?></td>
                                                <td><?php echo e($pedido['total_dias']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(estado_badge($pedido['estado'])); ?>">
                                                        <?php echo e(estado_label($pedido['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($pedido['ficheiro_justificativo']): ?>
                                                        <a href="<?php echo e($pedido['ficheiro_justificativo']); ?>" target="_blank">Ver</a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($temPermissaoAprovar): ?>
                                                    <td>
                                                        <?php if ($pedido['estado'] === 'pendente'): ?>
                                                            <div class="form-button-action">
                                                                <button type="button" class="btn btn-link btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#modalAprovarPedido<?php echo (int) $pedido['id']; ?>" title="Aprovar">
                                                                    <i class="fa fa-check"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-link btn-danger" data-bs-toggle="modal" data-bs-target="#modalRecusarPedido<?php echo (int) $pedido['id']; ?>" title="Recusar">
                                                                    <i class="fa fa-times"></i>
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Tratado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
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

    <div class="modal fade" id="modalPedirAusencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="post" enctype="multipart/form-data" class="modal-content needs-validation" novalidate>
                <input type="hidden" name="acao" value="pedir">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Novo pedido de ausencia</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo_ausencia_id" class="form-select" required>
                                <option value="">Selecionar tipo</option>
                                <?php foreach ($tiposAusencia as $tipo): ?>
                                    <option value="<?php echo (int) $tipo['id']; ?>"><?php echo e($tipo['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Selecione o tipo de ausencia.</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data inicio *</label>
                            <input type="date" name="data_inicio" class="form-control" required>
                            <div class="invalid-feedback">Indique a data inicio.</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data fim *</label>
                            <input type="date" name="data_fim" class="form-control" required>
                            <div class="invalid-feedback">Indique a data fim.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Motivo *</label>
                            <textarea name="motivo" class="form-control" rows="4" required></textarea>
                            <div class="invalid-feedback">Indique o motivo.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Anexo</label>
                            <input type="file" name="anexo" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">PDF, JPG ou PNG ate 5 MB.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Submeter pedido</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($temPermissaoAprovar): ?>
        <?php foreach ($pedidos as $pedido): ?>
            <?php if ($pedido['estado'] !== 'pendente') {
                continue;
            } ?>
            <div class="modal fade" id="modalAprovarPedido<?php echo (int) $pedido['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="post" class="modal-content">
                        <input type="hidden" name="acao" value="aprovar">
                        <input type="hidden" name="id" value="<?php echo (int) $pedido['id']; ?>">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Aprovar pedido</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Confirmar aprovacao do pedido de <strong><?php echo e($pedido['utilizador_nome']); ?></strong>?</p>
                            <label class="form-label">Observacoes</label>
                            <textarea name="observacoes_aprovacao" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-success">Aprovar</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="modalRecusarPedido<?php echo (int) $pedido['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="post" class="modal-content">
                        <input type="hidden" name="acao" value="recusar">
                        <input type="hidden" name="id" value="<?php echo (int) $pedido['id']; ?>">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Recusar pedido</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Confirmar recusa do pedido de <strong><?php echo e($pedido['utilizador_nome']); ?></strong>?</p>
                            <label class="form-label">Motivo da recusa</label>
                            <textarea name="observacoes_aprovacao" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="submit" class="btn btn-danger">Recusar</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-ausencias').DataTable({
                pageLength: 10,
                order: [[<?php echo $temPermissaoAprovar ? 2 : 1; ?>, 'desc']],
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum pedido encontrado',
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
