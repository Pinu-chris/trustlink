<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - TrustLink</title>
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
        <h2>Forgot Password</h2>
        <p class="text-muted">Enter your email address and we'll send you a link to reset your password.</p>

        <div id="alertContainer"></div>

        <form id="forgotForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary btn-large" style="width:100%;" id="submitBtn">
                Send Reset Link
            </button>
        </form>
        <p class="text-center mt-4">
            Remember your password? <a href="login.php">Sign in</a>
        </p>
    </div>

    <script src="../assets/js/api.js"></script>
    <script>
        const form = document.getElementById('forgotForm');
        const alertContainer = document.getElementById('alertContainer');
        const submitBtn = document.getElementById('submitBtn');

        function showAlert(message, type = 'error') {
            alertContainer.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
            setTimeout(() => { alertContainer.innerHTML = ''; }, 5000);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();

            if (!email) {
                showAlert('Please enter your email address', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';

            try {
                const response = await API.post('/auth/forgot_password.php', { email });
                if (response.success) {
                    showAlert('If the email exists, a password reset link has been sent.', 'success');
                    form.reset();
                } else {
                    showAlert(response.message || 'Failed to send reset link', 'error');
                }
            } catch (error) {
                console.error('Forgot password error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Reset Link';
            }
        });
    </script>
</body>
</html>