<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

$error       = '';
$departments = ['CSE', 'CSE & Design', 'IT', 'ECE', 'EE', 'ME', 'CE', 'MBA', 'MCA', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm'] ?? '';
    $role       = $_POST['role'] ?? 'student';
    $department = $_POST['department'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($department)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($role, ['student', 'faculty'])) {
        $error = 'Invalid role selected.';
    } else {
        // Check duplicate email
        $check = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing && $existing['is_verified']) {
            $error = 'This email is already registered and verified.';
        } elseif ($existing && !$existing['is_verified']) {
            // Exists but not verified — resend OTP
            $otp     = generateOTP();
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $uid     = $existing['id'];
            $conn->query("UPDATE users SET otp_code='$otp', otp_expires_at='$expires' WHERE id=$uid");
            $result = sendOTPEmail($email, $name, $otp);
            if ($result['success']) {
                $_SESSION['otp_user_id'] = $uid;
                header('Location: ' . BASE_URL . '/auth/verify.php?resent=1');
                exit;
            } else {
                $error = 'Could not resend OTP. ' . $result['error'];
            }
        } else {
            $hashed  = password_hash($password, PASSWORD_BCRYPT);
            $otp     = generateOTP();
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Faculty → pending approval. Student → auto approved.
            $approval = ($role === 'faculty') ? 'pending' : 'approved';

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password, role, department, is_verified, approval_status, otp_code, otp_expires_at)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)"
            );
            $stmt->bind_param('ssssssss', $name, $email, $hashed, $role, $department, $approval, $otp, $expires);

            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $stmt->close();

                $result = sendOTPEmail($email, $name, $otp);
                if ($result['success']) {
                    $_SESSION['otp_user_id'] = $newId;
                    header('Location: ' . BASE_URL . '/auth/verify.php');
                    exit;
                } else {
                    $conn->query("DELETE FROM users WHERE id = $newId");
                    $error = 'Verification email failed to send. Error: ' . $result['error'];
                }
            } else {
                $error = 'Registration failed. Please try again.';
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusSync — Register</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-brand">
        <span class="brand-icon-lg">⬡</span>
        <h1>CampusSync</h1>
        <p>Join your campus network.</p>
    </div>

    <div class="auth-card">
        <h2>Create Account</h2>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Aditya Kumar" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@college.edu" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="roleSelect">
                        <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="faculty" <?= ($_POST['role'] ?? '') === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required>
                        <option value="">Select...</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept ?>" <?= ($_POST['department'] ?? '') === $dept ? 'selected' : '' ?>>
                                <?= $dept ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Faculty notice banner -->
            <div id="facultyNotice" style="display:none" class="alert alert-info">
                🔔 Faculty accounts require <strong>admin approval</strong> before you can post notices.
                You'll still get OTP verified, but posting access is granted only after admin approves.
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm" placeholder="Repeat password" required>
            </div>
            <button type="submit" class="btn-primary full-width">Create Account →</button>
        </form>

        <p class="auth-switch">Already registered? <a href="login.php">Login here</a></p>
    </div>
</div>

<script>
    const roleSelect     = document.getElementById('roleSelect');
    const facultyNotice  = document.getElementById('facultyNotice');
    roleSelect.addEventListener('change', () => {
        facultyNotice.style.display = roleSelect.value === 'faculty' ? 'block' : 'none';
    });
    // Show on load if faculty pre-selected
    if (roleSelect.value === 'faculty') facultyNotice.style.display = 'block';
</script>

</body>
</html>
