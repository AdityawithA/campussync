<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } else {
        $stmt = $conn->prepare("SELECT id, name, password, role, department, is_verified, approval_status FROM users WHERE email = ? AND is_active = 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            // Block unverified accounts — send to OTP page
            if (!$user['is_verified']) {
                $_SESSION['otp_user_id'] = $user['id'];
                header('Location: ' . BASE_URL . '/auth/verify.php?pending=1');
                exit;
            }
            // Block rejected faculty entirely
            elseif ($user['role'] === 'faculty' && $user['approval_status'] === 'rejected') {
                $error = '❌ Your faculty account request was rejected by the admin. Please contact your administrator.';
            }
            // Allow login for everyone else (including pending faculty — they just can't post)
            else {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['name']       = $user['name'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['approval']   = $user['approval_status'];
                header('Location: ' . BASE_URL . '/dashboard/index.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusSync — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-brand">
        <span class="brand-icon-lg">⬡</span>
        <h1>CampusSync</h1>
        <p>Your college. Connected.</p>
    </div>

    <div class="auth-card">
        <h2>Welcome back</h2>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['verified'])): ?>
            <div class="alert alert-success">✅ Email verified! You can now log in.</div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Account created! You can now log in.</div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@college.edu" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-primary full-width">Login →</button>
        </form>

        <p class="auth-switch">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</div>

</body>
</html>
