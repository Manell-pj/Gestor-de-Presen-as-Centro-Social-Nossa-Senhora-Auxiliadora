<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funcionarios_estado.php';

$utilizadorSessao = require_login($conn);

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$estadoFuncionarios = fe_carregar_funcionarios_estado($conn);
$funcionarios = $estadoFuncionarios['funcionarios'];
$totais = $estadoFuncionarios['totais'];
$missingTables = $estadoFuncionarios['missing_tables'];
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
                        <h3 class="fw-bold mb-3">Presencas de Funcionários</h3>
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
                                <a href="dashboard.php">Estado atual</a>
                            </li>
                        </ul>
                    </div>

                    <?php if (!empty($missingTables)): ?>
                        <div class="alert alert-warning" role="alert">
                            Faltam tabelas de base: <strong><?php echo e(implode(', ', $missingTables)); ?></strong>.
                            Execute a migration <code>database/2026_05_15_lar_idosos_assiduidade.sql</code>.
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
                                                <p class="card-category">Funcionários ativos</p>
                                                <h4 class="card-title"><?php echo (int) $totais['ativos']; ?></h4>
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
                                                <p class="card-category">A trabalhar</p>
                                                <h4 class="card-title"><?php echo (int) $totais['a_trabalhar']; ?></h4>
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
                                                <i class="fas fa-coffee"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Em pausa</p>
                                                <h4 class="card-title"><?php echo (int) $totais['em_pausa']; ?></h4>
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
                                            <div class="icon-big text-center icon-secondary bubble-shadow-small">
                                                <i class="fas fa-user-clock"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Não a trabalhar</p>
                                                <h4 class="card-title"><?php echo (int) $totais['nao_trabalhar']; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Estado dos funcionários hoje</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela-funcionarios-estado" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>N.º mec.</th>
                                            <th>Funcionário</th>
                                            <th>Equipa</th>
                                            <th>Último movimento</th>
                                            <th>Hora</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($funcionarios as $funcionario): ?>
                                            <tr>
                                                <td><?php echo e($funcionario['numero_mecanografico'] ?: '-'); ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                                                    <small class="text-muted"><?php echo e($funcionario['funcao'] ?: 'Sem função definida'); ?></small>
                                                </td>
                                                <td><?php echo e($funcionario['equipa_nome'] ?: '-'); ?></td>
                                                <td><?php echo e(fe_movimento_label($funcionario['ultimo_tipo'])); ?></td>
                                                <td>
                                                    <?php echo $funcionario['ultimo_data_hora'] ? e(date('H:i', strtotime($funcionario['ultimo_data_hora']))) : '-'; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo e(fe_estado_badge($funcionario['estado_trabalho'])); ?>">
                                                        <?php echo e(fe_estado_label($funcionario['estado_trabalho'])); ?>
                                                    </span>
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

    <?php include 'includes/scripts.php'; ?>
    <script>
        $(document).ready(function () {
            $('#tabela-funcionarios-estado').DataTable({
                pageLength: 25,
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
        });
    </script>
</body>

</html>
