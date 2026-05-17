<?php
$pageTitle = 'Notice';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireLogin();

$uid       = (int)currentUser()['id'];
$notice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$notice_id) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT n.*, u.name AS author_name, u.role AS author_role, g.name AS group_name
    FROM notices n
    JOIN users u ON n.posted_by = u.id
    LEFT JOIN groups g ON n.group_id = g.id
    WHERE n.id = ?
");
$stmt->bind_param('i', $notice_id);
$stmt->execute();
$notice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$notice) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

// Handle comment submission
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $body = trim($_POST['comment']);
    if (empty($body)) {
        $commentError = 'Comment cannot be empty.';
    } else {
        $cs = $conn->prepare("INSERT INTO comments (notice_id, user_id, body) VALUES (?, ?, ?)");
        $cs->bind_param('iis', $notice_id, $uid, $body);
        $cs->execute();
        $cs->close();
        header('Location: ' . BASE_URL . '/notices/view.php?id=' . $notice_id . '#comments');
        exit;
    }
}

// Handle delete (only poster or admin)
if (isset($_GET['delete']) && (currentUser()['role'] === 'admin' || $notice['posted_by'] == $uid)) {
    $conn->query("DELETE FROM notices WHERE id = $notice_id");
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

// Load comments
$comments = $conn->query("
    SELECT c.*, u.name AS commenter_name, u.role AS commenter_role
    FROM comments c JOIN users u ON c.user_id = u.id
    WHERE c.notice_id = $notice_id
    ORDER BY c.created_at ASC
")->fetch_all(MYSQLI_ASSOC);

// Reaction count
$reactions = $conn->query("SELECT COUNT(*) as cnt FROM reactions WHERE notice_id = $notice_id")->fetch_assoc()['cnt'];
$userReaction = $conn->query("SELECT type FROM reactions WHERE notice_id = $notice_id AND user_id = $uid")->fetch_assoc();

$currentUser = currentUser();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container">
    <?php if (isset($_GET['posted'])): ?>
        <div class="alert alert-success">Notice posted successfully and members have been notified!</div>
    <?php endif; ?>

    <article class="notice-full cat-border-<?= $notice['category'] ?>">
        <div class="notice-meta">
            <span class="category-badge cat-<?= $notice['category'] ?>"><?= ucfirst($notice['category']) ?></span>
            <?php if ($notice['group_name']): ?>
                <span class="group-badge"># <?= htmlspecialchars($notice['group_name']) ?></span>
            <?php endif; ?>
            <span class="notice-time"><?= date('d M Y, h:i A', strtotime($notice['created_at'])) ?></span>
        </div>

        <h1 class="notice-title-full"><?= htmlspecialchars($notice['title']) ?></h1>

        <div class="author-row">
            <span>Posted by <strong><?= htmlspecialchars($notice['author_name']) ?></strong></span>
            <span class="role-tag <?= $notice['author_role'] ?>"><?= ucfirst($notice['author_role']) ?></span>
        </div>

        <div class="notice-body-full">
            <?= nl2br(htmlspecialchars($notice['body'])) ?>
        </div>

        <div class="notice-interact">
            <button class="react-btn-lg <?= $userReaction ? 'reacted' : '' ?>" data-id="<?= $notice_id ?>">
                👍 <?= $reactions ?> <?= $reactions == 1 ? 'Reaction' : 'Reactions' ?>
            </button>
            <?php if ($currentUser['role'] === 'admin' || $notice['posted_by'] == $uid): ?>
                <a href="<?= BASE_URL ?>/notices/view.php?id=<?= $notice_id ?>&delete=1"
                   class="btn-danger" onclick="return confirm('Delete this notice?')">Delete Notice</a>
            <?php endif; ?>
        </div>
    </article>

    <!-- Comments Section -->
    <section class="comments-section" id="comments">
        <h2>Comments (<?= count($comments) ?>)</h2>

        <?php if ($commentError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($commentError) ?></div>
        <?php endif; ?>

        <form method="POST" class="comment-form">
            <textarea name="comment" rows="3" placeholder="Write a comment..."></textarea>
            <button type="submit" class="btn-primary">Post Comment</button>
        </form>

        <?php if (empty($comments)): ?>
            <p class="muted-text">No comments yet. Be the first to comment!</p>
        <?php else: ?>
            <?php foreach ($comments as $c): ?>
                <div class="comment-card">
                    <div class="comment-header">
                        <strong><?= htmlspecialchars($c['commenter_name']) ?></strong>
                        <span class="role-tag <?= $c['commenter_role'] ?>"><?= ucfirst($c['commenter_role']) ?></span>
                        <span class="notif-time"><?= date('d M, h:i A', strtotime($c['created_at'])) ?></span>
                    </div>
                    <p><?= nl2br(htmlspecialchars($c['body'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
