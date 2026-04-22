 









<?php
// ============================================================================
// TRUSTLINK - Production Dashboard (E‑commerce Homepage)
// Version: 2.0 | March 2026
// ============================================================================

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/constants.php';

use TrustLink\Config\SessionKeys;

// Enable error reporting only in development – disable in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Check login
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'buyer';
$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>TrustLink – Farmers & Buyers Marketplace</title>
    
    <!-- JetBrains Mono – the only font used everywhere -->
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- External stylesheets (keep your existing ones) -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <style>
        /* ===== OVERRIDES & ADDITIONS ===== */
        * {
            font-family: 'JetBrains Mono', monospace !important;
        }
        
        /* Modern reset / enhancements */
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
        
        /* Product grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .product-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .product-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: var(--gray-200);
        }
        .product-info {
            padding: 1rem;
        }
        .product-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .product-farmer {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid var(--primary-light);
            display: inline-block;
        }
        .loading-skeleton {
            background: linear-gradient(90deg, var(--gray-200) 25%, var(--gray-100) 50%, var(--gray-200) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: var(--radius-md);
        }
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Responsive */
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
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 1rem;
            }
            .product-info {
                padding: 0.75rem;
            }
            .product-title {
                font-size: 0.85rem;
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
            .cart-icon {
                font-size: 1.2rem;
            }
        }
        
        /* Utility */
        .empty-message {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
            background: white;
            border-radius: var(--radius-lg);
        }
    </style>
</head>
<body>

<!-- ============================ HEADER ============================ -->
<nav class="navbar">
    <div class="container">
        <!-- Hamburger menu button -->
        <button class="hamburger" id="hamburgerBtn" aria-label="Menu">☰</button>
        
        <!-- Centered search input -->
        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Search for products, farmers, or categories...">
        </div>
        
        <!-- Right side: user avatar + cart -->
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
                    <a href="my-products.php" id="myProductsLink" style="display:none;">My Products</a>
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

<!-- ============================ SIDEBAR (HAMBURGER MENU) ============================ -->
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
    <!-- Products Grid (search results) -->
    <h2 class="section-title">🛍️ Products</h2>
    <div id="productsGrid" class="products-grid">
        <div class="loading-skeleton" style="height: 280px;"></div>
        <div class="loading-skeleton" style="height: 280px;"></div>
        <div class="loading-skeleton" style="height: 280px;"></div>
    </div>
    
    <!-- Recently Viewed Products -->
    <h2 class="section-title">⏱️ Recently Viewed</h2>
    <div id="recentlyViewedGrid" class="products-grid">
        <div class="empty-message">Loading...</div>
    </div>
    
    <!-- Recommended Products -->
    <h2 class="section-title">✨ Recommended for You</h2>
    <div id="recommendedGrid" class="products-grid">
        <div class="empty-message">Loading recommendations...</div>
    </div>
</div>
<script src="/assets/js/api.js"></script>

<script>
    // ======================== GLOBALS ========================
    let currentUser = null;
    let allProducts = [];          // store current product list for search
    let searchTimeout = null;
    
    // Recently viewed: store product IDs in localStorage
    const RECENT_KEY = 'trustlink_recent_products';
    
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

/**
 * Safely determines the product image path.
 * Uses relative paths to ensure it works regardless of the domain name.
 */
function getProductImage(product) {
    let imagePath = product.primary_image || product.image || product.image_url || product.image_path || product.photo;
    
    if (!imagePath || imagePath.trim() === '') {
        // Return SVG placeholder if no image provided
        return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" font-family="Arial" font-size="14" fill="%23999" text-anchor="middle" dy=".3em"%3ENo Image%3C/text%3E%3C/svg%3E';
    }
    
    // If it's already a full URL (starts with http:// or https://), use it directly
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
        return imagePath;
    }
    
    // If it's an absolute path starting with /trustfiles/ or /, use as is
    if (imagePath.startsWith('/trustfiles/') || imagePath.startsWith('/')) {
        return imagePath;
    }
    
    // If it's a relative path (like 'product_39_xxx.jpg'), build the correct relative URL
    // Since dashboard.php is in /public/, we need to go up one level to reach /trustfiles/
    return '../assets/images/uploads/products/' + imagePath;
}

