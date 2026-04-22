<?php
// Start session to check if already logged in
require_once __DIR__ . '/../config/session_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Login - TrustLink</title>
    <!-- Google Fonts: Playfair Display for headings, Inter for body -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Additional styles for this page */
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .navbar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .form-container {
            max-width: 450px;
            margin: 60px auto;
            background: white;
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .form-container h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1a3e2f;
        }
        .text-muted {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2d3e2f;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            background: #f8f9fa;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.1);
            background: white;
        }
        .password-wrapper {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            font-size: 1.2rem;
        }
        .strength-meter {
            margin-top: 6px;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }
        .strength-bar {
            width: 0%;
            height: 100%;
            transition: width 0.3s, background 0.3s;
        }
        .strength-text {
            font-size: 0.7rem;
            margin-top: 4px;
            color: #6c757d;
        }
        .btn-primary {
            background: #2e7d32;
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 40px;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: #1b5e20;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(46,125,50,0.3);
        }
        .btn-primary:disabled {
            opacity: 0.7;
            transform: none;
        }
        .alert {
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        .mt-4 { margin-top: 1.5rem; }
        .mt-2 { margin-top: 0.75rem; }
        .text-center { text-align: center; }
        .text-muted a {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 500;
        }
        .text-muted a:hover {
            text-decoration: underline;
        }
        @media (max-width: 576px) {
            .form-container {
                margin: 20px;
                padding: 32px 24px;
            }
            .form-container h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.php">
                    <span class="logo-icon">🌾</span>
                    <span class="logo-text">TrustLink</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="products.php" class="nav-link">Browse Products</a>
                <a href="register.php" class="btn btn-outline">Sign Up</a>
            </div>
        </div>
    </nav>

    <div class="form-container">
        <h2>Welcome Back</h2>
        <p class="text-muted">Sign in to your TrustLink account</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div id="alertContainer"></div>
        
        <form id="loginForm" method="POST" action="../api/auth/login_handler.php">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="0712345678" autocomplete="off" required>
                <span class="error" id="phoneError"></span>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <span class="toggle-password" id="togglePassword">👁️</span>
                </div>
                <div class="strength-meter">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText"></div>
                <span class="error" id="passwordError"></span>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="remember" style="width: auto;"> Remember me
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary btn-large" style="width: 100%;" id="submitBtn">
                Sign In
            </button>
        </form>
        
        <p class="text-center mt-4">
            Don't have an account? <a href="register.php">Create an account</a>
        </p>
        <p class="text-center mt-2">
            <a href="forgot-password.php">Forgot password?</a>
        </p>
    </div>

    <script src="../assets/js/api.js"></script>
    <script>
        // Helper: escape HTML
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const alertContainer = document.getElementById('alertContainer');
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        function showAlert(message, type = 'error') {
            alertContainer.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        function clearErrors() {
            document.querySelectorAll('.error').forEach(el => el.textContent = '');
            document.querySelectorAll('.form-group input').forEach(el => {
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

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            let percent = (strength / 5) * 100;
            let color, text;
            if (strength === 0) { percent = 0; color = '#dc3545'; text = 'Very Weak'; }
            else if (strength <= 2) { color = '#dc3545'; text = 'Weak'; }
            else if (strength === 3) { color = '#ffc107'; text = 'Medium'; }
            else if (strength === 4) { color = '#28a745'; text = 'Strong'; }
            else { color = '#2e7d32'; text = 'Very Strong'; }
            
            strengthBar.style.width = percent + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
        }

        // Toggle password visibility
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.textContent = type === 'password' ? '👁️' : '🙈';
        });

        // Real‑time password strength
        passwordInput.addEventListener('input', (e) => {
            checkPasswordStrength(e.target.value);
        });

        // Client-side validation
        form.addEventListener('submit', (e) => {
            clearErrors();
            
            const phone = document.getElementById('phone').value.trim();
            const password = passwordInput.value;
            
            let hasError = false;
            
            if (!phone) {
                showFieldError('phone', 'Phone number is required');
                hasError = true;
            } else if (!/^(07|01)[0-9]{8}$/.test(phone)) {
                showFieldError('phone', 'Invalid Kenyan phone number');
                hasError = true;
            }
            
            if (!password) {
                showFieldError('password', 'Password is required');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
            } else {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Signing in...';
            }
        });
    </script>
</body>
</html>