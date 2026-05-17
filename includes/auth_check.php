<?php
// Reusable session guard — include at top of any protected page
if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function requireRole(...$roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . BASE_URL . '/dashboard/index.php?error=unauthorized');
        exit;
    }
}

function currentUser() {
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'name'       => $_SESSION['name'] ?? '',
        'role'       => $_SESSION['role'] ?? 'student',
        'department' => $_SESSION['department'] ?? '',
    ];
}
