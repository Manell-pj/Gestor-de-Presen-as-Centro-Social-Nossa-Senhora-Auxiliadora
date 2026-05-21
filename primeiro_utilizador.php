<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function total_utilizadores($conn)
{
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM utilizadores');
    $row = $result ? mysqli_fetch_assoc($result) : ['total' => 0];
    return (int) ($row['total'] ?? 0);
}

if (total_utilizadores($conn) > 0) {
    header('Location: login.php');
    exit;
}

$erro = '';
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmarPassword = $_POST['confirmar_password'] ?? '';

    if ($nome === '' || $email === '' || $password === '') {
        $erro = 'Preencha nome, email e palavra-passe.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Introduza um email válido.';
    } elseif ($password !== $confirmarPassword) {
        $erro = 'As palavras-passe não coincidem.';
    } elseif (strlen($password) < 8) {
        $erro = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif (total_utilizadores($conn) > 0) {
        header('Location: login.php');
        exit;
    } else {
        mysqli_begin_transaction($conn);

        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn, "INSERT INTO utilizadores (nome, email, password_hash, estado) VALUES (?, ?, ?, 'ativo')");
            mysqli_stmt_bind_param($stmt, 'sss', $nome, $email, $passwordHash);
            mysqli_stmt_execute($stmt);
            $utilizadorId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "SELECT id FROM papeis WHERE slug = 'administrador' LIMIT 1");
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $papel = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($papel) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO utilizador_papeis (utilizador_id, papel_id) VALUES (?, ?)');
                mysqli_stmt_bind_param($stmt, 'ii', $utilizadorId, $papel['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            mysqli_commit($conn);

            session_regenerate_id(true);
            $_SESSION['utilizador_id'] = (int) $utilizadorId;
            $_SESSION['utilizador_nome'] = $nome;

            header('Location: principal.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            $erro = 'Não foi possível criar o primeiro utilizador.';
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
            <h3 class="text-center">Primeiro utilizador</h3>

            <?php if ($erro !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo e($erro); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="login-form">
                <div class="form-group">
                    <label for="nome"><b>Nome</b></label>
                    <input id="nome" name="nome" type="text" class="form-control" value="<?php echo e($nome); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email"><b>Email</b></label>
                    <input id="email" name="email" type="email" class="form-control" value="<?php echo e($email); ?>" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label for="password"><b>Palavra-passe</b></label>
                    <input id="password" name="password" type="password" class="form-control" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="confirmar_password"><b>Confirmar palavra-passe</b></label>
                    <input id="confirmar_password" name="confirmar_password" type="password" class="form-control" autocomplete="new-password" required>
                </div>
                <div class="form-action mb-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Criar e entrar</button>
                </div>
                <div class="text-center">
                    <a href="login.php" class="link-primary">Voltar ao login</a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
</body>

</html>
