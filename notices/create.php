<?php
$pageTitle = 'Post Notice';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireLogin();
requireRole('faculty', 'admin');

// Block faculty who haven't been approved yet
if ($_SESSION['role'] === 'faculty' && ($_SESSION['approval'] ?? 'pending') !== 'approved') {
    header('Location: ' . BASE_URL . '/dashboard/index.php?error=not_approved');
    exit;
}

$user  = currentUser();
$uid   = (int)$user['id'];
$error = '';

// Get groups this user manages or is member of
$groups = $conn->query("
    SELECT g.id, g.name FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = $uid
    ORDER BY g.name
")->fetch_all(MYSQLI_ASSOC);

$categories = ['general', 'exam', 'event', 'holiday', 'urgent', 'placement'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $category = $_POST['category'] ?? 'general';
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;

    if (empty($title) || empty($body)) {
        $error = 'Title and body are required.';
    } elseif (!in_array($category, $categories)) {
        $error = 'Invalid category.';
    } else {
        $stmt = $conn->prepare("INSERT INTO notices (title, body, category, group_id, posted_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssii', $title, $body, $category, $group_id, $uid);

        if ($stmt->execute()) {
            $notice_id = $stmt->insert_id;

            // Notify all members of the group (or all users if global notice)
            if ($group_id) {
                $members = $conn->query("SELECT user_id FROM group_members WHERE group_id = $group_id AND user_id != $uid");
                while ($m = $members->fetch_assoc()) {
                    $mid  = $m['user_id'];
                    $nstmt = $conn->prepare("INSERT INTO notifications (user_id, notice_id) VALUES (?, ?)");
                    $nstmt->bind_param('ii', $mid, $notice_id);
                    $nstmt->execute();
                    $nstmt->close();
                }
            } else {
                // Global notice — notify all students in same department
                $dept     = $conn->real_escape_string($user['department']);
                $everyone = $conn->query("SELECT id FROM users WHERE id != $uid AND department = '$dept' AND is_active = 1");
                while ($m = $everyone->fetch_assoc()) {
                    $mid  = $m['id'];
                    $nstmt = $conn->prepare("INSERT INTO notifications (user_id, notice_id) VALUES (?, ?)");
                    $nstmt->bind_param('ii', $mid, $notice_id);
                    $nstmt->execute();
                    $nstmt->close();
                }
            }

            header('Location: ' . BASE_URL . '/notices/view.php?id=' . $notice_id . '&posted=1');
            exit;
        } else {
            $error = 'Failed to post notice. Please try again.';
        }
        $stmt->close();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container narrow">
    <h1 class="page-heading">Post a Notice</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-card">
        <div class="form-group">
            <label>Notice Title <span class="required">*</span></label>
            <input type="text" name="title" placeholder="e.g. Mid-semester exam schedule released"
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($_POST['category'] ?? 'general') === $cat ? 'selected' : '' ?>>
                            <?= ucfirst($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Post to Group <span class="muted-text">(optional — leave blank for global)</span></label>
                <select name="group_id">
                    <option value="">— Global Notice —</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= ($_POST['group_id'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                            # <?= htmlspecialchars($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Notice Body <span class="required">*</span></label>
            <textarea name="body" rows="8" placeholder="Write the full notice here..."
                      required><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <a href="<?= BASE_URL ?>/dashboard/index.php" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Post Notice →</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
