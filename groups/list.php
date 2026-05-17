<?php
$pageTitle = 'Groups';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireLogin();

$uid  = (int)currentUser()['id'];
$role = currentUser()['role'];

// Join / Leave actions
if (isset($_GET['join'])) {
    $gid  = (int)$_GET['join'];
    $stmt = $conn->prepare("INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $gid, $uid);
    $stmt->execute();
    $stmt->close();
    header('Location: groups/list.php?joined=1');
    exit;
}

if (isset($_GET['leave'])) {
    $gid  = (int)$_GET['leave'];
    $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $gid, $uid);
    $stmt->execute();
    $stmt->close();
    header('Location: list.php');
    exit;
}

// Create group (faculty/admin)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($role, ['faculty', 'admin'])) {
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $dept  = trim($_POST['department'] ?? '');

    if (empty($name)) {
        $error = 'Group name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO groups (name, description, department, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sssi', $name, $desc, $dept, $uid);
        if ($stmt->execute()) {
            $gid  = $stmt->insert_id;
            // Auto-join creator
            $conn->query("INSERT INTO group_members (group_id, user_id) VALUES ($gid, $uid)");
            header('Location: list.php?created=1');
            exit;
        } else {
            $error = 'Failed to create group.';
        }
        $stmt->close();
    }
}

// Fetch all groups with membership info
$groups = $conn->query("
    SELECT g.*, u.name AS creator_name,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND user_id = $uid) AS is_member
    FROM groups g
    JOIN users u ON g.created_by = u.id
    ORDER BY g.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container">
    <div class="page-header-row">
        <h1 class="page-heading">Groups</h1>
        <?php if (in_array($role, ['faculty', 'admin'])): ?>
            <button class="btn-primary" onclick="document.getElementById('create-group-modal').style.display='flex'">
                + Create Group
            </button>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Group created successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['joined'])): ?>
        <div class="alert alert-success">You've joined the group!</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="groups-grid">
        <?php foreach ($groups as $g): ?>
            <div class="group-card <?= $g['is_member'] ? 'joined' : '' ?>">
                <div class="group-card-header">
                    <h3><?= htmlspecialchars($g['name']) ?></h3>
                    <?php if ($g['department']): ?>
                        <span class="dept-tag"><?= htmlspecialchars($g['department']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($g['description']): ?>
                    <p class="group-desc"><?= htmlspecialchars($g['description']) ?></p>
                <?php endif; ?>
                <div class="group-card-footer">
                    <span class="member-count">👥 <?= $g['member_count'] ?> members</span>
                    <span class="muted-text">by <?= htmlspecialchars($g['creator_name']) ?></span>
                    <div class="group-actions">
                        <?php if ($g['is_member']): ?>
                            <a href="<?= BASE_URL ?>/dashboard/index.php?group=<?= $g['id'] ?>" class="btn-secondary small">View Feed</a>
                            <a href="?leave=<?= $g['id'] ?>" class="btn-danger small" onclick="return confirm('Leave this group?')">Leave</a>
                        <?php else: ?>
                            <a href="?join=<?= $g['id'] ?>" class="btn-primary small">Join</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($groups)): ?>
            <div class="empty-state">
                <span>👥</span>
                <p>No groups created yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Group Modal (faculty/admin only) -->
<?php if (in_array($role, ['faculty', 'admin'])): ?>
<div id="create-group-modal" class="modal-overlay" style="display:none">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Create New Group</h2>
            <button onclick="document.getElementById('create-group-modal').style.display='none'" class="modal-close">✕</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Group Name <span class="required">*</span></label>
                <input type="text" name="name" placeholder="e.g. CSE 6th Semester" required>
            </div>
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" placeholder="e.g. CSE & Design">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="What is this group for?"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary"
                        onclick="document.getElementById('create-group-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Create Group →</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
