<?php
// Start session to check if already logged in
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// Get any messages from URL parameters
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$roleParam = isset($_GET['role']) ? htmlspecialchars($_GET['role']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TrustLink</title>
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
                <a href="login.php" class="btn btn-outline">Sign In</a>
            </div>
        </div>
    </nav>

    <div class="form-container">
        <h2>Create Account</h2>
        <p class="text-muted">Join TrustLink - Kenya's trusted marketplace</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div id="alertContainer"></div>
        
        <form id="registerForm" method="POST" action="../api/auth/register_handler.php">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="John Doe" required>
                <span class="error" id="nameError"></span>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="0712345678" required>
                <span class="error" id="phoneError"></span>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
                <span class="error" id="emailError"></span>
            </div>
            
            <div class="form-group">
                <label for="role">I want to</label>
                <select id="role" name="role" required>
                    <option value="buyer" <?php echo $roleParam === 'buyer' ? 'selected' : ''; ?>>Buy products</option>
                    <option value="farmer" <?php echo $roleParam === 'farmer' ? 'selected' : ''; ?>>Sell my products</option>
                    <option value="service_provider" <?php echo $roleParam === 'service_provider' ? 'selected' : ''; ?>>Offer services</option>
                </select>
                <span class="error" id="roleError"></span>
            </div>
            
            <div class="form-group">
                <label for="location">Location (Estate/Ward)</label>
                <input type="text" id="location" name="location" placeholder="e.g., Kilimani, Nairobi">
                <span class="error" id="locationError"></span>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="At least 6 characters" required>
                <span class="error" id="passwordError"></span>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
                <span class="error" id="passwordConfirmError"></span>
            </div>
            
            <button type="submit" class="btn btn-primary btn-large" style="width: 100%;" id="submitBtn">
                Create Account
            </button>
        </form>
        
        <p class="text-center mt-4">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
        
        <p class="text-center text-muted small mt-4">
            By signing up, you agree to our <a href="/terms.php">Terms of Service</a> and <a href="/privacy.php">Privacy Policy</a>
        </p>
    </div>

    <script src="../assets/js/api.js"></script>
    <script>
        const form = document.getElementById('registerForm');
        const submitBtn = document.getElementById('submitBtn');
        const alertContainer = document.getElementById('alertContainer');
        
        function showAlert(message, type = 'error') {
            alertContainer.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }
        
        function clearErrors() {
            document.querySelectorAll('.error').forEach(el => el.textContent = '');
            document.querySelectorAll('.form-group input, .form-group select').forEach(el => {
                el.classList.remove('error-border');
            });
        }
        
        function showFieldError(field, message) {
            const errorEl = document.getElementById(`${field}Error`);
            if (errorEl) {
                errorEl.textContent = message;
                document.getElementById(field)?.classList.add('error-border');
            }
        }
        
        // Client-side validation before submitting
        form.addEventListener('submit', (e) => {
            clearErrors();
            
            const name = document.getElementById('name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            
            let hasError = false;
            
            if (!name) {
                showFieldError('name', 'Full name is required');
                hasError = true;
            } else if (name.length < 2) {
                showFieldError('name', 'Name must be at least 2 characters');
                hasError = true;
            }
            
            if (!phone) {
                showFieldError('phone', 'Phone number is required');
                hasError = true;
            } else if (!/^(07|01)[0-9]{8}$/.test(phone)) {
                showFieldError('phone', 'Invalid Kenyan phone number (e.g., 0712345678)');
                hasError = true;
            }

            const email = document.getElementById('email').value.trim();
                if (!email) {
                    showFieldError('email', 'Email address is required');
                    hasError = true;
                } else if (!/^[^\s@]+@([^\s@]+\.)+[^\s@]+$/.test(email)) {
                    showFieldError('email', 'Please enter a valid email address');
                    hasError = true;
                }
            
            if (!password) {
                showFieldError('password', 'Password is required');
                hasError = true;
            } else if (password.length < 6) {
                showFieldError('password', 'Password must be at least 6 characters');
                hasError = true;
            }
            
            if (password !== passwordConfirm) {
                showFieldError('passwordConfirm', 'Passwords do not match');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
            } else {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating account...';
            }
        });
    </script>
</body>
</html>