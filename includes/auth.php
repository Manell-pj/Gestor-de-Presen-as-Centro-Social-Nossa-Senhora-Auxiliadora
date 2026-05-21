<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function auth_user($conn)
{
    $utilizadorId = (int) ($_SESSION['utilizador_id'] ?? 0);

    if ($utilizadorId <= 0) {
        return null;
    }

    $stmt = mysqli_prepare($conn, "SELECT id, nome, email, estado FROM utilizadores WHERE id = ? AND estado = 'ativo' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $utilizadorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $utilizador = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$utilizador) {
        unset($_SESSION['utilizador_id'], $_SESSION['utilizador_nome']);
        return null;
    }

    return $utilizador;
}

function require_login($conn)
{
    $utilizador = auth_user($conn);

    if ($utilizador) {
        return $utilizador;
    }

    $destino = $_SERVER['REQUEST_URI'] ?? 'principal.php';
    header('Location: login.php?' . http_build_query(['redirect' => $destino]));
    exit;
}

function redirect_if_logged_in($conn)
{
    if (auth_user($conn)) {
        header('Location: principal.php');
        exit;
    }
}

function auth_safe_redirect($redirect)
{
    $redirect = trim((string) $redirect);

    if ($redirect === '' || preg_match('#^https?://#i', $redirect) || str_starts_with($redirect, '//')) {
        return 'principal.php';
    }

    return $redirect;
}
