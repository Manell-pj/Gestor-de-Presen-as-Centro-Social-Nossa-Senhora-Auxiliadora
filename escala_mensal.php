<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/funcoes/escala_mensal_funcoes.php';

$utilizadorSessao = require_login($conn);

$contexto = escala_mensal_contexto_request();
$ano = $contexto['ano'];
$mes = $contexto['mes'];
$equipaId = $contexto['equipa_id'];
$diasNoMes = $contexto['dias_no_mes'];
$baseParams = escala_mensal_base_params($contexto);
$tiposDia = escala_mensal_tipos_dia();
$missingTables = escala_mensal_tabelas_em_falta($conn);

escala_mensal_processar_post($conn, $contexto, $missingTables);

$dadosEscala = escala_mensal_carregar_dados($conn, $contexto, $missingTables);
$equipas = $dadosEscala['equipas'];
$turnos = $dadosEscala['turnos'];
$funcionarios = $dadosEscala['funcionarios'];
$escalaGuardada = $dadosEscala['escala_guardada'];
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
                        <h3 class="fw-bold mb-3">Escala Mensal</h3>
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
                                <a href="escala_mensal.php">Escala Mensal</a>
                            </li>
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
                            Faltam tabelas de base: <strong><?php echo e(implode(', ', $missingTables)); ?></strong>.
                            Execute a migration <code>database/2026_05_15_lar_idosos_assiduidade.sql</code> antes de usar esta pagina.
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Filtros</h4>
                        </div>
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">Mes</label>
                                    <select name="mes" class="form-select">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i === $mes ? 'selected' : ''; ?>>
                                                <?php echo e(month_name($i)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ano</label>
                                    <input type="number" name="ano" class="form-control" min="2000" max="2100" value="<?php echo (int) $ano; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Equipa</label>
                                    <select name="equipa_id" class="form-select">
                                        <option value="0">Todas as equipas</option>
                                        <?php foreach ($equipas as $equipa): ?>
                                            <option value="<?php echo (int) $equipa['id']; ?>" <?php echo (int) $equipa['id'] === $equipaId ? 'selected' : ''; ?>>
                                                <?php echo e($equipa['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fa fa-filter"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="acao" value="guardar">
                        <input type="hidden" name="mes" value="<?php echo (int) $mes; ?>">
                        <input type="hidden" name="ano" value="<?php echo (int) $ano; ?>">
                        <input type="hidden" name="equipa_id" value="<?php echo (int) $equipaId; ?>">

                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <h4 class="card-title mb-0"><?php echo e(month_name($mes)); ?> <?php echo (int) $ano; ?></h4>
                                    <div class="ms-auto d-flex align-items-center flex-wrap gap-2">
                                        <span class="badge bg-warning text-dark">Folga trabalhada</span>
                                        <span class="badge bg-info text-dark">Substituição</span>
                                        <button type="submit" class="btn btn-success" <?php echo !empty($missingTables) ? 'disabled' : ''; ?>>
                                            <i class="fa fa-save"></i>
                                            Guardar escala
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($funcionarios)): ?>
                                    <div class="alert alert-info mb-0">
                                        Nenhum funcionário encontrado para os filtros selecionados.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive escala-wrapper">
                                        <table class="table table-bordered table-sm align-middle escala-table">
                                            <thead>
                                                <tr>
                                                    <th class="escala-sticky-col">Funcionário</th>
                                                    <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                                        <?php $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia); ?>
                                                        <th class="text-center escala-dia-header <?php echo in_array(date('N', strtotime($data)), [6, 7], true) ? 'escala-fim-semana' : ''; ?>">
                                                            <div><?php echo $dia; ?></div>
                                                            <small><?php echo e(weekday_short($data)); ?></small>
                                                        </th>
                                                    <?php endfor; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($funcionarios as $funcionario): ?>
                                                    <tr>
                                                        <th class="escala-sticky-col escala-funcionario">
                                                            <div class="fw-bold"><?php echo e($funcionario['nome']); ?></div>
                                                            <small class="text-muted">
                                                                <?php echo e($funcionario['numero_mecanografico'] ?: 'Sem número'); ?>
                                                                <?php if ($funcionario['equipa_nome']): ?>
                                                                    · <?php echo e($funcionario['equipa_nome']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </th>
                                                        <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                                                            <?php
                                                            $registo = $escalaGuardada[(int) $funcionario['id']][$dia] ?? [];
                                                            $tipoDia = $registo['tipo_dia'] ?? 'turno';
                                                            $turnoSelecionado = (int) ($registo['turno_id'] ?? 0);
                                                            $substituiSelecionado = (int) ($registo['substitui_funcionario_id'] ?? 0);
                                                            $folgaTrabalhada = (int) ($registo['folga_trabalhada'] ?? 0) === 1;
                                                            $observacoes = $registo['observacoes'] ?? '';
                                                            $cellClasses = [];

                                                            if ($folgaTrabalhada) {
                                                                $cellClasses[] = 'escala-folga-trabalhada';
                                                            }

                                                            if ($tipoDia === 'substituicao') {
                                                                $cellClasses[] = 'escala-substituicao';
                                                            }
                                                            ?>
                                                            <td class="escala-cell <?php echo e(implode(' ', $cellClasses)); ?>">
                                                                <select name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][tipo_dia]" class="form-select form-select-sm escala-tipo">
                                                                    <?php foreach ($tiposDia as $tipo): ?>
                                                                        <option value="<?php echo e($tipo); ?>" <?php echo $tipo === $tipoDia ? 'selected' : ''; ?>>
                                                                            <?php echo e(tipo_label($tipo)); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>

                                                                <select name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][turno_id]" class="form-select form-select-sm mt-1 escala-turno">
                                                                    <option value="">Sem turno</option>
                                                                    <?php foreach ($turnos as $turno): ?>
                                                                        <option value="<?php echo (int) $turno['id']; ?>" <?php echo (int) $turno['id'] === $turnoSelecionado ? 'selected' : ''; ?>>
                                                                            <?php echo e($turno['codigo'] ?: $turno['nome']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>

                                                                <select name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][substitui_funcionario_id]" class="form-select form-select-sm mt-1 escala-substitui">
                                                                    <option value="">Substitui...</option>
                                                                    <?php foreach ($funcionarios as $opcaoFuncionario): ?>
                                                                        <?php if ((int) $opcaoFuncionario['id'] === (int) $funcionario['id']) {
                                                                            continue;
                                                                        } ?>
                                                                        <option value="<?php echo (int) $opcaoFuncionario['id']; ?>" <?php echo (int) $opcaoFuncionario['id'] === $substituiSelecionado ? 'selected' : ''; ?>>
                                                                            <?php echo e($opcaoFuncionario['nome']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>

                                                                <div class="form-check mt-1 escala-folga-check">
                                                                    <input class="form-check-input escala-folga-trabalhada-check" type="checkbox" name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][folga_trabalhada]" id="folga<?php echo (int) $funcionario['id']; ?>_<?php echo $dia; ?>" <?php echo $folgaTrabalhada ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="folga<?php echo (int) $funcionario['id']; ?>_<?php echo $dia; ?>">Folga trab.</label>
                                                                </div>

                                                                <input type="text" name="escala[<?php echo (int) $funcionario['id']; ?>][<?php echo $dia; ?>][observacoes]" class="form-control form-control-sm mt-1" placeholder="Obs." value="<?php echo e($observacoes); ?>">
                                                            </td>
                                                        <?php endfor; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <style>
        .escala-wrapper {
            max-height: 72vh;
            overflow: auto;
        }

        .escala-table {
            min-width: 1800px;
        }

        .escala-table th,
        .escala-table td {
            vertical-align: top;
        }

        .escala-sticky-col {
            background: #fff;
            left: 0;
            min-width: 240px;
            position: sticky;
            z-index: 3;
        }

        .escala-table thead .escala-sticky-col {
            z-index: 5;
        }

        .escala-dia-header {
            min-width: 170px;
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fff;
        }

        .escala-fim-semana {
            background: #f7f7f7;
        }

        .escala-cell {
            min-width: 170px;
            background: #fff;
        }

        .escala-folga-trabalhada {
            background: #fff3cd !important;
            border-color: #ffda6a !important;
        }

        .escala-substituicao {
            background: #cff4fc !important;
            border-color: #9eeaf9 !important;
        }

        .escala-folga-trabalhada.escala-substituicao {
            background: linear-gradient(135deg, #fff3cd 0%, #fff3cd 50%, #cff4fc 50%, #cff4fc 100%) !important;
        }

        .escala-funcionario {
            white-space: normal;
        }

        .escala-cell .form-select,
        .escala-cell .form-control {
            font-size: 0.75rem;
        }

        .escala-folga-check {
            font-size: 0.72rem;
            min-height: auto;
        }
    </style>
    <script>
        $(document).ready(function () {
            function atualizarCelula($cell) {
                var tipo = $cell.find('.escala-tipo').val();
                var folgaTrabalhada = $cell.find('.escala-folga-trabalhada-check').is(':checked');
                var mostrarSubstitui = tipo === 'substituicao';
                var mostrarTurno = tipo === 'turno' || tipo === 'substituicao' || folgaTrabalhada;

                $cell.toggleClass('escala-substituicao', mostrarSubstitui);
                $cell.toggleClass('escala-folga-trabalhada', folgaTrabalhada);
                $cell.find('.escala-substitui').toggle(mostrarSubstitui);
                $cell.find('.escala-turno').toggle(mostrarTurno);
            }

            $('.escala-cell').each(function () {
                atualizarCelula($(this));
            });

            $('.escala-tipo, .escala-folga-trabalhada-check').on('change', function () {
                atualizarCelula($(this).closest('.escala-cell'));
            });
        });
    </script>
</body>

</html>

