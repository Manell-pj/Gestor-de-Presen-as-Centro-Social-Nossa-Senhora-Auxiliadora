<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in($conn);

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$erro = '';
$email = trim($_POST['email'] ?? '');
$redirect = auth_safe_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? 'principal.php');
$totalUtilizadores = 0;

$result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM utilizadores');
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalUtilizadores = (int) ($row['total'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $erro = 'Indique o email e a palavra-passe.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Indique um email válido.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, nome, email, password_hash, estado FROM utilizadores WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $utilizador = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$utilizador || $utilizador['estado'] !== 'ativo' || !password_verify($password, $utilizador['password_hash'])) {
            $erro = 'Email ou palavra-passe inválidos.';
        } else {
            session_regenerate_id(true);
            $_SESSION['utilizador_id'] = (int) $utilizador['id'];
            $_SESSION['utilizador_nome'] = $utilizador['nome'];

            $stmt = mysqli_prepare($conn, 'UPDATE utilizadores SET ultimo_login_at = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $utilizador['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<?php include 'includes/head.php'; ?>

<body class="login">
    <div class="wrapper wrapper-login">
        <div class="container container-login animated fadeIn">
            <div class="text-center mb-4">
                <img src="assets/img/kaiadmin/logo_dark.svg" alt="CSNSA" height="34">
            </div>
            <h3 class="text-center">Acesso ao sistema</h3>

            <?php if ($erro !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo e($erro); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="login-form">
                <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">
                <div class="form-group">
                    <label for="email"><b>Email</b></label>
                    <input id="email" name="email" type="email" class="form-control" value="<?php echo e($email); ?>" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label for="password"><b>Palavra-passe</b></label>
                    <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
                </div>
                <div class="form-action mb-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Entrar</button>
                </div>
                <?php if ($totalUtilizadores === 0): ?>
                    <div class="text-center">
                        <a href="primeiro_utilizador.php" class="link-primary">Criar primeiro utilizador</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
</body>

</html>
