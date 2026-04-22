<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['force_password_change'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - TrustLink</title>
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
            </div>
        </div>
    </nav>

    <div class="form-container">
        <h2>Change Your Password</h2>
        <p class="text-muted">You must set a new password before continuing.</p>

        <div id="alertContainer"></div>

        <form id="changePasswordForm">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-large" style="width:100%;" id="submitBtn">
                Change Password
            </button>
        </form>
    </div>

    <script src="../assets/js/api.js"></script>
    <script>
        const form = document.getElementById('changePasswordForm');
        const alertContainer = document.getElementById('alertContainer');
        const submitBtn = document.getElementById('submitBtn');

        function showAlert(message, type = 'error') {
            alertContainer.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
            setTimeout(() => { alertContainer.innerHTML = ''; }, 5000);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (password.length < 6) {
                showAlert('Password must be at least 6 characters', 'error');
                return;
            }
            if (password !== confirm) {
                showAlert('Passwords do not match', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';

            try {
                const response = await API.post('/auth/change_password.php', { password });
                if (response.success) {
                    showAlert('Password changed successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    showAlert(response.message || 'Failed to change password', 'error');
                }
            } catch (error) {
                console.error('Change password error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Change Password';
            }
        });
    </script>
</body>
</html>