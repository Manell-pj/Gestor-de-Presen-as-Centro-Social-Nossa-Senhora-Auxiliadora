<?php
$nomeUtilizadorTopo = $utilizadorSessao['nome'] ?? ($_SESSION['utilizador_nome'] ?? 'Utilizador');
$emailUtilizadorTopo = $utilizadorSessao['email'] ?? '';
?>
<!-- Navbar Header -->
<nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
    <div class="container-fluid">
        <div class="navbar-form nav-search p-0 d-none d-lg-flex">
            <div class="fw-bold text-muted">Centro Social Nossa Senhora Auxiliadora</div>
        </div>

        <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
            <li class="nav-item topbar-user dropdown hidden-caret">
                <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                    <div class="avatar-sm">
                        <span class="avatar-title rounded-circle border border-white bg-primary">
                            <?php echo e(strtoupper(substr($nomeUtilizadorTopo, 0, 1))); ?>
                        </span>
                    </div>
                    <span class="profile-username">
                        <span class="op-7">Olá,</span>
                        <span class="fw-bold"><?php echo e($nomeUtilizadorTopo); ?></span>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-user animated fadeIn">
                    <div class="dropdown-user-scroll scrollbar-outer">
                        <li>
                            <div class="user-box">
                                <div class="avatar-lg">
                                    <span class="avatar-title rounded bg-primary">
                                        <?php echo e(strtoupper(substr($nomeUtilizadorTopo, 0, 1))); ?>
                                    </span>
                                </div>
                                <div class="u-text">
                                    <h4><?php echo e($nomeUtilizadorTopo); ?></h4>
                                    <?php if ($emailUtilizadorTopo !== ''): ?>
                                        <p class="text-muted"><?php echo e($emailUtilizadorTopo); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="utilizadores.php">Utilizadores</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">Sair</a>
                        </li>
                    </div>
                </ul>
            </li>
        </ul>
    </div>
</nav>
<!-- End Navbar -->
