<?php
$pageTitle = 'Feed';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireLogin();

$user = currentUser();
$uid  = (int)$user['id'];

// Filters
$category   = $_GET['category'] ?? '';
$search     = trim($_GET['search'] ?? '');
$group_id   = isset($_GET['group']) ? (int)$_GET['group'] : 0;

// Build query — show global notices + notices from user's groups
$where = ['1=1'];
$params = [];
$types  = '';

if ($category) {
    $where[] = 'n.category = ?';
    $params[] = $category;
    $types   .= 's';
}
if ($search) {
    $where[]  = '(n.title LIKE ? OR n.body LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types   .= 'ss';
}
if ($group_id) {
    $where[] = 'n.group_id = ?';
    $params[] = $group_id;
    $types   .= 'i';
} else {
    // Show notices: global (no group) OR from groups user has joined
    $where[] = "(n.group_id IS NULL OR n.group_id IN (SELECT group_id FROM group_members WHERE user_id = $uid))";
}

$whereSQL = implode(' AND ', $where);

$sql = "
    SELECT n.*, u.name AS author_name, u.role AS author_role,
           g.name AS group_name,
           (SELECT COUNT(*) FROM comments c WHERE c.notice_id = n.id) AS comment_count,
           (SELECT COUNT(*) FROM reactions r WHERE r.notice_id = n.id) AS reaction_count,
           (SELECT type FROM reactions WHERE notice_id = n.id AND user_id = $uid LIMIT 1) AS user_reaction
    FROM notices n
    JOIN users u ON n.posted_by = u.id
    LEFT JOIN groups g ON n.group_id = g.id
    WHERE $whereSQL
    ORDER BY n.created_at DESC
    LIMIT 50
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $notices = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Get user's groups for sidebar
$myGroups = $conn->query("
    SELECT g.id, g.name FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = $uid
")->fetch_all(MYSQLI_ASSOC);

$categories = ['exam', 'event', 'holiday', 'urgent', 'general', 'placement'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-section">
            <h3>Filter by Category</h3>
            <a href="?" class="filter-chip <?= !$category ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= $cat ?>" class="filter-chip cat-<?= $cat ?> <?= $category === $cat ? 'active' : '' ?>">
                    <?= ucfirst($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-section">
            <h3>My Groups</h3>
            <?php if ($myGroups): ?>
                <?php foreach ($myGroups as $g): ?>
                    <a href="?group=<?= $g['id'] ?>" class="group-link <?= $group_id === $g['id'] ? 'active' : '' ?>">
                        # <?= htmlspecialchars($g['name']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted-text">No groups joined yet.</p>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/groups/list.php" class="btn-secondary small">Browse Groups</a>
        </div>
    </aside>

    <!-- Main Feed -->
    <section class="feed">
        <!-- Search bar -->
        <form method="GET" class="search-bar">
            <input type="text" name="search" placeholder="Search notices..." value="<?= htmlspecialchars($search) ?>">
            <?php if ($category): ?><input type="hidden" name="category" value="<?= $category ?>"><?php endif; ?>
            <button type="submit">Search</button>
            <?php if ($search): ?><a href="?" class="clear-search">✕ Clear</a><?php endif; ?>
        </form>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
            <div class="alert alert-error">You are not authorized to perform that action.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'not_approved'): ?>
            <div class="alert alert-error">⏳ Your faculty account is awaiting admin approval. You cannot post notices yet.</div>
        <?php endif; ?>
        <?php if (($_SESSION['role'] ?? '') === 'faculty' && ($_SESSION['approval'] ?? '') === 'pending'): ?>
            <div class="alert alert-info">⏳ Your faculty account is <strong>pending admin approval</strong>. You can browse notices but cannot post until approved.</div>
        <?php endif; ?>

        <?php if (empty($notices)): ?>
            <div class="empty-state">
                <span>📭</span>
                <p>No notices found<?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notices as $notice): ?>
                <article class="notice-card cat-border-<?= $notice['category'] ?>">
                    <div class="notice-meta">
                        <span class="category-badge cat-<?= $notice['category'] ?>"><?= ucfirst($notice['category']) ?></span>
                        <?php if ($notice['group_name']): ?>
                            <span class="group-badge"># <?= htmlspecialchars($notice['group_name']) ?></span>
                        <?php endif; ?>
                        <span class="notice-time"><?= date('d M Y, h:i A', strtotime($notice['created_at'])) ?></span>
                    </div>

                    <h2 class="notice-title">
                        <a href="<?= BASE_URL ?>/notices/view.php?id=<?= $notice['id'] ?>">
                            <?= htmlspecialchars($notice['title']) ?>
                        </a>
                    </h2>

                    <p class="notice-body"><?= nl2br(htmlspecialchars(substr($notice['body'], 0, 200))) ?><?= strlen($notice['body']) > 200 ? '...' : '' ?></p>

                    <div class="notice-footer">
                        <span class="author">by <strong><?= htmlspecialchars($notice['author_name']) ?></strong>
                            <span class="role-tag <?= $notice['author_role'] ?>"><?= ucfirst($notice['author_role']) ?></span>
                        </span>
                        <div class="notice-actions">
                            <a href="<?= BASE_URL ?>/notices/view.php?id=<?= $notice['id'] ?>#comments" class="action-btn">
                                💬 <?= $notice['comment_count'] ?>
                            </a>
                            <button class="action-btn react-btn <?= $notice['user_reaction'] ? 'reacted' : '' ?>"
                                    data-id="<?= $notice['id'] ?>">
                                👍 <?= $notice['reaction_count'] ?>
                            </button>
                            <a href="<?= BASE_URL ?>/notices/view.php?id=<?= $notice['id'] ?>" class="action-btn read-more">
                                Read more →
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