function renderProductCard(product) {
    if (!product) return '';
    
    const imgUrl = getProductImage(product);
    const jpgPath = '../assets/images/placeholder.jpg';
    const pngPath = '../assets/images/placeholder.png'; // Adding PNG support
    
    const formattedPrice = Number(product.price || 0).toLocaleString();
    const farmerDisplay = escapeHtml(product.farmer_name || (product.farmer && product.farmer.name) || 'Farmer');

    return `
        <div class="product-card" data-product-id="${product.id}">
           <img class="product-image" 
     src="${imgUrl}" 
     alt="${escapeHtml(product.name)}"
     onerror="handleImageError(this)">
            <div class="product-info">
                <div class="product-title">${escapeHtml(product.name)}</div>
                <div class="product-price">KES ${formattedPrice}</div>
                <div class="product-farmer">👨‍🌾 ${farmerDisplay}</div>
            </div>
        </div>
    `;
}

// New helper function to cycle through image types
function handleImageError(img) {
    // If the image already shows our data URI, do nothing
    if (img.src.startsWith('data:image/svg+xml')) return;
    // Replace broken image with the same data URI
    img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" font-family="Arial" font-size="14" fill="%23999" text-anchor="middle" dy=".3em"%3ENo Image%3C/text%3E%3C/svg%3E';
    img.style.objectFit = 'contain';
    img.style.backgroundColor = '#f5f5f5';
}
    
    // Load products from API (with optional search query)
    async function loadProducts(searchQuery = '') {
        const grid = document.getElementById('productsGrid');
        grid.innerHTML = '<div class="loading-skeleton" style="height:280px;"></div><div class="loading-skeleton" style="height:280px;"></div><div class="loading-skeleton" style="height:280px;"></div>';
        
        try {
            let url = '/products/get_products.php?per_page=20';
            if (searchQuery) {
                url += `&search=${encodeURIComponent(searchQuery)}`;
            }
            const response = await API.get(url);
            if (response.success && response.data) {
                console.log('Sample product:', response.data[0]);
                allProducts = response.data;
                
                if (allProducts.length === 0) {
                    grid.innerHTML = '<div class="empty-message">No products found. Try a different search.</div>';
                } else {
                    grid.innerHTML = allProducts.map(p => renderProductCard(p)).join('');
                    attachProductClickEvents();
                }
            } else {
                grid.innerHTML = '<div class="empty-message">Failed to load products. Please try again.</div>';
            }
        } catch (error) {
            console.error('Error loading products:', error);
            grid.innerHTML = '<div class="empty-message">Error loading products.</div>';
        }
    }
    
    // Recently viewed logic
    function getRecentlyViewedIds() {
        const stored = localStorage.getItem(RECENT_KEY);
        if (!stored) return [];
        try {
            return JSON.parse(stored);
        } catch(e) { return []; }
    }
    
    function addToRecentlyViewed(productId) {
        let ids = getRecentlyViewedIds();
        ids = ids.filter(id => id != productId);
        ids.unshift(productId);
        if (ids.length > 12) ids.pop(); // keep last 12
        localStorage.setItem(RECENT_KEY, JSON.stringify(ids));
        loadRecentlyViewed(); // refresh the section
    }
    
    async function loadRecentlyViewed() {
        const container = document.getElementById('recentlyViewedGrid');
        const ids = getRecentlyViewedIds();
        if (ids.length === 0) {
            container.innerHTML = '<div class="empty-message">No recently viewed products.</div>';
            return;
        }
        container.innerHTML = '<div class="loading-skeleton" style="height:280px;"></div>';
        try {
            // Fetch each product individually? Better to use a batch endpoint if available.
            // Simulate: we fetch all products and filter.
            const response = await API.get('/products/get_products.php?per_page=100');
            if (response.success && response.data) {
                const recentProducts = response.data.filter(p => ids.includes(p.id));
                if (recentProducts.length === 0) {
                    container.innerHTML = '<div class="empty-message">No recently viewed products.</div>';
                } else {
                    container.innerHTML = recentProducts.map(p => renderProductCard(p)).join('');
                    attachProductClickEvents();
                }
            } else {
                container.innerHTML = '<div class="empty-message">Could not load recently viewed.</div>';
            }
        } catch(e) {
            console.error(e);
            container.innerHTML = '<div class="empty-message">Error loading recently viewed.</div>';
        }
    }
    
    // Recommended products (use API if available, else fallback to random products)
    async function loadRecommended() {
        const container = document.getElementById('recommendedGrid');
        container.innerHTML = '<div class="loading-skeleton" style="height:280px;"></div>';
        try {
            // Try to get personalized recommendations from backend
            let response = await API.get('/products/get_recommended.php?limit=8');
            if (response.success && response.data && response.data.length) {
                container.innerHTML = response.data.map(p => renderProductCard(p)).join('');
                attachProductClickEvents();
                return;
            }
        } catch(e) { /* fallback */ }
        
        // Fallback: fetch random products from all products
        try {
            const fallbackRes = await API.get('/products/get_products.php?per_page=12&sort=random');
            if (fallbackRes.success && fallbackRes.data) {
                container.innerHTML = fallbackRes.data.map(p => renderProductCard(p)).join('');
                attachProductClickEvents();
            } else {
                container.innerHTML = '<div class="empty-message">Recommendations unavailable.</div>';
            }
        } catch(err) {
            container.innerHTML = '<div class="empty-message">Could not load recommendations.</div>';
        }
    }
    
    // Attach click listeners to product cards (for tracking recent views and redirect)
    function attachProductClickEvents() {
        document.querySelectorAll('.product-card').forEach(card => {
            card.removeEventListener('click', productClickHandler);
            card.addEventListener('click', productClickHandler);
        });
    }
    
    function productClickHandler(e) {
        // Prevent if click originated from a link inside? but we have no inner links.
        const productId = this.dataset.productId;
        const productName = this.dataset.productName;
        if (productId) {
            addToRecentlyViewed(parseInt(productId));
            // Redirect to product detail page (adjust to your actual detail page)
            window.location.href = `product-detail.php?id=${productId}`;
        }
    }
    
    // Update cart count badge
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
    
    // Load user profile and show role-specific menu items
    async function loadUserProfile() {
        try {
            const response = await API.get('/users/get_profile.php');
            if (response.success && response.data) {
                currentUser = response.data;
            } else if (window.userData) {
                currentUser = window.userData;
            } else {
                currentUser = { id: <?= json_encode($userId) ?>, name: <?= json_encode($userName) ?>, role: <?= json_encode($userRole) ?> };
            }
            // Update UI
            document.getElementById('userName').textContent = currentUser.name.split(' ')[0];
            // Role-specific links
            if (currentUser.role === 'farmer') {
                const farmerLink = document.getElementById('farmerOrderLink');
                const addLink = document.getElementById('addProductLink');
                  const myProductsLink = document.getElementById('myProductsLink'); // 👈 ADD THIS
                if (farmerLink) farmerLink.style.display = 'block';
                if (addLink) addLink.style.display = 'block';
                if (myProductsLink) myProductsLink.style.display = 'block';
            } else if (currentUser.role === 'admin') {
                const adminLink = document.getElementById('adminLink');
                if (adminLink) adminLink.style.display = 'block';
            }
        } catch(e) {
            console.warn('Could not load full profile', e);
        }
    }
    
    // ======================== SIDEBAR & UI INTERACTIONS ========================
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
        hamburger.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
        
        // Submenu toggle for "Our Category"
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
    
    // Search with debounce
    function initSearch() {
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value;
            searchTimeout = setTimeout(() => {
                loadProducts(query);
            }, 500);
        });
    }
    
    // Logout
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
    
    // Dropdown for user menu
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
    
    // ======================== INITIALIZATION ========================
    document.addEventListener('DOMContentLoaded', async () => {
        // Pass PHP session data to JS
        window.userData = {
            id: <?= json_encode($userId) ?>,
            name: <?= json_encode($userName) ?>,
            role: <?= json_encode($userRole) ?>
        };
        
        initSidebar();
        initSearch();
        initLogout();
        initUserDropdown();
        
        await loadUserProfile();
        await loadProducts('');
        await loadRecentlyViewed();
        await loadRecommended();
        await updateCartCount();
    });
</script>
</body>
</html>



