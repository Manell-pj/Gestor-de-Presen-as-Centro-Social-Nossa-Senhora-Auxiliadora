<?php
session_start();
require_once 'config.php';

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
        'saida' => 'Saida',
        'inicio_pausa' => 'Inicio de pausa',
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

function movimento_permitido($ultimoTipo, $novoTipo)
{
    if ($novoTipo === 'entrada') {
        return $ultimoTipo === null || $ultimoTipo === 'saida';
    }

    if ($novoTipo === 'inicio_pausa') {
        return $ultimoTipo === 'entrada' || $ultimoTipo === 'fim_pausa';
    }

    if ($novoTipo === 'fim_pausa') {
        return $ultimoTipo === 'inicio_pausa';
    }

    if ($novoTipo === 'saida') {
        return $ultimoTipo === 'entrada' || $ultimoTipo === 'fim_pausa';
    }

    return false;
}

function mensagem_movimento_invalido($ultimoTipo, $novoTipo)
{
    if ($ultimoTipo === null && $novoTipo !== 'entrada') {
        return 'O primeiro registo deve ser uma entrada.';
    }

    if ($ultimoTipo === 'entrada' && $novoTipo === 'entrada') {
        return 'Nao pode registar duas entradas seguidas sem uma saida.';
    }

    if ($ultimoTipo === 'saida' && $novoTipo === 'saida') {
        return 'Nao pode registar duas saidas seguidas sem nova entrada.';
    }

    if ($ultimoTipo === 'inicio_pausa' && $novoTipo !== 'fim_pausa') {
        return 'Depois de iniciar a pausa deve registar o fim da pausa.';
    }

    if ($ultimoTipo === 'fim_pausa' && $novoTipo === 'fim_pausa') {
        return 'Nao pode registar dois fins de pausa seguidos.';
    }

    return 'Este movimento nao e valido tendo em conta o ultimo registo.';
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
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total
            FROM utilizador_papeis up
            INNER JOIN papeis p ON p.id = up.papel_id
            WHERE up.utilizador_id = ? AND p.slug = 'administrador'");
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        $isAdmin = (int) ($row['total'] ?? 0) > 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'registar_ponto') {
        if (!$utilizadorAutenticado || $utilizadorAutenticado['estado'] !== 'ativo') {
            redirect_with_message('danger', 'Precisa de estar autenticado com um utilizador ativo para registar ponto.');
        }

        $tipo = $_POST['tipo'] ?? '';
        $tiposPermitidos = ['entrada', 'saida', 'inicio_pausa', 'fim_pausa'];

        if (!in_array($tipo, $tiposPermitidos, true)) {
            redirect_with_message('danger', 'Tipo de movimento invalido.');
        }

        $stmt = mysqli_prepare($conn, "SELECT tipo
            FROM registos_ponto
            WHERE utilizador_id = ? AND estado IN ('valido', 'corrigido')
            ORDER BY data_hora DESC, id DESC
            LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $ultimoRegisto = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        $ultimoTipo = $ultimoRegisto['tipo'] ?? null;

        if (!movimento_permitido($ultimoTipo, $tipo)) {
            redirect_with_message('danger', mensagem_movimento_invalido($ultimoTipo, $tipo));
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO registos_ponto (utilizador_id, tipo, data_hora, origem, estado, criado_por)
            VALUES (?, ?, NOW(), 'manual', 'valido', ?)");
        mysqli_stmt_bind_param($stmt, 'isi', $utilizadorAutenticadoId, $tipo, $utilizadorAutenticadoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        redirect_with_message('success', movimento_label($tipo) . ' registada com sucesso.');
    }
}

$hoje = date('Y-m-d');
$registos = [];

if ($utilizadorAutenticado) {
    if ($isAdmin) {
        $sql = "SELECT rp.id, rp.tipo, rp.data_hora, rp.origem, rp.estado, u.nome AS utilizador_nome
            FROM registos_ponto rp
            INNER JOIN utilizadores u ON u.id = rp.utilizador_id
            WHERE DATE(rp.data_hora) = CURDATE()
            ORDER BY rp.data_hora DESC, rp.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
    } else {
        $sql = "SELECT rp.id, rp.tipo, rp.data_hora, rp.origem, rp.estado, u.nome AS utilizador_nome
            FROM registos_ponto rp
            INNER JOIN utilizadores u ON u.id = rp.utilizador_id
            WHERE rp.utilizador_id = ? AND DATE(rp.data_hora) = CURDATE()
            ORDER BY rp.data_hora DESC, rp.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
    }

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
                                <a href="ponto.php">Registo de Ponto</a>
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

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Movimentos</h4>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-4">
                                        <?php if ($utilizadorAutenticado): ?>
                                            <?php echo e($utilizadorAutenticado['nome']); ?> - <?php echo e(date('d/m/Y')); ?>
                                        <?php else: ?>
                                            Sessao nao autenticada
                                        <?php endif; ?>
                                    </p>

                                    <form method="post" class="d-grid gap-2">
                                        <input type="hidden" name="acao" value="registar_ponto">
                                        <button type="submit" name="tipo" value="entrada" class="btn btn-success" <?php echo !$utilizadorAutenticado ? 'disabled' : ''; ?>>
                                            <i class="fa fa-sign-in-alt"></i>
                                            Entrada
                                        </button>
                                        <button type="submit" name="tipo" value="inicio_pausa" class="btn btn-warning" <?php echo !$utilizadorAutenticado ? 'disabled' : ''; ?>>
                                            <i class="fa fa-coffee"></i>
                                            Inicio de pausa
                                        </button>
                                        <button type="submit" name="tipo" value="fim_pausa" class="btn btn-info" <?php echo !$utilizadorAutenticado ? 'disabled' : ''; ?>>
                                            <i class="fa fa-play"></i>
                                            Fim de pausa
                                        </button>
                                        <button type="submit" name="tipo" value="saida" class="btn btn-danger" <?php echo !$utilizadorAutenticado ? 'disabled' : ''; ?>>
                                            <i class="fa fa-sign-out-alt"></i>
                                            Saida
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex align-items-center">
                                        <h4 class="card-title">
                                            Registos de hoje
                                            <?php if ($isAdmin): ?>
                                                <span class="badge badge-primary ms-2">Todos os utilizadores</span>
                                            <?php endif; ?>
                                        </h4>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="tabela-ponto" class="display table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <?php if ($isAdmin): ?>
                                                        <th>Utilizador</th>
                                                    <?php endif; ?>
                                                    <th>Data</th>
                                                    <th>Hora</th>
                                                    <th>Movimento</th>
                                                    <th>Origem</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($registos as $registo): ?>
                                                    <?php $dataHora = new DateTime($registo['data_hora']); ?>
                                                    <tr>
                                                        <?php if ($isAdmin): ?>
                                                            <td><?php echo e($registo['utilizador_nome']); ?></td>
                                                        <?php endif; ?>
                                                        <td><?php echo e($dataHora->format('d/m/Y')); ?></td>
                                                        <td><?php echo e($dataHora->format('H:i:s')); ?></td>
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
                pageLength: 10,
                order: [[<?php echo $isAdmin ? 2 : 1; ?>, 'desc']],
                language: {
                    search: 'Pesquisar:',
                    lengthMenu: 'Mostrar _MENU_ registos',
                    info: 'A mostrar _START_ a _END_ de _TOTAL_ registos',
                    infoEmpty: 'Sem registos',
                    zeroRecords: 'Nenhum registo encontrado',
                    paginate: {
                        first: 'Primeiro',
                        last: 'Ultimo',
                        next: 'Seguinte',
                        previous: 'Anterior'
                    }
                }
            });
        });
    </script>
</body>

</html>
