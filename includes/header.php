<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

$user = [
    'id'   => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['name'] ?? '',
    'role' => $_SESSION['role'] ?? '',
];

// Unread notification count
$unread = 0;
if ($user['id']) {
    $uid = (int)$user['id'];
    $res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $uid AND is_read = 0");
    $unread = $res ? $res->fetch_assoc()['cnt'] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusSync<?= isset($pageTitle) ? ' — ' . htmlspecialchars($pageTitle) : '' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="<?= BASE_URL ?>/dashboard/index.php" class="nav-brand">
        <span class="brand-icon">⬡</span> CampusSync
    </a>
    <div class="nav-links">
        <a href="<?= BASE_URL ?>/dashboard/index.php">Feed</a>
        <a href="<?= BASE_URL ?>/groups/list.php">Groups</a>
        <?php if (in_array($user['role'], ['faculty', 'admin']) && ($_SESSION['approval'] ?? 'approved') === 'approved'): ?>
            <a href="<?= BASE_URL ?>/notices/create.php" class="btn-post">+ Post Notice</a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>/admin/index.php">Admin</a>
        <?php endif; ?>
    </div>
    <div class="nav-right">
        <?php if ($user['id']): ?>
            <a href="<?= BASE_URL ?>/dashboard/notifications.php" class="notif-btn">
                🔔 <?php if ($unread > 0): ?><span class="badge"><?= $unread ?></span><?php endif; ?>
            </a>
            <div class="nav-user">
                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                <span class="role-tag <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
            </div>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="btn-logout">Logout</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/auth/login.php" class="btn-login">Login</a>
        <?php endif; ?>
    </div>
</nav>
<main class="main-content">
