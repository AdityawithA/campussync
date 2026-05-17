<?php
$pageTitle = 'Notifications';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireLogin();

$uid = (int)currentUser()['id'];

// Mark all as read
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $uid");

$notifications = $conn->query("
    SELECT n.*, nt.title AS notice_title, nt.category
    FROM notifications n
    JOIN notices nt ON n.notice_id = nt.id
    WHERE n.user_id = $uid
    ORDER BY n.created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container">
    <h1 class="page-heading">Notifications</h1>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <span>🔔</span>
            <p>No notifications yet. Join some groups to get updates!</p>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifications as $n): ?>
                <a href="<?= BASE_URL ?>/notices/view.php?id=<?= $n['notice_id'] ?>" class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                    <span class="category-badge cat-<?= $n['category'] ?>"><?= ucfirst($n['category']) ?></span>
                    <span class="notif-title"><?= htmlspecialchars($n['notice_title']) ?></span>
                    <span class="notif-time"><?= date('d M, h:i A', strtotime($n['created_at'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
