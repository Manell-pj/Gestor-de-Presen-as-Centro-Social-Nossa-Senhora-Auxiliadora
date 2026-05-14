<?php
session_start();
require_once 'config.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function utilizador_tem_papel($conn, $utilizadorId, $slugs)
{
    if (empty($slugs)) {
        return false;
    }

    $slugsSeguros = [];
    foreach ($slugs as $slug) {
        $slugsSeguros[] = "'" . mysqli_real_escape_string($conn, $slug) . "'";
    }

    $listaSlugs = implode(',', $slugsSeguros);

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

function contar_total_colaboradores($conn, $isAdmin, $utilizadorId)
{
    if ($isAdmin) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM utilizadores WHERE estado = 'ativo'");
    } else {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM utilizadores WHERE id = ? AND estado = 'ativo'");
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0);
}

function contar_presentes_hoje($conn, $isAdmin, $utilizadorId)
{
    $sql = "SELECT COUNT(DISTINCT rp.utilizador_id) AS total
        FROM registos_ponto rp
        INNER JOIN utilizadores u ON u.id = rp.utilizador_id
        WHERE DATE(rp.data_hora) = CURDATE()
          AND rp.tipo = 'entrada'
          AND rp.estado IN ('valido', 'corrigido')
          AND u.estado = 'ativo'";

    if ($isAdmin) {
        $stmt = mysqli_prepare($conn, $sql);
    } else {
        $stmt = mysqli_prepare($conn, $sql . ' AND rp.utilizador_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0);
}

function contar_pedidos_pendentes($conn, $isAdmin, $utilizadorId)
{
    if ($isAdmin) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM pedidos_ausencia WHERE estado = 'pendente'");
    } else {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM pedidos_ausencia WHERE estado = 'pendente' AND utilizador_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0);
}

function ultimos_meses()
{
    $meses = [];
    $inicio = new DateTime('first day of -5 months');

    for ($i = 0; $i < 6; $i++) {
        $chave = $inicio->format('Y-m');
        $meses[$chave] = [
            'label' => $inicio->format('m/Y'),
            'valor' => 0,
        ];
        $inicio->modify('+1 month');
    }

    return $meses;
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

$totalColaboradores = 0;
$presentesHoje = 0;
$ausentesHoje = 0;
$pedidosPendentes = 0;
$ultimosRegistos = [];
$absentismoMensal = ultimos_meses();
$horasExtraMensal = ultimos_meses();

if ($utilizadorAutenticado) {
    $totalColaboradores = contar_total_colaboradores($conn, $isAdmin, $utilizadorAutenticadoId);
    $presentesHoje = contar_presentes_hoje($conn, $isAdmin, $utilizadorAutenticadoId);
    $ausentesHoje = max(0, $totalColaboradores - $presentesHoje);
    $pedidosPendentes = contar_pedidos_pendentes($conn, $isAdmin, $utilizadorAutenticadoId);

    $dataInicioGrafico = date('Y-m-01', strtotime('-5 months'));

    if ($isAdmin) {
        $stmt = mysqli_prepare($conn, "SELECT DATE_FORMAT(data_inicio, '%Y-%m') AS mes, SUM(COALESCE(total_dias, DATEDIFF(data_fim, data_inicio) + 1)) AS total
            FROM pedidos_ausencia
            WHERE estado = 'aprovado' AND data_inicio >= ?
            GROUP BY DATE_FORMAT(data_inicio, '%Y-%m')");
        mysqli_stmt_bind_param($stmt, 's', $dataInicioGrafico);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT DATE_FORMAT(data_inicio, '%Y-%m') AS mes, SUM(COALESCE(total_dias, DATEDIFF(data_fim, data_inicio) + 1)) AS total
            FROM pedidos_ausencia
            WHERE estado = 'aprovado' AND data_inicio >= ? AND utilizador_id = ?
            GROUP BY DATE_FORMAT(data_inicio, '%Y-%m')");
        mysqli_stmt_bind_param($stmt, 'si', $dataInicioGrafico, $utilizadorAutenticadoId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($absentismoMensal[$row['mes']])) {
            $absentismoMensal[$row['mes']]['valor'] = (float) $row['total'];
        }
    }
    mysqli_stmt_close($stmt);

    if ($isAdmin) {
        $stmt = mysqli_prepare($conn, "SELECT DATE_FORMAT(data_movimento, '%Y-%m') AS mes, SUM(GREATEST(minutos, 0)) AS total_minutos
            FROM banco_horas
            WHERE data_movimento >= ?
            GROUP BY DATE_FORMAT(data_movimento, '%Y-%m')");
        mysqli_stmt_bind_param($stmt, 's', $dataInicioGrafico);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT DATE_FORMAT(data_movimento, '%Y-%m') AS mes, SUM(GREATEST(minutos, 0)) AS total_minutos
            FROM banco_horas
            WHERE data_movimento >= ? AND utilizador_id = ?
            GROUP BY DATE_FORMAT(data_movimento, '%Y-%m')");
        mysqli_stmt_bind_param($stmt, 'si', $dataInicioGrafico, $utilizadorAutenticadoId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($horasExtraMensal[$row['mes']])) {
            $horasExtraMensal[$row['mes']]['valor'] = round(((int) $row['total_minutos']) / 60, 2);
        }
    }
    mysqli_stmt_close($stmt);

    if ($isAdmin) {
        $stmt = mysqli_prepare($conn, "SELECT rp.tipo, rp.data_hora, rp.origem, rp.estado, u.nome AS utilizador_nome
            FROM registos_ponto rp
            INNER JOIN utilizadores u ON u.id = rp.utilizador_id
            ORDER BY rp.data_hora DESC, rp.id DESC
            LIMIT 10");
    } else {
        $stmt = mysqli_prepare($conn, "SELECT rp.tipo, rp.data_hora, rp.origem, rp.estado, u.nome AS utilizador_nome
            FROM registos_ponto rp
            INNER JOIN utilizadores u ON u.id = rp.utilizador_id
            WHERE rp.utilizador_id = ?
            ORDER BY rp.data_hora DESC, rp.id DESC
            LIMIT 10");
        mysqli_stmt_bind_param($stmt, 'i', $utilizadorAutenticadoId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $ultimosRegistos[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$labelsMeses = array_column($absentismoMensal, 'label');
$dadosAbsentismo = array_column($absentismoMensal, 'valor');
$dadosHorasExtra = array_column($horasExtraMensal, 'valor');
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
                        <h3 class="fw-bold mb-3">Dashboard</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="dashboard.php">
                                    <i class="icon-home"></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"></i>
                            </li>
                            <li class="nav-item">
                                <a href="dashboard.php">Resumo</a>
                            </li>
                        </ul>
                    </div>

                    <?php if (!$utilizadorAutenticado): ?>
                        <div class="alert alert-warning" role="alert">
                            Nao existe utilizador autenticado na sessao. Depois de criares o login, define
                            <strong>$_SESSION['utilizador_id']</strong> com o ID do utilizador autenticado.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-primary bubble-shadow-small">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Colaboradores</p>
                                                <h4 class="card-title"><?php echo (int) $totalColaboradores; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-success bubble-shadow-small">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Presentes hoje</p>
                                                <h4 class="card-title"><?php echo (int) $presentesHoje; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-danger bubble-shadow-small">
                                                <i class="fas fa-user-times"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Ausentes hoje</p>
                                                <h4 class="card-title"><?php echo (int) $ausentesHoje; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-warning bubble-shadow-small">
                                                <i class="fas fa-hourglass-half"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Pedidos pendentes</p>
                                                <h4 class="card-title"><?php echo (int) $pedidosPendentes; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Absentismo mensal</h4>
                                </div>
                                <div class="card-body">
                                    <canvas id="graficoAbsentismo" height="130"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Horas extra</h4>
                                </div>
                                <div class="card-body">
                                    <canvas id="graficoHorasExtra" height="130"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Ultimos registos de ponto</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
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
                                        <?php foreach ($ultimosRegistos as $registo): ?>
                                            <tr>
                                                <?php if ($isAdmin): ?>
                                                    <td><?php echo e($registo['utilizador_nome']); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo e(date('d/m/Y', strtotime($registo['data_hora']))); ?></td>
                                                <td><?php echo e(date('H:i:s', strtotime($registo['data_hora']))); ?></td>
                                                <td><?php echo e(ucfirst(str_replace('_', ' ', $registo['tipo']))); ?></td>
                                                <td><?php echo e(ucfirst($registo['origem'])); ?></td>
                                                <td><?php echo e(ucfirst($registo['estado'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($ultimosRegistos)): ?>
                                            <tr>
                                                <td colspan="<?php echo $isAdmin ? 6 : 5; ?>" class="text-muted">Sem registos de ponto.</td>
                                            </tr>
                                        <?php endif; ?>
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

    <?php include 'includes/scripts.php'; ?>
    <script>
        const labelsMeses = <?php echo json_encode($labelsMeses); ?>;
        const dadosAbsentismo = <?php echo json_encode($dadosAbsentismo); ?>;
        const dadosHorasExtra = <?php echo json_encode($dadosHorasExtra); ?>;

        new Chart(document.getElementById('graficoAbsentismo'), {
            type: 'bar',
            data: {
                labels: labelsMeses,
                datasets: [{
                    label: 'Dias de ausencia',
                    data: dadosAbsentismo,
                    backgroundColor: 'rgba(243, 84, 93, 0.75)',
                    borderColor: '#f3545d',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        new Chart(document.getElementById('graficoHorasExtra'), {
            type: 'line',
            data: {
                labels: labelsMeses,
                datasets: [{
                    label: 'Horas extra',
                    data: dadosHorasExtra,
                    borderColor: '#1572e8',
                    backgroundColor: 'rgba(21, 114, 232, 0.15)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>

</html>
