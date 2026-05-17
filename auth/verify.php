<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

// If no pending OTP session, redirect to register
if (!isset($_SESSION['otp_user_id'])) {
    header('Location: ' . BASE_URL . '/auth/register.php');
    exit;
}

$uid   = (int)$_SESSION['otp_user_id'];
$error = '';

// Fetch user for display
$userRow = $conn->query("SELECT name, email FROM users WHERE id = $uid")->fetch_assoc();
if (!$userRow) {
    session_unset();
    header('Location: ' . BASE_URL . '/auth/register.php');
    exit;
}

// ── Handle OTP submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $entered = trim($_POST['otp'] ?? '');

    if (empty($entered) || !ctype_digit($entered) || strlen($entered) !== 6) {
        $error = 'Please enter the 6-digit code sent to your email.';
    } else {
        $stmt = $conn->prepare(
            "SELECT otp_code, otp_expires_at FROM users WHERE id = ? AND is_verified = 0"
        );
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error = 'Account not found or already verified.';
        } elseif ($entered !== $row['otp_code']) {
            $error = 'Incorrect OTP. Please check your email and try again.';
        } elseif (strtotime($row['otp_expires_at']) < time()) {
            $error = 'This OTP has expired. Use the Resend button to get a new one.';
        } else {
            // ✅ OTP correct and valid — verify the account
            $conn->query(
                "UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = $uid"
            );
            unset($_SESSION['otp_user_id']);
            header('Location: ' . BASE_URL . '/auth/login.php?verified=1');
            exit;
        }
    }
}

// ── Handle Resend OTP ──
if (isset($_GET['resend'])) {
    $newOtp    = generateOTP();
    $newExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $conn->query(
        "UPDATE users SET otp_code = '$newOtp', otp_expires_at = '$newExpiry' WHERE id = $uid"
    );

    $result = sendOTPEmail($userRow['email'], $userRow['name'], $newOtp);

    if ($result['success']) {
        header('Location: ' . BASE_URL . '/auth/verify.php?resent=1');
    } else {
        $error = 'Failed to resend OTP: ' . $result['error'];
    }
    exit;
}

// Mask email for display: ri***@gmail.com
function maskEmail(string $email): string {
    [$local, $domain] = explode('@', $email);
    $masked = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3));
    return $masked . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusSync — Verify Email</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .otp-inputs {
            display: flex;
            gap: .6rem;
            justify-content: center;
            margin: 1.5rem 0;
        }
        .otp-inputs input {
            width: 48px;
            height: 58px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 800;
            font-family: 'Syne', sans-serif;
            background: #1e1e25;
            border: 1.5px solid #2a2a35;
            border-radius: 10px;
            color: #e8e8f0;
            caret-color: #f5a623;
            transition: border-color .2s;
        }
        .otp-inputs input:focus {
            outline: none;
            border-color: #f5a623;
            background: #23232e;
        }
        .otp-inputs input.filled {
            border-color: #f5a623;
        }
        .email-hint {
            text-align: center;
            font-size: .88rem;
            color: #6b6b80;
            margin-bottom: .5rem;
        }
        .email-hint strong { color: #e8e8f0; }
        .timer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .82rem;
            color: #6b6b80;
            margin-top: .5rem;
        }
        #countdown { color: #f5a623; font-weight: 700; }
        #countdown.expired { color: #e05252; }
        .resend-link {
            color: #f5a623;
            font-size: .82rem;
            cursor: pointer;
        }
        .resend-link:hover { opacity: .8; }
        .otp-hidden { display: none; }
    </style>
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-brand">
        <span class="brand-icon-lg">⬡</span>
        <h1>CampusSync</h1>
        <p>Verify your email</p>
    </div>

    <div class="auth-card">
        <h2>Enter OTP</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['resent'])): ?>
            <div class="alert alert-success">New OTP sent! Check your inbox.</div>
        <?php endif; ?>

        <p class="email-hint">
            A 6-digit code was sent to<br>
            <strong><?= maskEmail($userRow['email']) ?></strong>
        </p>

        <form method="POST" action="" id="otpForm">
            <!-- Hidden single input that receives the assembled OTP -->
            <input type="hidden" name="otp" id="otpHidden">

            <div class="otp-inputs">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="off">
            </div>

            <div class="timer-row">
                <span>Expires in <span id="countdown">10:00</span></span>
                <a href="<?= BASE_URL ?>/auth/verify.php?resend=1" class="resend-link" id="resendLink">Resend OTP</a>
            </div>

            <button type="submit" class="btn-primary full-width" style="margin-top:1.25rem" id="verifyBtn" disabled>
                Verify Email →
            </button>
        </form>

        <p class="auth-switch" style="margin-top:1rem">
            Wrong email? <a href="register.php">Go back</a>
        </p>
    </div>
</div>

<script>
// ── OTP digit box logic ──
const digits   = document.querySelectorAll('.otp-digit');
const hidden   = document.getElementById('otpHidden');
const verifyBtn= document.getElementById('verifyBtn');

digits.forEach((input, i) => {
    input.addEventListener('input', (e) => {
        // Allow only digits
        input.value = input.value.replace(/\D/g, '').slice(-1);
        if (input.value) {
            input.classList.add('filled');
            if (i < digits.length - 1) digits[i + 1].focus();
        } else {
            input.classList.remove('filled');
        }
        syncHidden();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && i > 0) {
            digits[i - 1].focus();
            digits[i - 1].value = '';
            digits[i - 1].classList.remove('filled');
            syncHidden();
        }
    });

    // Handle paste on any box
    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = e.clipboardData.getData('text').replace(/\D/g,'').slice(0, 6);
        pasted.split('').forEach((ch, idx) => {
            if (digits[idx]) {
                digits[idx].value = ch;
                digits[idx].classList.add('filled');
            }
        });
        const next = Math.min(pasted.length, digits.length - 1);
        digits[next].focus();
        syncHidden();
    });
});

function syncHidden() {
    const val = [...digits].map(d => d.value).join('');
    hidden.value = val;
    verifyBtn.disabled = val.length !== 6;
}

// Assemble OTP before submit
document.getElementById('otpForm').addEventListener('submit', (e) => {
    const val = [...digits].map(d => d.value).join('');
    if (val.length !== 6) {
        e.preventDefault();
        return;
    }
    hidden.value = val;
});

// ── Countdown timer (10 min) ──
const countdownEl = document.getElementById('countdown');
let seconds = 10 * 60;

const timer = setInterval(() => {
    seconds--;
    if (seconds <= 0) {
        clearInterval(timer);
        countdownEl.textContent = 'Expired';
        countdownEl.classList.add('expired');
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'OTP Expired — Resend';
        return;
    }
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    countdownEl.textContent = `${m}:${s}`;
}, 1000);

// Focus first box on load
digits[0].focus();
</script>

</body>
</html>
