<?php
// ============================================================================
// TRUSTLINK - Shopping Cart (Modern Layout)
// Version: 2.0 | March 2026
// ============================================================================

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/constants.php';

use TrustLink\Config\SessionKeys;

// Error reporting – disable in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Check login
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION[SessionKeys::USER_ID] ?? null;
$userName = $_SESSION[SessionKeys::USER_NAME] ?? 'User';
$userRole = $_SESSION[SessionKeys::USER_ROLE] ?? 'buyer';
$userPhone = $_SESSION[SessionKeys::USER_PHONE] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Shopping Cart - TrustLink</title>
    
    <!-- JetBrains Mono – the only font used everywhere -->
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- External stylesheets -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <style>
        /* ===== OVERRIDES & ADDITIONS (same as dashboard) ===== */
        * {
            font-family: 'JetBrains Mono', monospace !important;
        }
        
        body {
            background: var(--gray-50);
            overflow-x: hidden;
        }
        
        /* Header layout */
        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        /* Hamburger button */
        .hamburger {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 0.5rem;
            line-height: 1;
            color: var(--gray-700);
            transition: all 0.2s;
            border-radius: var(--radius-md);
        }
        .hamburger:hover {
            background-color: var(--gray-100);
        }
        
        /* Search input – centered */
        .search-container {
            flex: 1;
            max-width: 500px;
            margin: 0 1rem;
        }
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-300);
            border-radius: 60px;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: white;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46,125,50,0.1);
        }
        
        /* Right icons container */
        .right-icons {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Sidebar overlay (hamburger menu) */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: -320px;
            width: 300px;
            height: 100%;
            background: white;
            box-shadow: var(--shadow-xl);
            z-index: 1002;
            transition: left 0.3s ease;
            overflow-y: auto;
            padding: 1.5rem 0;
        }
        .sidebar.active {
            left: 0;
        }
        .sidebar-header {
            padding: 0 1.5rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .close-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            border-bottom: 1px solid var(--gray-100);
        }
        .sidebar-menu a, .sidebar-menu .menu-item {
            display: block;
            padding: 0.8rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: background 0.2s;
            cursor: pointer;
            font-weight: 500;
        }
        .sidebar-menu a:hover, .sidebar-menu .menu-item:hover {
            background: var(--gray-100);
        }
        .submenu {
            list-style: none;
            padding-left: 2rem;
            display: none;
            background: var(--gray-50);
        }
        .submenu.show {
            display: block;
        }
        .submenu li a {
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
        }
        
        /* Cart specific styles */
        .cart-container { margin: 2rem 0; }
        .cart-layout { display: flex; gap: 2rem; flex-wrap: wrap; }
        .cart-items { flex: 2; min-width: 280px; }
        .cart-item {
            display: flex;
            gap: 1rem;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            transition: var(--shadow-md);
        }
        .cart-item:hover { box-shadow: var(--shadow-md); }
        .cart-item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .cart-item-details { flex: 1; }
        .cart-item-details h3 { margin: 0 0 0.25rem; font-size: 1.1rem; }
        .farmer-name { font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.5rem; }
        .price-info { margin-bottom: 0.5rem; }
        .unit-price { font-weight: 500; color: var(--primary-color); }
        .quantity-controls { display: flex; align-items: center; gap: 0.5rem; margin: 0.5rem 0; }
        .qty-btn {
            width: 32px;
            height: 32px;
            background: var(--gray-100);
            border: none;
            border-radius: 6px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .qty-btn:hover { background: var(--gray-200); }
        .quantity { min-width: 32px; text-align: center; }
        .stock-info { font-size: 0.75rem; color: var(--gray-500); }
        .item-subtotal { font-weight: 600; margin: 0.5rem 0; }
        .remove-btn {
            background: none;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            font-size: 0.85rem;
            padding: 0;
            text-decoration: underline;
        }
        .cart-summary {
            flex: 1;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 90px;
            align-self: start;
        }
        .cart-summary h3 { margin-top: 0; margin-bottom: 1rem; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 0.75rem; }
        .summary-row.total {
            font-weight: 700;
            font-size: 1.2rem;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--gray-200);
        }
        .empty-cart {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }
        .text-muted { color: var(--gray-500); font-size: 0.8rem; }
        .alert-warning {
            background-color: #fff3e0;
            color: #ff9800;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .navbar .container {
                flex-wrap: nowrap;
            }
            .search-container {
                margin: 0 0.5rem;
            }
            .search-input {
                font-size: 0.8rem;
                padding: 0.5rem 0.8rem;
            }
            .cart-layout { flex-direction: column; }
            .cart-item { flex-direction: column; align-items: center; text-align: center; }
            .cart-item-image { width: 150px; height: 150px; }
            .quantity-controls { justify-content: center; }
            .cart-summary { position: static; }
        }
        @media (max-width: 480px) {
            .hamburger {
                font-size: 1.5rem;
            }
            .right-icons .avatar-small {
                width: 28px;
                height: 28px;
            }
            .cart-icon {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>

<!-- ============================ HEADER (same as dashboard) ============================ -->
<nav class="navbar">
    <div class="container">
        <button class="hamburger" id="hamburgerBtn" aria-label="Menu">☰</button>
        
        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Search for products, farmers, or categories...">
        </div>
        
        <div class="right-icons">
            <div class="dropdown">
                <button class="dropdown-btn" id="userMenuBtn">
                    <img id="userAvatar" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 24 24' fill='%23999'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E" alt="Avatar" class="avatar-small">
                    <span id="userName"><?= htmlspecialchars($userName) ?></span>
                    <span class="dropdown-icon">▼</span>
                </button>
                <div class="dropdown-content" id="userDropdownContent">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="profile.php">Profile</a>
                    <a href="my-orders.php" id="buyerOrderLink">My Orders</a>
                    <a href="received-orders.php" id="farmerOrderLink" style="display:none;">Received Orders</a>
                    <a href="add-product.php" id="addProductLink" style="display:none;">Add Product</a>
                    <a href="admin.php" id="adminLink" style="display:none;">Admin Panel</a>
                    <a href="cart.php">Shopping Cart</a>
                    <hr>
                    <a href="#" id="logoutBtn">Logout</a>
                </div>
            </div>
            <a href="cart.php" class="cart-icon-link" id="cartIconLink">
                <span class="cart-icon">🛒</span>
                <span id="cartCount" class="cart-badge">0</span>
            </a>
        </div>
    </div>
</nav>

<!-- ============================ SIDEBAR (same as dashboard) ============================ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <strong>Menu</strong>
        <button class="close-sidebar" id="closeSidebarBtn">&times;</button>
    </div>
    <ul class="sidebar-menu">
        <li><a href="my-orders.php">📦 Orders</a></li>
        <li><a href="#">📥 Inbox</a></li>
        <li><a href="#">⭐ Pending Reviews</a></li>
        <li><a href="#">🎟️ Voucher</a></li>
        <li><a href="#">✅ Whitelist</a></li>
        <li>
            <div class="menu-item" id="categoryToggle">📂 Our Category ▼</div>
            <ul class="submenu" id="categorySubmenu">
                <li><a href="products.php?category=vegetables">🥬 Vegetables</a></li>
                <li><a href="products.php?category=fruits">🍎 Fruits</a></li>
                <li><a href="products.php?category=cleaning">🧹 Cleaning</a></li>
                <li><a href="services.php">🔧 List all services</a></li>
            </ul>
        </li>
        <li><a href="services.php">🛠️ Our Services</a></li>
        <li><a href="#">🏪 Sell on our website</a></li>
        <li><a href="#">❓ Help Center</a></li>
        <li><a href="#">📞 Contact Us</a></li>
    </ul>
</div>

<!-- ============================ MAIN CONTENT ============================ -->
<div class="container">
    <h1>Shopping Cart</h1>
    <div id="cartContent" class="cart-container">
        <div class="loading">Loading cart...</div>
    </div>
</div>

<script src="../assets/js/api.js"></script>
<script src="../assets/js/app.js"></script>
<script>
    // ======================== GLOBALS (from dashboard) ========================
    let currentUser = null;
    let searchTimeout = null;

    // PHP session data injected
    window.userData = {
        id: <?= json_encode($userId) ?>,
        name: <?= json_encode($userName) ?>,
        role: <?= json_encode($userRole) ?>,
        phone: <?= json_encode($userPhone) ?>
    };
    console.log('User data:', window.userData);

    // ======================== HELPER FUNCTIONS ========================
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Simple toast fallback
    function showToast(message, type) {
        alert(message); // For simplicity; you can replace with a nicer toast later
    }

function getProductImage(product) {
    if (!product) {
        return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E';
    }

    let imagePath = 
        product.primary_image || 
        product.image || 
        product.image_url || 
        product.image_path || 
        product.photo || 
        product.main_image;

    if (!imagePath || imagePath.trim() === '') {
        return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E';
    }

    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
        return imagePath;
    }

    if (imagePath.startsWith('/')) {
        return imagePath;
    }

    return '../assets/images/uploads/products/' + imagePath;
}

 function getFarmerAvatar(farmer) {
    let avatar = farmer?.profile_photo;

    // If farmer has uploaded image
    if (avatar && avatar.trim() !== '') {
        if (avatar.startsWith('http://') || avatar.startsWith('https://')) return avatar;
        if (avatar.startsWith('/')) return avatar;

        return '../assets/images/uploads/farmers/' + avatar;
    }

    // ✅ ONLINE AVATAR (fallback)
    const name = encodeURIComponent(farmer?.name || 'Farmer');

    return `https://ui-avatars.com/api/?name=${name}&background=2e7d32&color=fff&size=128`;
}

    // ======================== CART FUNCTIONS ========================
    async function loadCart() {
        if (!currentUser || !currentUser.id) {
            document.getElementById('cartContent').innerHTML = `
                <div class="empty-cart">
                    <p>Please login to view your cart.</p>
                    <a href="login.php" class="btn btn-primary">Login</a>
                </div>
            `;
            return;
        }
        
        try {
            const response = await API.get('/cart/get_cart.php');
            if (response.success && response.data) {
                renderCart(response.data);
            } else {
                document.getElementById('cartContent').innerHTML = '<div class="error">Failed to load cart. Please try again.</div>';
            }
        } catch (error) {
            console.error('Failed to load cart:', error);
            document.getElementById('cartContent').innerHTML = '<div class="error">Failed to load cart. Please try again.</div>';
        }
    }
    
    function renderCart(cart) {
        const container = document.getElementById('cartContent');
        
        if (!cart.items || cart.items.length === 0) {
            container.innerHTML = `
                <div class="empty-cart">
                    <p>Your cart is empty.</p>
                    <a href="products.php" class="btn btn-primary">Continue Shopping</a>
                </div>
            `;
            return;
        }
        
        const itemsHtml = cart.items.map(item => {
             console.log("CART ITEM PRODUCT:", item.product);
            const imageUrl = item.product.images?.[0] 
                || item.product.primary_image 
                || item.product.image 
                || '/trustfiles/assets/images/default/product-placeholder.jpg'
            const unitAbbr = item.product.unit_abbr || item.product.unit;
            const stock = item.product.stock_quantity;
            const isLowStock = stock < 10;
            
            return `
                <div class="cart-item" data-id="${item.cart_item_id}">
                    <img src="${imageUrl}" 
                         alt="${escapeHtml(item.product.name)}" 
                         class="cart-item-image"
                         onerror="handleImageError(this)">
                    <div class="cart-item-details">
                        <h3>${escapeHtml(item.product.name)}</h3>
                       <div class="farmer-info" style="display:flex; align-items:center; gap:8px;">
                            <img 
                                src="${getFarmerAvatar(item.product.farmer)}"
                                alt="Farmer"
                                style="width:30px; height:30px; border-radius:50%; object-fit:cover;"
                                onerror="handleImageError(this)">
                            
                            <p class="farmer-name">by ${escapeHtml(item.product.farmer.name)}</p>
                        </div>
                        <div class="price-info">
                            <span class="unit-price">KES ${item.product.price.toLocaleString()} / ${unitAbbr}</span>
                        </div>
                        <div class="quantity-controls">
                            <button class="qty-btn" onclick="updateQuantity(${item.cart_item_id}, ${item.quantity - 1})">-</button>
                            <span class="quantity">${item.quantity}</span>
                            <button class="qty-btn" onclick="updateQuantity(${item.cart_item_id}, ${item.quantity + 1})">+</button>
                            <span class="stock-info ${isLowStock ? 'low-stock' : ''}">
                                ${stock} available
                            </span>
                        </div>
                        <div class="item-subtotal">Subtotal: KES ${item.subtotal.toLocaleString()}</div>
                        <button class="remove-btn" onclick="removeItem(${item.cart_item_id})">Remove</button>
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = `
            <div class="cart-layout">
                <div class="cart-items">
                    ${itemsHtml}
                </div>
                
                <div class="cart-summary">
                    <h3>Order Summary</h3>
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>KES ${cart.summary.subtotal.toLocaleString()}</span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery Fee:</span>
                        <span>KES ${cart.summary.delivery_fee.toLocaleString()}</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>KES ${cart.summary.total.toLocaleString()}</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="deliveryLocation">Delivery Location *</label>
                        <input type="text" id="deliveryLocation" placeholder="Your estate/ward, e.g., Kilimani" required>
                        <small class="text-muted">Please enter your delivery location</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructions">Special Instructions (Optional)</label>
                        <textarea id="instructions" rows="2" placeholder="E.g., Call before delivery, leave at gate..."></textarea>
                    </div>
                    
                    ${cart.summary.has_invalid_items ? `
                        <div class="alert-warning">
                            ⚠️ Some items are no longer available or out of stock. Please remove them to proceed.
                        </div>
                    ` : ''}
                    
                    <button class="btn btn-primary btn-large" onclick="checkout()" ${cart.summary.has_invalid_items ? 'disabled' : ''}>
                        Proceed to Checkout
                    </button>
                    
                    <a href="products.php" class="btn btn-outline btn-large">Continue Shopping</a>
                </div>
            </div>
        `;
    }
    
    async function updateQuantity(cartItemId, newQuantity) {
        if (newQuantity < 1) {
            await removeItem(cartItemId);
            return;
        }
        
        try {
            const response = await API.put('/cart/update_cart.php', { 
                cart_item_id: cartItemId, 
                quantity: newQuantity 
            });
            
            if (response.success) {
                loadCart(); // Reload cart to reflect changes
                updateCartCount();
                showToast('Cart updated', 'success');
            } else {
                showToast(response.message || 'Failed to update quantity', 'error');
            }
        } catch (error) {
            console.error('Update quantity error:', error);
            showToast(error.message || 'Failed to update quantity', 'error');
        }
    }
    
    async function removeItem(cartItemId) {
        if (!confirm('Remove this item from cart?')) return;
        
        try {
            const response = await API.delete(`/cart/remove_from_cart.php?cart_item_id=${cartItemId}`);
            if (response.success) {
                showToast('Item removed from cart', 'success');
                loadCart(); // Reload cart
                updateCartCount();
            } else {
                showToast(response.message || 'Failed to remove item', 'error');
            }
        } catch (error) {
            console.error('Remove item error:', error);
            showToast(error.message || 'Failed to remove item', 'error');
        }
    }
    
    async function checkout() {
        const location = document.getElementById('deliveryLocation')?.value.trim();
        const instructions = document.getElementById('instructions')?.value.trim();
        
        if (!location) {
            showToast('Please enter your delivery location', 'error');
            document.getElementById('deliveryLocation')?.focus();
            return;
        }
        
        if (location.length < 3) {
            showToast('Please enter a valid delivery location', 'error');
            return;
        }
        
        const checkoutBtn = document.querySelector('.btn-primary.btn-large');
        if (checkoutBtn) {
            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Processing...';
        }
        
        try {
            const response = await API.post('/orders/place_order.php', {
                location: location,
                instructions: instructions,
                payment_method: 'cash'
            });
            
            if (response.success) {
                showToast('Order placed successfully!', 'success');
                setTimeout(() => {
                    window.location.href = 'my-orders.php';
                }, 1500);
            } else {
                showToast(response.message || 'Failed to place order', 'error');
                if (checkoutBtn) {
                    checkoutBtn.disabled = false;
                    checkoutBtn.textContent = 'Proceed to Checkout';
                }
            }
        } catch (error) {
            console.error('Checkout error:', error);
            showToast(error.message || 'Failed to place order. Please try again.', 'error');
            if (checkoutBtn) {
                checkoutBtn.disabled = false;
                checkoutBtn.textContent = 'Proceed to Checkout';
            }
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
            console.error('Cart count error:', error);
        }
    }

                function handleImageError(img) {
                    // If already fallback, stop loop
                    if (img.src.startsWith('data:image/svg+xml')) return;

                    // Check if it's a small image (avatar)
                    if (img.width <= 50) {
                        img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100"%3E%3Ccircle cx="50" cy="50" r="40" fill="%23ccc"/%3E%3C/svg%3E';
                    } else {
                        img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" font-family="Arial" font-size="14" fill="%23999" text-anchor="middle" dy=".3em"%3ENo Image%3C/text%3E%3C/svg%3E';
                        img.style.objectFit = 'contain';
                        img.style.backgroundColor = '#f5f5f5';
                    }
                }

    // ======================== SIDEBAR & UI INTERACTIONS (from dashboard) ========================
    function initSidebar() {
        const hamburger = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const closeBtn = document.getElementById('closeSidebarBtn');
        
        function openSidebar() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        if (hamburger) hamburger.addEventListener('click', openSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
        
        const categoryToggle = document.getElementById('categoryToggle');
        const submenu = document.getElementById('categorySubmenu');
        if (categoryToggle) {
            categoryToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                submenu.classList.toggle('show');
                categoryToggle.innerHTML = submenu.classList.contains('show') ? '📂 Our Category ▲' : '📂 Our Category ▼';
            });
        }
    }
    
    function initSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value;
                searchTimeout = setTimeout(() => {
                    if (query.trim()) {
                        window.location.href = `products.php?search=${encodeURIComponent(query)}`;
                    }
                }, 500);
            });
        }
    }
    
    function initLogout() {
        document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await API.post('/auth/logout.php');
                window.location.href = 'login.php';
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = 'login.php';
            }
        });
    }
    
    function initUserDropdown() {
        const dropdownBtn = document.getElementById('userMenuBtn');
        const dropdownContent = document.getElementById('userDropdownContent');
        if (dropdownBtn && dropdownContent) {
            dropdownBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                dropdownContent.classList.toggle('show');
            });
            document.addEventListener('click', (event) => {
                if (!dropdownBtn.contains(event.target) && !dropdownContent.contains(event.target)) {
                    dropdownContent.classList.remove('show');
                }
            });
        }
    }
    
    async function loadUserProfile() {
        try {
            const response = await API.get('/users/get_profile.php');
            if (response.success && response.data) {
                currentUser = response.data;
            } else if (window.userData.id) {
                currentUser = window.userData;
            } else {
                currentUser = { id: null, name: 'User', role: 'guest' };
            }
            // Update UI
            document.getElementById('userName').textContent = currentUser.name?.split(' ')[0] || 'User';
            if (currentUser.role === 'farmer') {
                const farmerLink = document.getElementById('farmerOrderLink');
                const addLink = document.getElementById('addProductLink');
                if (farmerLink) farmerLink.style.display = 'block';
                if (addLink) addLink.style.display = 'block';
            } else if (currentUser.role === 'admin') {
                const adminLink = document.getElementById('adminLink');
                if (adminLink) adminLink.style.display = 'block';
            }
        } catch(e) {
            console.warn('Could not load full profile', e);
            currentUser = window.userData;
        }
    }

    // ======================== INITIALIZATION ========================
    document.addEventListener('DOMContentLoaded', async () => {
        await loadUserProfile();
        initSidebar();
        initSearch();
        initLogout();
        initUserDropdown();
        await loadCart();
        await updateCartCount();
    });
</script>
</body>
</html>