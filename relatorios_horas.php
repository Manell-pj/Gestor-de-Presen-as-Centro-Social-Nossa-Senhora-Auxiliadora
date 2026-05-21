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

function relatorios_horas_csv($filename, $headers, $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');

    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }

    fclose($out);
    exit;
}

$anoAtual = (int) date('Y');
$mesAtual = (int) date('n');
$ano = (int) ($_GET['ano'] ?? $anoAtual);
$mes = (int) ($_GET['mes'] ?? $mesAtual);
$funcionarioId = (int) ($_GET['funcionario_id'] ?? 0);
$exportar = ($_GET['exportar'] ?? '') === 'csv';

if ($ano < 2000 || $ano > 2100) {
    $ano = $anoAtual;
}

if ($mes < 1 || $mes > 12) {
    $mes = $mesAtual;
}

[$dataInicio, $dataFim] = ht_periodo_mensal($ano, $mes);
$todosFuncionarios = ht_carregar_funcionarios($conn);
$relatorio = ht_carregar_horas_trabalhadas($conn, $dataInicio, $dataFim, $funcionarioId > 0 ? $funcionarioId : null);
$totalMinutos = 0;
$totalDias = 0;

foreach ($relatorio as $funcionario) {
    $totalMinutos += (int) $funcionario['minutos_trabalhados'];
    $totalDias += (int) $funcionario['dias_trabalhados'];
}

if ($exportar) {
    if ($funcionarioId > 0) {
        $funcionario = reset($relatorio);
        $rows = [];

        if ($funcionario) {
            foreach ($funcionario['dias'] as $dia) {
                $rows[] = [
                    date('d/m/Y', strtotime($dia['data'])),
                    ht_formatar_minutos($dia['minutos_trabalhados']),
                    $dia['picagens'],
                    $dia['primeira_picagem'] ? date('H:i', strtotime($dia['primeira_picagem'])) : '',
                    $dia['ultima_picagem'] ? date('H:i', strtotime($dia['ultima_picagem'])) : '',
                ];
            }
        }

        relatorios_horas_csv(
            sprintf('relatorio_horas_%04d_%02d_funcionario_%d.csv', $ano, $mes, $funcionarioId),
            ['Data', 'Horas trabalhadas', 'Picagens', 'Primeira picagem', 'Ultima picagem'],
            $rows
        );
    }

    $rows = [];
    foreach ($relatorio as $funcionario) {
        $rows[] = [
            $funcionario['numero_mecanografico'],
            $funcionario['nome'],
            $funcionario['equipa_nome'],
            $funcionario['dias_trabalhados'],
            ht_formatar_minutos($funcionario['minutos_trabalhados']),
        ];
    }

    relatorios_horas_csv(
        sprintf('relatorio_geral_horas_%04d_%02d.csv', $ano, $mes),
        ['Numero mecanografico', 'Funcionario', 'Equipa', 'Dias trabalhados', 'Horas trabalhadas'],
        $rows
    );
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
                        <h3 class="fw-bold mb-3">Relatórios de Horas</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="principal.php"><i class="icon-home"></i></a>
                            </li>
                            <li class="separator"><i class="icon-arrow-right"></i></li>
                            <li class="nav-item"><a href="relatorios_horas.php">Relatórios de Horas</a></li>
                        </ul>
                    </div>

                    <div class="card mb-4 no-print">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Relatório mensal</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end">
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
                                <div class="col-md-4">
                                    <label class="form-label">Funcionário</label>
                                    <select name="funcionario_id" class="form-select">
                                        <option value="0">Relatório geral</option>
                                        <?php foreach ($todosFuncionarios as $funcionario): ?>
                                            <option value="<?php echo (int) $funcionario['id']; ?>" <?php echo (int) $funcionario['id'] === $funcionarioId ? 'selected' : ''; ?>>
                                                <?php echo e($funcionario['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fa fa-filter"></i>
                                        Ver relatório
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="exportar" value="csv" class="btn btn-secondary w-100">
                                        <i class="fa fa-download"></i>
                                        Exportar CSV
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 col-md-4">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Período</p>
                                        <h4 class="card-title"><?php echo e(sprintf('%02d/%04d', $mes, $ano)); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Dias trabalhados</p>
                                        <h4 class="card-title"><?php echo (int) $totalDias; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Horas trabalhadas</p>
                                        <h4 class="card-title"><?php echo e(ht_formatar_minutos($totalMinutos)); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <h4 class="card-title">
                                    <?php echo $funcionarioId > 0 ? 'Relatório mensal do funcionário' : 'Relatório geral mensal'; ?>
                                </h4>
                                <button type="button" class="btn btn-secondary btn-round ms-auto no-print" onclick="window.print()">
                                    <i class="fa fa-print"></i>
                                    Imprimir
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($funcionarioId > 0): ?>
                                <?php $funcionario = reset($relatorio); ?>
                                <?php if (!$funcionario): ?>
                                    <div class="alert alert-info mb-0">Funcionário não encontrado.</div>
                                <?php else: ?>
                                    <div class="mb-3">
                                        <h5 class="mb-1"><?php echo e($funcionario['nome']); ?></h5>
                                        <span class="text-muted">
                                            <?php echo e($funcionario['numero_mecanografico'] ?: 'Sem número mecanográfico'); ?>
                                            <?php if ($funcionario['equipa_nome']): ?>
                                                · <?php echo e($funcionario['equipa_nome']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="table-responsive">
                                        <table id="tabela-relatorio-funcionario" class="display table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Horas trabalhadas</th>
                                                    <th>Picagens</th>
                                                    <th>Primeira picagem</th>
                                                    <th>Última picagem</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($funcionario['dias'] as $dia): ?>
                                                    <tr>
                                                        <td><?php echo e(date('d/m/Y', strtotime($dia['data']))); ?></td>
                                                        <td><span class="badge badge-primary"><?php echo e(ht_formatar_minutos($dia['minutos_trabalhados'])); ?></span></td>
                                                        <td><?php echo (int) $dia['picagens']; ?></td>
                                                        <td><?php echo $dia['primeira_picagem'] ? e(date('H:i', strtotime($dia['primeira_picagem']))) : '-'; ?></td>
                                                        <td><?php echo $dia['ultima_picagem'] ? e(date('H:i', strtotime($dia['ultima_picagem']))) : '-'; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="tabela-relatorio-geral" class="display table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>N.º mec.</th>
                                                <th>Funcionário</th>
                                                <th>Equipa</th>
                                                <th>Dias trabalhados</th>
                                                <th>Horas trabalhadas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($relatorio as $funcionario): ?>
                                                <tr>
                                                    <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                    <td><?php echo e($funcionario['nome']); ?></td>
                                                    <td><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                                                    <td><?php echo (int) $funcionario['dias_trabalhados']; ?></td>
                                                    <td><span class="badge badge-primary"><?php echo e(ht_formatar_minutos($funcionario['minutos_trabalhados'])); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <style>
        @media print {
            .no-print,
            .sidebar,
            .main-header,
            footer {
                display: none !important;
            }

            .main-panel,
            .container,
            .page-inner {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
    </style>
    <script>
        $(document).ready(function () {
            $('#tabela-relatorio-geral, #tabela-relatorio-funcionario').DataTable({
                pageLength: 31,
                order: [[0, 'asc']],
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
