<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Start session and check authentication
session_start();

 

// Get user info from session (if logged in)
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'buyer';
$userPhone = $_SESSION['user_phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Products - TrustLink</title>
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
            <div class="nav-links" id="navLinks">
                <a href="products.php" class="nav-link active">Browse Products</a>
                    <a href="cart.php" id="cartIcon" class="cart-icon-link">
                        <span class="cart-icon">🛒</span>
                        <span id="cartCount" class="cart-badge">0</span>
                    </a>
                <div id="authLinks">
                    <a href="login.php" class="btn btn-outline">Login</a>
                    <a href="register.php" class="btn btn-primary">Sign Up</a>
                </div>
                <div id="userMenu" style="display: none;">
                    <div class="dropdown">
                        <button class="dropdown-btn" id="userMenuBtn">
                            <img id="userAvatar" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 24 24' fill='%23999'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E" alt="Avatar" class="avatar-small">
                            <span id="userName">Loading...</span>
                            <span class="dropdown-icon">▼</span>
                        </button>
                        <div class="dropdown-content">
                            <a href="dashboard.php">Dashboard</a>
                            <a href="profile.php">Profile</a>
                            <a href="cart.php">Cart</a>
                            <a href="my-orders.php">My Orders</a>
                            <a href="add-product.php" id="addProductLink" style="display:none;">Add Product</a>
                            <hr>
                            <a href="#" id="logoutBtn">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="products-layout">
            <!-- Filters Sidebar -->
            <button id="filterToggleBtn" class="filter-toggle-btn" aria-label="Show filters">
                <span>🔍</span> Filters
            </button>
            <aside class="filters-sidebar">
                <div class="filter-header">
                    <h3>Filters</h3>
                    <button class="close-filters-btn" id="closeFiltersBtn" aria-label="Close filters">✕</button>
                </div>
                
                <div class="filter-group">
                    <label for="searchInput">Search</label>
                    <input type="text" id="searchInput" placeholder="Search products...">
                </div>
                
                <div class="filter-group">
                    <label for="categorySelect">Category</label>
                    <select id="categorySelect">
                        <option value="">All Categories</option>
                        <option value="vegetables">🥬 Vegetables</option>
                        <option value="fruits">🍎 Fruits</option>
                        <option value="dairy">🥛 Dairy</option>
                        <option value="grains">🌾 Grains</option>
                        <option value="poultry">🐔 Poultry</option>
                        <option value="other">📦 Other</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Price Range (KES)</label>
                    <div class="price-range">
                        <input type="number" id="minPrice" placeholder="Min" step="10">
                        <span>-</span>
                        <input type="number" id="maxPrice" placeholder="Max" step="10">
                    </div>
                </div>
                
                <div class="filter-group">
                    <label for="verificationSelect">Seller Verification</label>
                    <select id="verificationSelect">
                        <option value="">All Sellers</option>
                        <option value="basic">Basic Verified</option>
                        <option value="trusted">Trusted Seller</option>
                        <option value="premium">Premium Verified</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sortSelect">Sort By</label>
                    <select id="sortSelect">
                        <option value="newest">Newest First</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="trust_desc">Highest Trust Score</option>
                    </select>
                </div>
                
                <button id="resetFiltersBtn" class="btn btn-outline btn-sm">Reset Filters</button>
            </aside>
            
            <!-- Products Grid -->
            <main class="products-main">
                <div class="products-header">
                    <h2 id="resultsTitle">All Products</h2>
                    <div id="resultsCount"></div>
                </div>
                
                <div id="productsGrid" class="products-grid">
                    <div class="loading">Loading products...</div>
                </div>
                
                <div id="pagination" class="pagination"></div>
            </main>
        </div>
    </div>

<script src="/assets/js/api.js"></script>
<script src="/assets/js/app.js"></script>
    <script>
        // ============================================================
        // PASS USER DATA FROM PHP
        // ============================================================
        const currentUser = {
            id: <?php echo json_encode($userId); ?>,
            name: <?php echo json_encode($userName); ?>,
            role: <?php echo json_encode($userRole); ?>,
            phone: <?php echo json_encode($userPhone); ?>
        };
        console.log('Current user (from PHP):', currentUser);
        
        // ============================================================
        // DETECT "MY PRODUCTS" FILTER (for farmers)
        // ============================================================
        const urlParams = new URLSearchParams(window.location.search);
        const myProducts = urlParams.get('my_products') === '1';
        
        // ============================================================
        // UPDATE UI BASED ON LOGIN STATUS
        // ============================================================
        function updateUI() {
            const authLinks = document.getElementById('authLinks');
            const userMenu = document.getElementById('userMenu');
            const addProductLink = document.getElementById('addProductLink');
            const userNameSpan = document.getElementById('userName');
            
            if (currentUser && currentUser.id) {
                // Logged in
                if (authLinks) authLinks.style.display = 'none';
                if (userMenu) userMenu.style.display = 'block';
                if (userNameSpan) userNameSpan.textContent = currentUser.name;
                if (currentUser.role === 'farmer' && addProductLink) {
                    addProductLink.style.display = 'block';
                }
            } else {
                // Not logged in
                if (authLinks) authLinks.style.display = 'flex';
                if (userMenu) userMenu.style.display = 'none';
            }
        }
        updateUI();
        
        // ============================================================
        // LOGOUT
        // ============================================================
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
        
        // ============================================================
        // STAR RENDER HELPER
        // ============================================================
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
        
        // ============================================================
        // PAGE STATE
        // ============================================================
        let currentPage = 1;
        let totalPages = 1;
        let isLoading = false;
        
        const filters = {
            search: '',
            category: '',
            min_price: null,
            max_price: null,
            verification_tier: '',
            sort: 'newest'
        };
        
        // ============================================================
        // LOAD PRODUCTS
        // ============================================================
        async function loadProducts() {
            if (isLoading) return;
            isLoading = true;
            
            const params = {
                page: currentPage,
                per_page: 12,
                ...filters
            };
            
            // Remove empty values
            Object.keys(params).forEach(key => {
                if (!params[key] && params[key] !== 0) delete params[key];
            });
            
            // If viewing "My Products" and user is a farmer, filter by farmer_id
            if (myProducts && currentUser && currentUser.role === 'farmer') {
                params.farmer_id = currentUser.id;
            }
            
            try {
                const response = await API.get('/products/get_products.php', params);
                
                if (response.success) {
                    renderProducts(response.data);
                    updatePagination(response.meta?.pagination);
                    document.getElementById('resultsCount').textContent = 
                        `${response.meta?.pagination?.total || 0} products found`;
                }
            } catch (error) {
                console.error('Failed to load products:', error);
                document.getElementById('productsGrid').innerHTML = 
                    '<div class="error">Failed to load products. Please try again.</div>';
            } finally {
                isLoading = false;
            }
        }
        
        // ============================================================
        // RENDER PRODUCT CARDS
        // ============================================================
        function renderProducts(products) {
            const container = document.getElementById('productsGrid');
            
            if (!products || products.length === 0) {
                container.innerHTML = '<div class="empty-state">No products found. Try adjusting your filters.</div>';
                return;
            }
            
            const placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 24 24' fill='%23999'%3E%3Crect width='24' height='24' fill='%23ccc'/%3E%3C/svg%3E";
            
            container.innerHTML = products.map(product => `
                <div class="product-card" onclick="location.href='product-detail.php?id=${product.id}'">
                    <img src="${product.primary_image || placeholder}" 
                         alt="${escapeHtml(product.name)}" class="product-image">
                    <div class="product-info">
                        <h3>${escapeHtml(product.name)}</h3>
                        <div class="product-price">KES ${product.price.toLocaleString()} / ${product.unit_abbr}</div>
                        <div class="product-farmer">
                            <span class="trust-badge">${product.farmer.trust_badge.icon}</span>
                            ${escapeHtml(product.farmer.name)}
                        </div>
                        <div class="product-stock ${product.quantity < 10 ? 'low-stock' : ''}">
                            ${product.quantity > 0 ? `${product.quantity} ${product.unit_abbr} available` : 'Out of stock'}
                        </div>
                        <div class="product-rating">
                            ${product.avg_rating ? `
                                <span class="stars">${renderStars(product.avg_rating)}</span>
                                <span class="rating-value">${product.avg_rating.toFixed(1)}</span>
                                <span class="review-count">(${product.review_count})</span>
                            ` : '<span class="no-reviews">No reviews yet</span>'}
                        </div>
                    </div>
                    <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(${product.id})">
                        🛒 Add to Cart
                    </button>
                </div>
            `).join('');
        }
        
        // ============================================================
        // PAGINATION
        // ============================================================
        function updatePagination(pagination) {
            if (!pagination) return;
            
            totalPages = pagination.total_pages;
            const container = document.getElementById('pagination');
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            let html = '<div class="pagination-controls">';
            
            if (pagination.has_previous) {
                html += `<button onclick="goToPage(${pagination.current_page - 1})" class="page-btn">← Previous</button>`;
            }
            
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(totalPages, pagination.current_page + 2);
            
            if (startPage > 1) {
                html += `<button onclick="goToPage(1)" class="page-btn">1</button>`;
                if (startPage > 2) html += `<span class="page-dots">...</span>`;
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button onclick="goToPage(${i})" class="page-btn ${i === pagination.current_page ? 'active' : ''}">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span class="page-dots">...</span>`;
                html += `<button onclick="goToPage(${totalPages})" class="page-btn">${totalPages}</button>`;
            }
            
            if (pagination.has_next) {
                html += `<button onclick="goToPage(${pagination.current_page + 1})" class="page-btn">Next →</button>`;
            }
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function goToPage(page) {
            if (page === currentPage || page < 1 || page > totalPages) return;
            currentPage = page;
            loadProducts();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // ============================================================
        // ADD TO CART
        // ============================================================
        async function addToCart(productId) {
            if (!App.isLoggedIn) {
                if (confirm('Please login to add items to cart. Go to login page?')) {
                    window.location.href = 'login.php';
                }
                return;
            }
            
            try {
                const response = await API.post('/cart/add_to_cart.php', { product_id: productId, quantity: 1 });
                if (response.success) {
                    showToast('Product added to cart!', 'success');
                    updateCartCount(); // update the badge without page refresh
                }
            } catch (error) {
                showToast(error.message || 'Failed to add to cart', 'error');
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
                badge.style.display = count > 0 ? 'inline-block' : 'none'; // optional
            }
        }
    } catch (error) {
        console.error('Failed to update cart count:', error);
    }
}

// Call on page load
document.addEventListener('DOMContentLoaded', () => {
    updateCartCount();
    // ... other initializations
});

// Also update after adding to cart (in the addToCart function)
// Find the addToCart function and after the success response, call updateCartCount()
        
        // ============================================================
        // FILTERS
        // ============================================================
        function setupFilters() {
            const searchInput = document.getElementById('searchInput');
            const categorySelect = document.getElementById('categorySelect');
            const minPrice = document.getElementById('minPrice');
            const maxPrice = document.getElementById('maxPrice');
            const verificationSelect = document.getElementById('verificationSelect');
            const sortSelect = document.getElementById('sortSelect');
            const resetBtn = document.getElementById('resetFiltersBtn');
            
            const applyFilters = () => {
                filters.search = searchInput.value.trim();
                filters.category = categorySelect.value;
                filters.min_price = minPrice.value ? parseFloat(minPrice.value) : null;
                filters.max_price = maxPrice.value ? parseFloat(maxPrice.value) : null;
                filters.verification_tier = verificationSelect.value;
                filters.sort = sortSelect.value;
                currentPage = 1;
                loadProducts();
            };
            
            searchInput.addEventListener('input', debounce(applyFilters, 500));
            categorySelect.addEventListener('change', applyFilters);
            minPrice.addEventListener('change', applyFilters);
            maxPrice.addEventListener('change', applyFilters);
            verificationSelect.addEventListener('change', applyFilters);
            sortSelect.addEventListener('change', applyFilters);
            
            resetBtn.addEventListener('click', () => {
                searchInput.value = '';
                categorySelect.value = '';
                minPrice.value = '';
                maxPrice.value = '';
                verificationSelect.value = '';
                sortSelect.value = 'newest';
                applyFilters();
            });
        }
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // ============================================================
        // DROPDOWN TOGGLE
        // ============================================================
        const dropdownBtn = document.getElementById('userMenuBtn');
        const dropdownContent = document.querySelector('.dropdown-content');
        
        if (dropdownBtn && dropdownContent) {
            dropdownBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownContent.classList.toggle('show');
            });
            document.addEventListener('click', () => {
                dropdownContent.classList.remove('show');
            });
        }
        
        // ============================================================
        // MOBILE FILTER TOGGLE
        // ============================================================
        const filterToggleBtn = document.getElementById('filterToggleBtn');
        const filtersSidebar = document.querySelector('.filters-sidebar');
        
        if (filterToggleBtn && filtersSidebar) {
            filterToggleBtn.addEventListener('click', () => {
                filtersSidebar.classList.toggle('show');
            });
        }
        
        const closeFiltersBtn = document.getElementById('closeFiltersBtn');
        if (closeFiltersBtn && filtersSidebar) {
            closeFiltersBtn.addEventListener('click', () => {
                filtersSidebar.classList.remove('show');
            });
        }
        
        // ============================================================
        // INITIALIZE
        // ============================================================
        document.addEventListener('DOMContentLoaded', () => {
            setupFilters();
            loadProducts();
        });
    </script>
</body>
</html>