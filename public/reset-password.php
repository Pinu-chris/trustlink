<?php
$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: forgot-password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - TrustLink</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/">
                    <span class="logo-icon">🌾</span>
                    <span class="logo-text">TrustLink</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="products.php" class="nav-link">Browse Products</a>
                <a href="login.php" class="btn btn-outline">Back to Login</a>
            </div>
        </div>
    </nav>

    <div class="form-container">
        <h2>Reset Password</h2>
        <p class="text-muted">Enter your new password below.</p>

        <div id="alertContainer"></div>

        <form id="resetForm">
            <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required minlength="6">
                <small class="text-muted">At least 6 characters</small>
            </div>
            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" required>
            </div>
            <button type="submit" class="btn btn-primary btn-large" style="width:100%;" id="submitBtn">
                Reset Password
            </button>
        </form>
        <p class="text-center mt-4">
            <a href="login.php">Back to Login</a>
        </p>
    </div>

    <script src="../assets/js/api.js"></script>
    <script>
        const form = document.getElementById('resetForm');
        const alertContainer = document.getElementById('alertContainer');
        const submitBtn = document.getElementById('submitBtn');

        function showAlert(message, type = 'error') {
            alertContainer.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
            setTimeout(() => { alertContainer.innerHTML = ''; }, 5000);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const token = document.getElementById('token').value;
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm').value;

            if (!token) {
                showAlert('Invalid reset token', 'error');
                return;
            }
            if (password.length < 6) {
                showAlert('Password must be at least 6 characters', 'error');
                return;
            }
            if (password !== confirm) {
                showAlert('Passwords do not match', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Resetting...';

            try {
                const response = await API.post('/auth/reset_password.php', { token, password, confirm_password: confirm });
                if (response.success) {
                    showAlert('Password reset successfully! You can now login.', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showAlert(response.message || 'Failed to reset password', 'error');
                }
            } catch (error) {
                console.error('Reset password error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reset Password';
            }
        });
    </script>
</body>
</html>