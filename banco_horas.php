<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';
require_once __DIR__ . '/includes/horas_trabalhadas.php';

$utilizadorSessao = require_login($conn);

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$missingTables = [];
foreach (['funcionarios', 'registos_ponto'] as $table) {
    if (!fe_table_exists($conn, $table)) {
        $missingTables[] = $table;
    }
}

$anoAtual = (int) date('Y');
$mesAtual = (int) date('n');
$ano = (int) ($_GET['ano'] ?? $anoAtual);
$mes = (int) ($_GET['mes'] ?? $mesAtual);
$periodo = $_GET['periodo'] ?? 'mes';

if ($ano < 2000 || $ano > 2100) {
    $ano = $anoAtual;
}

if ($mes < 1 || $mes > 12) {
    $mes = $mesAtual;
}

$dataInicio = null;
$dataFim = null;

if ($periodo !== 'total') {
    [$dataInicio, $dataFim] = ht_periodo_mensal($ano, $mes);
}

$funcionarios = empty($missingTables) ? ht_carregar_horas_trabalhadas($conn, $dataInicio, $dataFim) : [];
$totalMinutos = 0;
$totalDias = 0;

foreach ($funcionarios as $funcionario) {
    $totalMinutos += (int) $funcionario['minutos_trabalhados'];
    $totalDias += (int) $funcionario['dias_trabalhados'];
}
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
                                <a href="principal.php"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="banco_horas.php">Banco de Horas</a></li>
                        </ul>
                    </div>

                    <?php if (!empty($missingTables)): ?>
                        <div class="alert alert-warning" role="alert">
                            Faltam tabelas de base: <strong><?php echo e(implode(', ', $missingTables)); ?></strong>.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Funcionários</p>
                                        <h4 class="card-title"><?php echo count($funcionarios); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Horas trabalhadas</p>
                                        <h4 class="card-title"><?php echo e(ht_formatar_minutos($totalMinutos)); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Dias com trabalho</p>
                                        <h4 class="card-title"><?php echo (int) $totalDias; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Período</p>
                                        <h4 class="card-title"><?php echo $periodo === 'total' ? 'Total' : e(sprintf('%02d/%04d', $mes, $ano)); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Filtros</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Período</label>
                                    <select name="periodo" class="form-select">
                                        <option value="mes" <?php echo $periodo !== 'total' ? 'selected' : ''; ?>>Mês</option>
                                        <option value="total" <?php echo $periodo === 'total' ? 'selected' : ''; ?>>Total acumulado</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Mês</label>
                                    <select name="mes" class="form-select">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i === $mes ? 'selected' : ''; ?>>
                                                <?php echo e(sprintf('%02d', $i)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ano</label>
                                    <input type="number" name="ano" class="form-control" min="2000" max="2100" value="<?php echo (int) $ano; ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fa fa-filter"></i>
                                        Filtrar
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <a class="btn btn-secondary w-100" href="relatorios_horas.php?ano=<?php echo (int) $ano; ?>&mes=<?php echo (int) $mes; ?>">
                                        <i class="fa fa-file-alt"></i>
                                        Relatório mensal
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Horas trabalhadas por funcionário</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-banco-horas" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>N.º mec.</th>
                                            <th>Equipa</th>
                                            <th>Dias trabalhados</th>
                                            <th>Horas trabalhadas</th>
                                            <th>Primeira picagem</th>
                                            <th>Última picagem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($funcionarios as $funcionario): ?>
                                            <tr>
                                                <td><?php echo e($funcionario['nome']); ?></td>
                                                <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                <td><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                                                <td><?php echo (int) $funcionario['dias_trabalhados']; ?></td>
                                                <td><span class="badge badge-primary"><?php echo e(ht_formatar_minutos($funcionario['minutos_trabalhados'])); ?></span></td>
                                                <td><?php echo $funcionario['primeira_picagem'] ? e(date('d/m/Y H:i', strtotime($funcionario['primeira_picagem']))) : '-'; ?></td>
                                                <td><?php echo $funcionario['ultima_picagem'] ? e(date('d/m/Y H:i', strtotime($funcionario['ultima_picagem']))) : '-'; ?></td>
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

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-banco-horas').DataTable({
                pageLength: 10,
                order: [[4, 'desc']],
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
        });
    </script>
</body>

</html>
