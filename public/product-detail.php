<?php
// ============================================================================
// TRUSTLINK - Product Detail Page (Modern Layout)
// Version: 2.0 | March 2026
// ============================================================================

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/constants.php';

use TrustLink\Config\SessionKeys;

// Error reporting – disable in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Get user session data (same as dashboard.php)
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
    <title>Product Details - TrustLink</title>
    
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
        
        /* Product detail specific styles */
        .product-detail-container {
            max-width: 1200px;
            margin: 2rem auto;
        }
        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .product-gallery {
            position: relative;
        }
        .main-image {
            width: 100%;
            height: auto;
            border-radius: var(--radius-lg);
            object-fit: cover;
        }
        .product-info-detail h1 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        .product-meta {
            margin-bottom: 1.5rem;
        }
        .price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stock {
            display: inline-block;
            margin-left: 1rem;
            font-size: 0.9rem;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
        }
        .in-stock {
            background: #e8f5e9;
            color: var(--success-color);
        }
        .out-of-stock {
            background: #ffebee;
            color: var(--danger-color);
        }
        .seller-info {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }
        .seller-avatar img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .seller-details h4 {
            margin: 0 0 0.5rem;
        }
        .trust-info {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 0.5rem;
        }
        .trust-badge-large {
            font-size: 1.2rem;
        }
        .trust-score {
            font-weight: 600;
        }
        .verification-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .badge-basic { background: #e0e0e0; color: #666; }
        .badge-trusted { background: #e8f5e9; color: #2e7d32; }
        .badge-premium { background: linear-gradient(135deg, #ffd700, #ffb347); color: #5d3a1a; }
        .product-description {
            margin: 1.5rem 0;
        }
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .quantity-selector label {
            font-weight: 500;
        }
        .qty-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--gray-300);
            background: white;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 1.2rem;
        }
        .quantity-selector input {
            width: 60px;
            text-align: center;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
        }
        .reviews-section {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .rating-summary {
            margin-bottom: 1.5rem;
        }
        .average-rating {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .big-rating {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stars {
            font-size: 1.2rem;
        }
        .review-item {
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 0;
        }
        .review-header {
            display: flex;
            gap: 1rem;
            align-items: baseline;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        .review-stars {
            color: #ffc107;
        }
        .review-date {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        .farmer-reply {
            margin-top: 0.8rem;
            padding: 0.8rem;
            background: var(--gray-50);
            border-left: 3px solid var(--primary-color);
            border-radius: var(--radius-md);
        }
        .farmer-reply-form {
            margin-top: 0.8rem;
        }
        .farmer-reply-form textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
        }
        .related-products {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            padding: 2rem;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .product-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s;
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .product-card .product-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .product-card .product-info {
            padding: 0.8rem;
        }
        .product-card h3 {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-card .product-price {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .empty-state, .loading {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
        }
        .btn-sm {
            padding: 0.3rem 0.8rem;
            font-size: 0.8rem;
        }
       
        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
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
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            .action-buttons {
                flex-direction: column;
            }
        }
        @media (max-width: 480px) {
            .hamburger {
                font-size: 1.5rem;
            }
            .right-icons .avatar-small {
                width: 28px;
                height: 28px;
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
<div class="container product-detail-container">
    <div id="productContent">
        <div class="loading">Loading product details...</div>
    </div>
</div>

<script src="../assets/js/api.js"></script>
<script src="../assets/js/app.js"></script>
<script>
    // ======================== GLOBALS (from dashboard) ========================
    let currentUser = null;
    let searchTimeout = null;
    let productId = null;
    let productFarmerId = null;
    let quantity = 1;

    // PHP session data injected
    window.userData = {
        id: <?= json_encode($userId) ?>,
        name: <?= json_encode($userName) ?>,
        role: <?= json_encode($userRole) ?>,
        phone: <?= json_encode($userPhone) ?>
    };
    console.log('User data:', window.userData);

    // ======================== HELPER FUNCTIONS (same as dashboard) ========================
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function renderStars(rating) {
        const fullStars = Math.floor(rating);
        const halfStar = rating % 1 >= 0.5;
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
        let html = '';
        for (let i = 0; i < fullStars; i++) html += '⭐';
        if (halfStar) html += '½';
        for (let i = 0; i < emptyStars; i++) html += '☆';
        return html;
    }

        function getProductImage(product) {
            if (!product) {
                return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E';
            }

            let imagePath = product.primary_image || product.image || product.image_url || product.image_path || product.photo || product.profile_image;

            if (!imagePath || imagePath.trim() === '') {
                return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E';
            }

            if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
                return imagePath;
            }

            if (imagePath.startsWith('/trustfiles/') || imagePath.startsWith('/')) {
                return imagePath;
            }

            return '../assets/images/uploads/products/' + imagePath;
        }

    function handleImageError(img) {
        if (img.src.startsWith('data:image/svg+xml')) return;
        img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" font-family="Arial" font-size="14" fill="%23999" text-anchor="middle" dy=".3em"%3ENo Image%3C/text%3E%3C/svg%3E';
        img.style.objectFit = 'contain';
        img.style.backgroundColor = '#f5f5f5';
    }

    // ======================== PRODUCT DETAIL LOGIC ========================
    async function loadProduct() {
        const container = document.getElementById('productContent');
        try {
            const response = await API.get('/products/get_single_product.php', { id: productId });
            if (response.success && response.data) {
                console.log("FULL PRODUCT DATA:", response.data);
                renderProduct(response.data);
            } else {
                container.innerHTML = '<div class="error">Product not found</div>';
            }
        } catch (error) {
            console.error('Failed to load product:', error);
            container.innerHTML = '<div class="error">Failed to load product</div>';
        }
    }

    function renderProduct(product) {
        productFarmerId = product.farmer.id;
        const placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 24 24' fill='%23999'%3E%3Crect width='24' height='24' fill='%23ccc'/%3E%3C/svg%3E";
                let mainImage = '';

            if (product.images && product.images.length > 0) {
                mainImage = product.images[0].url;
            } else {
                mainImage = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E';
            }

            console.log("CORRECT IMAGE:", mainImage);
        console.log("IMAGE PATH:", mainImage);

        const container = document.getElementById('productContent');
        container.innerHTML = `
            <div class="product-detail">
                <div class="product-gallery">
                    <img src="${mainImage}" alt="${escapeHtml(product.name)}" class="main-image" onerror="handleImageError(this)">
                </div>
                <div class="product-info-detail">
                    <h1>${escapeHtml(product.name)}</h1>
                    <div class="product-meta">
                        <span class="price">KES ${product.price.toLocaleString()} / ${product.unit_abbr}</span>
                        <span class="stock ${product.quantity > 0 ? 'in-stock' : 'out-of-stock'}">
                            ${product.quantity > 0 ? `✓ ${product.quantity} ${product.unit_abbr} available` : '✗ Out of stock'}
                        </span>
                    </div>
                    <div class="seller-info">
                    <div class="seller-avatar">
                        <img 
                            src="${
                                product.farmer.profile_photo 
                                ? product.farmer.profile_photo 
                                : `https://ui-avatars.com/api/?name=${encodeURIComponent(product.farmer.name || 'Farmer')}&background=2e7d32&color=fff&size=128`
                            }"
                            alt="Farmer"
                            onerror="handleImageError(this)">
                    </div>
                        <div class="seller-details">
                            <h4>${escapeHtml(product.farmer.name)}</h4>
                            <div class="trust-info">
                                <span class="trust-badge-large">${product.farmer.trust_badge?.icon || '🌱'}</span>
                                <span class="trust-score">${(product.farmer.trust_score || 0).toFixed(1)} ★</span>
                                <span class="verification-badge badge-${product.farmer.verification_tier || 'basic'}">
                                    ${product.farmer.verification_display || product.farmer.verification_tier || 'Basic'}
                                </span>
                            </div>
                            <div class="seller-location">📍 ${product.farmer.location?.county || 'Kenya'}</div>
                        </div>
                    </div>
                    <div class="product-description">
                        <h3>Description</h3>
                        <p>${escapeHtml(product.description) || 'No description provided.'}</p>
                    </div>
                    <div class="quantity-selector">
                        <label>Quantity:</label>
                        <button class="qty-btn" onclick="updateQuantity(-1)">-</button>
                        <input type="number" id="quantity" value="1" min="1" max="${product.quantity}" onchange="validateQuantity()">
                        <button class="qty-btn" onclick="updateQuantity(1)">+</button>
                        <span class="unit">${product.unit_abbr}</span>
                    </div>
                    <div class="action-buttons">
                        <button class="btn btn-primary btn-large" onclick="addToCart()" ${product.quantity <= 0 ? 'disabled' : ''}>
                            🛒 Add to Cart
                        </button>
                        <button class="btn btn-outline btn-large" onclick="buyNow()" ${product.quantity <= 0 ? 'disabled' : ''}>
                            Buy Now
                        </button>
                    </div>
                </div>
            </div>
            <div class="reviews-section">
                <h3>Customer Reviews</h3>
                <div id="reviewsContainer" class="reviews-list">
                    <div class="loading">Loading reviews...</div>
                </div>
            </div>
            <div class="related-products">
                <h3>You May Also Like</h3>
                <div id="relatedProducts" class="products-grid">
                    <div class="loading">Loading recommendations...</div>
                </div>
            </div>
        `;

        loadProductReviews(product.id);
        if (product.related_products) renderRelatedProducts(product.related_products);
    }

    async function loadProductReviews(productId) {
        const container = document.getElementById('reviewsContainer');
        if (!container) return;
        try {
            const response = await API.get('/reviews/get_product_reviews.php', { product_id: productId });
            if (response.success && response.data) {
                renderReviews(response.data);
            } else {
                container.innerHTML = '<div class="empty-state">No reviews yet. Be the first to review!</div>';
            }
        } catch (error) {
            console.error('Failed to load reviews:', error);
            container.innerHTML = '<div class="error">Failed to load reviews</div>';
        }
    }

    function renderReviews(data) {
        const container = document.getElementById('reviewsContainer');
        if (!container) return;

        let summaryHtml = '';
        if (data.rating_summary) {
            summaryHtml = `
                <div class="rating-summary">
                    <div class="average-rating">
                        <span class="big-rating">${data.rating_summary.average.toFixed(1)}</span>
                        <span class="stars">${renderStars(data.rating_summary.average)}</span>
                        <span class="review-count">(${data.rating_summary.total} reviews)</span>
                    </div>
                </div>
            `;
        }

        if (!data.reviews || data.reviews.length === 0) {
            container.innerHTML = summaryHtml + '<div class="empty-state">No reviews yet. Be the first to review!</div>';
            return;
        }

        const isFarmer = currentUser && currentUser.role === 'farmer' && Number(currentUser.id) === Number(productFarmerId);
        const reviewsHtml = data.reviews.map(review => {
            let replyHtml = '';
            if (review.farmer_reply) {
                replyHtml = `
                    <div class="farmer-reply">
                        <strong>Seller's response:</strong>
                        <p>${escapeHtml(review.farmer_reply)}</p>
                        <small>Replied on ${new Date(review.farmer_replied_at).toLocaleDateString()}</small>
                    </div>
                `;
            } else if (isFarmer) {
                replyHtml = `
                    <div class="farmer-reply-form">
                        <textarea id="reply-${review.id}" placeholder="Write your reply..." rows="2" maxlength="500"></textarea>
                        <button class="btn btn-sm btn-primary reply-btn" data-review-id="${review.id}">Post Reply</button>
                    </div>
                `;
            }
            return `
                <div class="review-item" data-review-id="${review.id}">
                    <div class="review-header">
                        <strong>${escapeHtml(review.buyer_name || 'Anonymous')}</strong>
                        <span class="review-stars">${renderStars(review.rating)}</span>
                        <span class="review-date">${review.time_ago || new Date(review.created_at).toLocaleDateString()}</span>
                    </div>
                    <div class="review-comment">${escapeHtml(review.comment)}</div>
                    ${replyHtml}
                </div>
            `;
        }).join('');

        container.innerHTML = summaryHtml + `<div class="all-reviews">${reviewsHtml}</div>`;

        // Event delegation for reply buttons
        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.reply-btn');
            if (btn) {
                const reviewId = btn.getAttribute('data-review-id');
                if (reviewId) submitReply(reviewId);
            }
        });
    }

    async function submitReply(reviewId) {
        const textarea = document.getElementById(`reply-${reviewId}`);
        const reply = textarea.value.trim();
        if (!reply) {
            alert('Please enter a reply');
            return;
        }
        try {
            const response = await API.post('/reviews/reply_to_review.php', {
                review_id: reviewId,
                reply: reply
            });
            if (response.success) {
                loadProductReviews(productId);
            } else {
                alert(response.message || 'Failed to post reply');
            }
        } catch (error) {
            console.error('Reply error:', error);
            alert('Failed to post reply');
        }
    }

    function renderRelatedProducts(products) {
        const container = document.getElementById('relatedProducts');
        if (!container) return;
        if (!products || products.length === 0) {
            container.innerHTML = '<div class="empty-state">No related products found.</div>';
            return;
        }
        const placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 24 24' fill='%23999'%3E%3Crect width='24' height='24' fill='%23ccc'/%3E%3C/svg%3E";
        container.innerHTML = products.map(product => `
            <div class="product-card" onclick="location.href='product-detail.php?id=${product.id}'">
               <img src="${product.primary_image || 'data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" width=\"200\" height=\"200\"%3E%3Crect width=\"200\" height=\"200\" fill=\"%23ccc\"/%3E%3C/svg%3E'}">
                <div class="product-info">
                    <h3>${escapeHtml(product.name)}</h3>
                    <div class="product-price">KES ${product.price.toLocaleString()} / ${product.unit_abbr}</div>
                </div>
            </div>
        `).join('');
    }

    // ======================== CART ACTIONS ========================
    function updateQuantity(delta) {
        const input = document.getElementById('quantity');
        let newVal = parseInt(input.value) + delta;
        const max = parseInt(input.max) || 999;
        newVal = Math.max(1, Math.min(newVal, max));
        input.value = newVal;
        quantity = newVal;
    }

    function validateQuantity() {
        const input = document.getElementById('quantity');
        let val = parseInt(input.value);
        const max = parseInt(input.max) || 999;
        if (isNaN(val) || val < 1) val = 1;
        if (val > max) val = max;
        input.value = val;
        quantity = val;
    }

    async function addToCart() {
        if (!currentUser || !currentUser.id) {
            if (confirm('Please login to add items to cart. Go to login page?')) {
                window.location.href = 'login.php';
            }
            return;
        }
        const qty = parseInt(document.getElementById('quantity').value);
        try {
            const response = await API.post('/cart/add_to_cart.php', { product_id: productId, quantity: qty });
            if (response.success) {
                alert('Product added to cart!');
                updateCartCount();
            } else {
                alert(response.message || 'Failed to add to cart');
            }
        } catch (error) {
            alert(error.message || 'Failed to add to cart');
        }
    }

    async function buyNow() {
        if (!currentUser || !currentUser.id) {
            window.location.href = 'login.php';
            return;
        }
        const qty = parseInt(document.getElementById('quantity').value);
        try {
            await API.post('/cart/add_to_cart.php', { product_id: productId, quantity: qty });
            window.location.href = 'cart.php';
        } catch (error) {
            alert(error.message || 'Failed to add to cart');
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
        // Get product ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        productId = urlParams.get('id');
        if (!productId) {
            window.location.href = 'products.php';
            return;
        }

        await loadUserProfile();
        initSidebar();
        initSearch();
        initLogout();
        initUserDropdown();
        await loadProduct();
        await updateCartCount();
    });
</script>
</body>
</html>