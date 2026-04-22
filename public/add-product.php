<?php
/**
 * TRUSTLINK - Add Product Page
 * Version: 2.0 | PostgreSQL Compatible
 */

// Include global session configuration
require_once __DIR__ . '/../config/session_config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check if user is a farmer (only farmers can add products)
if (!isFarmer()) {
    header('Location: dashboard.php');
    exit;
}

// Get user info from session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Farmer';
$userPhone = $_SESSION['user_phone'] ?? '';
$userRole = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - TrustLink</title>
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
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="cart.php" class="cart-icon-link" id="cartIconLink">
                    <span class="cart-icon">🛒</span>
                    <span id="cartCount" class="cart-badge">0</span>
                </a>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($userName); ?></span>
                    <a href="#" id="logoutBtn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="form-container" style="max-width: 600px;">
            <h2>Add New Product</h2>
            <p>List your fresh produce for sale</p>
            
            <div id="alertContainer"></div>
            
            <form id="productForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" placeholder="e.g., Fresh Sukuma Wiki" required>
                    <span class="error" id="nameError"></span>
                </div>
                
                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="vegetables">🥬 Vegetables</option>
                        <option value="fruits">🍎 Fruits</option>
                        <option value="dairy">🥛 Dairy</option>
                        <option value="grains">🌾 Grains</option>
                        <option value="poultry">🐔 Poultry</option>
                        <option value="other">📦 Other</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price (KES) *</label>
                        <input type="number" id="price" name="price" step="0.5" placeholder="50" required>
                    </div>
                    <div class="form-group">
                        <label for="unit">Unit *</label>
                        <select id="unit" name="unit" required>
                            <option value="kg">Kilogram (kg)</option>
                            <option value="bunch">Bunch</option>
                            <option value="piece">Piece</option>
                            <option value="dozen">Dozen</option>
                            <option value="liter">Liter</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Available Quantity *</label>
                    <input type="number" id="quantity" name="quantity" placeholder="100" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4" placeholder="Describe your product..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="images">Product Images (Max 5, JPEG/PNG)</label>
                    <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">Upload up to 5 images. First image will be primary.</small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large" style="width: 100%;" id="submitBtn">
                    Add Product
                </button>
            </form>
        </div>
    </div>

    <script>
        // Pass user data from PHP to JavaScript
        const currentUser = {
            id: <?php echo json_encode($userId); ?>,
            name: <?php echo json_encode($userName); ?>,
            role: <?php echo json_encode($userRole); ?>,
            phone: <?php echo json_encode($userPhone); ?>
        };
        
        console.log('Current user (from PHP):', currentUser);
        
        // Double-check role (though PHP already checks)
        if (currentUser.role !== 'farmer') {
            showAlert('Only farmers can add products. Redirecting...', 'error');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        }
    </script>
    <script src="../assets/js/api.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        const form = document.getElementById('productForm');
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
            document.querySelectorAll('.form-group input, .form-group select, .form-group textarea').forEach(el => {
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
        
                async function updateCartCount() {
                    try {
                        const response = await API.get('/cart/get_cart_count.php');
                        if (response.success && response.data) {
                            const count = response.data.count;
                            const badge = document.getElementById('cartCount');
                            if (badge) {
                                badge.textContent = count;
                                badge.style.display = count > 0 ? 'inline-block' : 'none';
                            }
                        }
                    } catch (error) {
                        console.error('Failed to update cart count:', error);
                    }
                }


        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            
            const name = document.getElementById('name').value.trim();
            const category = document.getElementById('category').value;
            const price = parseFloat(document.getElementById('price').value);
            const unit = document.getElementById('unit').value;
            const quantity = parseInt(document.getElementById('quantity').value);
            const description = document.getElementById('description').value.trim();
            const images = document.getElementById('images').files;
            
            // Validation
            let hasError = false;
            
            if (!name) {
                showFieldError('name', 'Product name is required');
                hasError = true;
            } else if (name.length < 3) {
                showFieldError('name', 'Name must be at least 3 characters');
                hasError = true;
            }
            
            if (isNaN(price) || price <= 0) {
                showFieldError('price', 'Valid price is required');
                hasError = true;
            }
            
            if (isNaN(quantity) || quantity <= 0) {
                showFieldError('quantity', 'Valid quantity is required');
                hasError = true;
            }
            
            if (hasError) return;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Adding product...';
            
            try {
                // First create product
                const productResponse = await API.post('/products/add_product.php', {
                    name,
                    category,
                    price,
                    quantity,
                    unit,
                    description
                });
                
                console.log('Product response:', productResponse);
                
                if (productResponse.success && productResponse.data?.product) {
                    const productId = productResponse.data.product.id;
                    
                    // Upload images if any
                    if (images.length > 0) {
                        const formData = new FormData();
                        formData.append('product_id', productId);
                        for (let i = 0; i < images.length; i++) {
                            formData.append('images[]', images[i]);
                        }
                        
                        await API.upload('/products/upload_images.php', formData);
                    }
                    
                    showAlert('Product added successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    throw new Error(productResponse.message || 'Failed to add product');
                }
            } catch (error) {
                console.error('Add product error:', error);
                showAlert(error.message || 'Failed to add product. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Add Product';
            }
        });
        
        // Logout functionality
        document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await API.post('/auth/logout.php');
                window.location.href = 'login.php';
            } catch (error) {
                console.error('Logout failed:', error);
                window.location.href = 'login.php';
            }
        });
        updateCartCount();
    </script>
</body>
</html>