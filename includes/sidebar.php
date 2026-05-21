<!-- Sidebar -->
<div class="sidebar" data-background-color="dark">
    <div class="sidebar-logo">
        <!-- Logo Header -->
        <div class="logo-header" data-background-color="dark">
            <a href="principal.php" class="logo">
                <img src="assets/img/kaiadmin/logo_light.svg" alt="navbar brand" class="navbar-brand" height="20" />
            </a>
            <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                    <i class="gg-menu-right"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                    <i class="gg-menu-left"></i>
                </button>
            </div>
            <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt"></i>
            </button>
        </div>
        <!-- End Logo Header -->
    </div>
    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <?php $paginaAtual = basename($_SERVER['PHP_SELF']); ?>
            <ul class="nav nav-secondary">
                <li class="nav-item <?php echo in_array($paginaAtual, ['principal.php', 'dashboard.php', 'index.php'], true) ? 'active' : ''; ?>">
                    <a href="principal.php">
                        <i class="fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item <?php echo in_array($paginaAtual, ['funcionarios.php'], true) ? 'active' : ''; ?>">
                    <a href="funcionarios.php">
                        <i class="fas fa-users"></i>
                        <p>Funcionários</p>
                    </a>
                </li>
                <li class="nav-item <?php echo $paginaAtual === 'departamentos.php' ? 'active' : ''; ?>">
                    <a href="departamentos.php">
                        <i class="fas fa-building"></i>
                        <p>Equipas</p>
                    </a>
                </li>
                <li class="nav-item <?php echo $paginaAtual === 'ponto.php' ? 'active' : ''; ?>">
                    <a href="ponto.php">
                        <i class="fas fa-clock"></i>
                        <p>Registo de Ponto</p>
                    </a>
                </li>
                <li class="nav-item <?php echo $paginaAtual === 'ausencias.php' ? 'active' : ''; ?>">
                    <a href="ausencias.php">
                        <i class="fas fa-calendar-alt"></i>
                        <p>Ausências</p>
                    </a>
                </li>
                <li class="nav-item <?php echo $paginaAtual === 'turnos.php' ? 'active' : ''; ?>">
                    <a href="turnos.php">
                        <i class="fas fa-business-time"></i>
                        <p>Turnos</p>
                    </a>
                </li>
                <li class="nav-item <?php echo $paginaAtual === 'escala_mensal.php' ? 'active' : ''; ?>">
                    <a href="escala_mensal.php">
                        <i class="fas fa-calendar-check"></i>
                        <p>Escala Mensal</p>
                    </a>
                </li>
                <li class="nav-item <?php echo $paginaAtual === 'banco_horas.php' ? 'active' : ''; ?>">
                    <a href="banco_horas.php">
                        <i class="fas fa-hourglass-half"></i>
                        <p>Banco de Horas</p>
                    </a>
                </li>
                <li class="nav-item <?php echo $paginaAtual === 'relatorios_horas.php' ? 'active' : ''; ?>">
                    <a href="relatorios_horas.php">
                        <i class="fas fa-file-alt"></i>
                        <p>Relatórios de Horas</p>
                    </a>
                </li>
                <li class="nav-item <?php echo $paginaAtual === 'utilizadores.php' ? 'active' : ''; ?>">
                    <a href="utilizadores.php">
                        <i class="fas fa-user-lock"></i>
                        <p>Utilizadores</p>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- End Sidebar -->
