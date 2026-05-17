<?php
$pageTitle = 'Admin Panel';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireLogin();
requireRole('admin');

// ── Approve faculty
if (isset($_GET['approve'])) {
    $tid = (int)$_GET['approve'];
    $conn->query("UPDATE users SET approval_status='approved' WHERE id=$tid AND role='faculty'");
    header('Location: index.php?approved=1');
    exit;
}

// ── Reject faculty
if (isset($_GET['reject'])) {
    $tid = (int)$_GET['reject'];
    $conn->query("UPDATE users SET approval_status='rejected' WHERE id=$tid AND role='faculty'");
    header('Location: index.php?rejected=1');
    exit;
}

// ── Toggle user active status
if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $conn->query("UPDATE users SET is_active = NOT is_active WHERE id=$tid AND role != 'admin'");
    header('Location: index.php');
    exit;
}

// ── Delete user
if (isset($_GET['delete_user'])) {
    $did = (int)$_GET['delete_user'];
    $conn->query("DELETE FROM users WHERE id=$did AND role != 'admin'");
    header('Location: index.php');
    exit;
}

// Stats
$totalUsers    = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_verified=1")->fetch_assoc()['c'];
$totalNotices  = $conn->query("SELECT COUNT(*) as c FROM notices")->fetch_assoc()['c'];
$totalGroups   = $conn->query("SELECT COUNT(*) as c FROM `groups`")->fetch_assoc()['c'];
$totalComments = $conn->query("SELECT COUNT(*) as c FROM comments")->fetch_assoc()['c'];
$pendingCount  = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='faculty' AND approval_status='pending' AND is_verified=1")->fetch_assoc()['c'];

// Pending faculty — verified but not yet approved
$pendingFaculty = $conn->query("
    SELECT * FROM users
    WHERE role = 'faculty' AND approval_status = 'pending' AND is_verified = 1
    ORDER BY created_at ASC
")->fetch_all(MYSQLI_ASSOC);

// All verified users
$users = $conn->query("
    SELECT * FROM users WHERE is_verified = 1
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container" style="max-width:1100px">
    <h1 class="page-heading">Admin Panel</h1>

    <?php if (isset($_GET['approved'])): ?>
        <div class="alert alert-success">✅ Faculty account approved successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['rejected'])): ?>
        <div class="alert alert-error">Faculty account has been rejected.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-num"><?= $totalUsers ?></span>
            <span class="stat-label">Users</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $totalNotices ?></span>
            <span class="stat-label">Notices</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $totalGroups ?></span>
            <span class="stat-label">Groups</span>
        </div>
        <div class="stat-card <?= $pendingCount > 0 ? 'stat-card-alert' : '' ?>">
            <span class="stat-num" style="<?= $pendingCount > 0 ? 'color:var(--cat-urgent)' : '' ?>"><?= $pendingCount ?></span>
            <span class="stat-label">Pending Faculty</span>
        </div>
    </div>

    <!-- ── Faculty Approval Section ── -->
    <div style="margin-top:2.5rem">
        <h2 style="margin-bottom:1rem; display:flex; align-items:center; gap:.75rem">
            Faculty Approval Requests
            <?php if ($pendingCount > 0): ?>
                <span class="badge" style="position:static; background:var(--cat-urgent); font-size:.8rem; padding:3px 10px; border-radius:99px">
                    <?= $pendingCount ?> pending
                </span>
            <?php endif; ?>
        </h2>

        <?php if (empty($pendingFaculty)): ?>
            <div class="empty-state" style="padding:2rem; text-align:left">
                <p class="muted-text">✅ No pending faculty requests right now.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingFaculty as $f): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                                <td><?= htmlspecialchars($f['email']) ?></td>
                                <td><?= htmlspecialchars($f['department'] ?? '—') ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($f['created_at'])) ?></td>
                                <td style="display:flex; gap:.5rem; align-items:center">
                                    <a href="?approve=<?= $f['id'] ?>" class="btn-primary small"
                                       onclick="return confirm('Approve <?= htmlspecialchars($f['name']) ?> as faculty?')">
                                        ✅ Approve
                                    </a>
                                    <a href="?reject=<?= $f['id'] ?>" class="btn-danger small"
                                       onclick="return confirm('Reject this faculty request?')">
                                        ❌ Reject
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── All Users Table ── -->
    <h2 style="margin-top:2.5rem; margin-bottom:1rem">All Users</h2>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Approval</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="<?= !$u['is_active'] ? 'row-inactive' : '' ?>">
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="role-tag <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td><?= htmlspecialchars($u['department'] ?? '—') ?></td>
                        <td>
                            <?= $u['is_active']
                                ? '<span class="status-active">Active</span>'
                                : '<span class="status-inactive">Suspended</span>' ?>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'faculty'): ?>
                                <?php if ($u['approval_status'] === 'approved'): ?>
                                    <span class="status-active">Approved</span>
                                <?php elseif ($u['approval_status'] === 'pending'): ?>
                                    <span style="color:var(--cat-urgent); font-size:.82rem; font-weight:600">Pending</span>
                                <?php else: ?>
                                    <span class="status-inactive">Rejected</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted-text">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['role'] !== 'admin'): ?>
                                <a href="?toggle=<?= $u['id'] ?>" class="btn-secondary small">
                                    <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                                </a>
                                <a href="?delete_user=<?= $u['id'] ?>" class="btn-danger small"
                                   onclick="return confirm('Permanently delete this user?')">Delete</a>
                            <?php else: ?>
                                <span class="muted-text">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
