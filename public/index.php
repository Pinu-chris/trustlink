 
<?php
require_once dirname(__DIR__) . '/utils/auth_middleware.php';

$auth = auth();
$isLoggedIn = $auth->isAuthenticated();
$userData = $isLoggedIn ? $auth->getCurrentUser() : null;

$userName = $userData['name'] ?? 'Guest';
$userRole = $userData['role'] ?? null;
$userAvatar = $userData['avatar'] ?? '/assets/images/default/avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrustLink - Kenya's Verified Farm-to-Table Marketplace | Fresh Produce & Agricultural Services</title>
    <meta name="description" content="TrustLink connects Kenyan farmers directly with consumers. Buy fresh, verified vegetables, fruits, dairy, grains, and poultry. Safe, transparent, and fair prices.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Additional styles for enhanced features (does not override original CSS) */
        .hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            position: relative;
            overflow: hidden;
        }
        .live-activity {
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 0;
            font-size: 0.85rem;
            overflow: hidden;
            white-space: nowrap;
        }
        .ticker {
            display: inline-block;
            animation: ticker 30s linear infinite;
        }
        @keyframes ticker {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        .stats-section {
            background: var(--gray-100);
            padding: 60px 0;
        }
        .stats-grid {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 30px;
            text-align: center;
        }
        .stat-item h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        .product-carousel {
            position: relative;
            overflow: hidden;
        }
        .carousel-container {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 10px 0;
            scrollbar-width: thin;
        }
        .carousel-container::-webkit-scrollbar {
            height: 6px;
        }
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            box-shadow: var(--shadow-md);
            z-index: 10;
        }
        .carousel-btn.prev { left: -20px; }
        .carousel-btn.next { right: -20px; }
        @media (max-width: 768px) {
            .carousel-btn { display: none; }
        }
        .skeleton-card {
            background: #e0e0e0;
            border-radius: 12px;
            height: 280px;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 0.6; }
            100% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        .flash-sale {
            background: #ff9800;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }
        .category-card {
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .category-card:hover {
            transform: translateY(-5px);
        }
        .category-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .product-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            color: #f5b642;
        }
        .timer {
            font-weight: bold;
            background: #000;
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        /* New sections styling (added without breaking original) */
        .about-section, .mission-vision, .why-choose, .testimonials, .faq-section, .newsletter, .partners {
            padding: 60px 0;
            border-bottom: 1px solid var(--gray-200);
        }
        .mission-vision {
            background: var(--gray-50);
        }
        .two-column {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
        }
        .two-column > div {
            flex: 1;
            min-width: 250px;
        }
        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .testimonial-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }
        .testimonial-card p {
            font-style: italic;
        }
        .faq-item {
            margin-bottom: 15px;
            border-bottom: 1px solid var(--gray-200);
        }
        .faq-question {
            font-weight: 600;
            cursor: pointer;
            padding: 15px 0;
            display: flex;
            justify-content: space-between;
        }
        .faq-answer {
            display: none;
            padding-bottom: 15px;
            color: var(--gray-600);
        }
        .faq-question.active + .faq-answer {
            display: block;
        }
        .newsletter-form {
            display: flex;
            gap: 10px;
            max-width: 500px;
            margin-top: 20px;
        }
        .newsletter-form input {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
        }
        .partner-logos {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
        }
        .partner-logos span {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--gray-500);
        }
        .footer {
            background: #1a2a1f;
            color: white;
            padding: 40px 0 20px;
            margin-top: 40px;
        }
        .footer a {
            color: #ccc;
        }
        .footer a:hover {
            color: white;
        }
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }
        .footer-section h3, .footer-section h4 {
            margin-bottom: 15px;
            color: #ffd966;
        }
        .footer-section p, .footer-section a {
            display: block;
            margin-bottom: 8px;
        }
        .social-icons {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        .social-icons a {
            font-size: 1.5rem;
        }
        .footer-bottom {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #3a4a3a;
        }
        @media (max-width: 768px) {
            .two-column { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- Live Activity Ticker -->
<div class="live-activity">
    <div class="container ticker" id="liveTicker">
        <!-- Activities will be inserted via JavaScript -->
    </div>
</div>

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
            <a href="how-it-works.php" class="nav-link">How It Works</a>
            <?php if ($isLoggedIn): ?>
                <div class="dropdown">
                    <button class="dropdown-btn">
                        <img src="<?php echo $userAvatar; ?>" alt="Avatar" class="avatar-small">
                        <span><?php echo htmlspecialchars($userName); ?></span>
                        <span class="dropdown-icon">▼</span>
                    </button>
                    <div class="dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="profile.php">Profile</a>
                        <?php if ($userRole === 'buyer'): ?> <a href="my-orders.php">My Orders</a> <?php endif; ?>
                        <?php if ($userRole === 'farmer'): ?>
                            <a href="received-orders.php">Received Orders</a>
                            <a href="add-product.php">Add Product</a>
                        <?php endif; ?>
                        <?php if ($userRole === 'admin'): ?> <a href="admin.php">Admin Panel</a> <?php endif; ?>
                        <hr>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Login</a>
                <a href="register.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
        <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
    </div>
</nav>

<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Fresh from Kenyan Farms<br><span class="highlight">Directly to Your Table</span></h1>
            <p class="hero-subtitle">
                TrustLink is Kenya's first verified farm-to-table marketplace. Buy fresh vegetables, fruits, dairy, grains, and poultry directly from trusted farmers. No middlemen, fair prices, and full traceability.
            </p>
            <div class="hero-buttons">
                <a href="products.php" class="btn btn-primary btn-large">Shop Fresh Produce</a>
                <?php if (!$isLoggedIn): ?>
                    <a href="register.php?role=farmer" class="btn btn-outline btn-large">Become a Farmer Seller</a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-outline btn-large">My Farm Dashboard</a>
                <?php endif; ?>
            </div>
            <div class="trust-badges">
                <span>✓ Verified Farmers</span>
                <span>✓ Trust Score System</span>
                <span>✓ Farm-Fresh Guarantee</span>
                <span>✓ Fair Trade Practices</span>
                <span>✓ Sustainable Agriculture</span>
            </div>
        </div>
    </div>
</section>

<!-- Animated Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <h3 id="farmersCount">0</h3>
                <p>Active Farmers</p>
            </div>
            <div class="stat-item">
                <h3 id="ordersCount">0</h3>
                <p>Orders Delivered</p>
            </div>
            <div class="stat-item">
                <h3 id="productsCount">0</h3>
                <p>Fresh Products</p>
            </div>
            <div class="stat-item">
                <h3 id="buyersCount">0</h3>
                <p>Happy Buyers</p>
            </div>
        </div>
    </div>
</section>

<!-- About Us Section -->
<section class="about-section">
    <div class="container">
        <h2 class="section-title">About TrustLink</h2>
        <div class="two-column">
            <div>
                <p>TrustLink was founded in 2026 by <strong>Chrispinus</strong> (📞 0793472206) with a mission to revolutionize Kenya's agricultural supply chain. We empower smallholder farmers by giving them direct access to consumers, eliminating exploitative middlemen.</p>
                <p>Our platform ensures that every product is traceable from farm to fork. Farmers undergo a rigorous verification process including farm inspection, ID verification, and trust scoring based on customer reviews and delivery reliability.</p>
                <p>We specialize in <strong>fresh vegetables, organic fruits, dairy products, grains, poultry, and agricultural services</strong> like tractor hiring and pest control.</p>
            </div>
            <div>
                <img src="../assets/images/about-farming.jpg" alt="Kenyan farmer" style="width:100%; border-radius:12px;" onerror="this.src='https://via.placeholder.com/400x250?text=Farm+to+Table'">
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision -->
<section class="mission-vision">
    <div class="container">
        <div class="two-column">
            <div>
                <h3>🌱 Our Mission</h3>
                <p>To create a transparent, efficient, and fair digital marketplace that connects Kenyan farmers directly with consumers, ensuring food security, fair prices, and sustainable agricultural practices.</p>
            </div>
            <div>
                <h3>🌟 Our Vision</h3>
                <p>To become the most trusted agricultural ecosystem in Africa, where every farmer thrives and every family enjoys fresh, affordable, and safe food.</p>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="why-choose">
    <div class="container">
        <h2 class="section-title">Why Choose TrustLink?</h2>
        <div class="categories-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr));">
            <div class="category-card"><div class="category-icon">✅</div><p>100% Verified Farmers</p><small>Background checks & farm visits</small></div>
            <div class="category-card"><div class="category-icon">💰</div><p>No Middlemen</p><small>You pay farmers directly, up to 30% cheaper</small></div>
            <div class="category-card"><div class="category-icon">📦</div><p>Farm-Fresh Delivery</p><small>Picked within 24 hours of order</small></div>
            <div class="category-card"><div class="category-icon">🔒</div><p>Trust Score System</p><small>Ratings & reviews from real buyers</small></div>
            <div class="category-card"><div class="category-icon">🌍</div><p>Support Local Farmers</p><small>Empower small-scale agriculture</small></div>
            <div class="category-card"><div class="category-icon">🚜</div><p>Agricultural Services</p><small>Hire tractors, irrigation experts, pest control</small></div>
        </div>
    </div>
</section>

<!-- Categories Section (original) -->
<section class="categories">
    <div class="container">
        <h2 class="section-title">Shop by Category</h2>
        <div class="categories-grid" id="categoriesGrid">
            <div class="category-card" data-category="vegetables"><div class="category-icon">🥬</div><p>Vegetables</p></div>
            <div class="category-card" data-category="fruits"><div class="category-icon">🍎</div><p>Fruits</p></div>
            <div class="category-card" data-category="dairy"><div class="category-icon">🥛</div><p>Dairy</p></div>
            <div class="category-card" data-category="grains"><div class="category-icon">🌾</div><p>Grains</p></div>
            <div class="category-card" data-category="poultry"><div class="category-icon">🐔</div><p>Poultry</p></div>
            <div class="category-card" data-category="other"><div class="category-icon">📦</div><p>Other</p></div>
        </div>
    </div>
</section>

<!-- Flash Sale Section -->
<section class="flash-sale-section">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="section-title">🔥 Flash Sale – Fresh Harvest Deals</h2>
            <div class="timer" id="flashTimer">Ends in 00:00:00</div>
        </div>
        <div id="flashProducts" class="products-grid">
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
        </div>
    </div>
</section>

<!-- Featured Products Carousel -->
<section class="featured-products">
    <div class="container">
        <h2 class="section-title">Fresh from Our Farmers</h2>
        <div class="product-carousel">
            <button class="carousel-btn prev" id="carouselPrev">‹</button>
            <div class="carousel-container" id="featuredCarousel">
                <!-- Skeleton items will be loaded here -->
            </div>
            <button class="carousel-btn next" id="carouselNext">›</button>
        </div>
        <div class="text-center mt-4"><a href="products.php" class="btn btn-outline">View All Products</a></div>
    </div>
</section>

<!-- Personalized Recommendations -->
<section class="recommendations">
    <div class="container">
        <h2 class="section-title"><?php echo $isLoggedIn ? 'Recommended for You' : 'Popular Products'; ?></h2>
        <div id="recommendedProducts" class="products-grid">
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials">
    <div class="container">
        <h2 class="section-title">What Our Farmers & Buyers Say</h2>
        <div class="testimonial-grid">
            <div class="testimonial-card">
                <p>"TrustLink has transformed my small farm. I now sell my sukuma wiki directly to Nairobi families at better prices. The verification process built customer trust."</p>
                <strong>- Mary W., Farmer, Kiambu</strong>
                <div>⭐⭐⭐⭐⭐</div>
            </div>
            <div class="testimonial-card">
                <p>"As a buyer, I love knowing exactly where my food comes from. The tomatoes and dairy are always fresh. Plus, I'm supporting local agriculture."</p>
                <strong>- James K., Buyer, Nairobi</strong>
                <div>⭐⭐⭐⭐⭐</div>
            </div>
            <div class="testimonial-card">
                <p>"The trust score system is brilliant. I feel safe ordering from farmers with high ratings. Delivery is always on time."</p>
                <strong>- Lucy M., Buyer, Mombasa</strong>
                <div>⭐⭐⭐⭐⭐</div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section (Accordion) -->
<section class="faq-section">
    <div class="container">
        <h2 class="section-title">Frequently Asked Questions</h2>
        <div class="faq-list">
            <div class="faq-item">
                <div class="faq-question">How do you verify farmers? <span>+</span></div>
                <div class="faq-answer">We conduct physical farm inspections, verify national ID, and check land ownership documents. Farmers also undergo a 14-day probation period before their products are listed.</div>
            </div>
            <div class="faq-item">
                <div class="faq-question">What payment methods are accepted? <span>+</span></div>
                <div class="faq-answer">We accept M-Pesa, bank transfer, and card payments. All transactions are escrow-protected: funds are released to the farmer only after you confirm delivery.</div>
            </div>
            <div class="faq-item">
                <div class="faq-question">Do you deliver across Kenya? <span>+</span></div>
                <div class="faq-answer">Yes! We partner with logistics companies to deliver fresh produce to all counties. Delivery fees vary by location, but we offer free delivery for orders over KES 3,000 in Nairobi.</div>
            </div>
            <div class="faq-item">
                <div class="faq-question">How can I become a farmer seller? <span>+</span></div>
                <div class="faq-answer">Click "Sell on TrustLink" on the homepage, fill out the registration form, and our team will contact you within 48 hours to schedule a farm verification visit.</div>
            </div>
            <div class="faq-item">
                <div class="faq-question">What if I receive spoiled produce? <span>+</span></div>
                <div class="faq-answer">We have a "Freshness Guarantee". If you report within 2 hours of delivery, we'll issue a full refund or replacement. Farmers with repeated complaints lose their trust score.</div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter & Partners -->
<section class="newsletter">
    <div class="container">
        <h2 class="section-title">Join Our Farm-to-Table Community</h2>
        <p>Subscribe to get fresh harvest alerts, farming tips, and exclusive discounts.</p>
        <form class="newsletter-form" id="newsletterForm">
            <input type="email" placeholder="Your email address" required>
            <button type="submit" class="btn btn-primary">Subscribe</button>
        </form>
    </div>
</section>

<section class="partners">
    <div class="container">
        <h2 class="section-title">Trusted By</h2>
        <div class="partner-logos">
            <span>🌾 Kenya Farmers Association</span>
            <span>🥑 Organic Farmers Co-op</span>
            <span>🚜 AGRA Kenya</span>
            <span>📦 Sendy Logistics</span>
            <span>💰 M-Pesa</span>
        </div>
    </div>
</section>

<!-- IMPROVED FOOTER with Address, Contact, About, FAQ, Mission, Vision -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>🌱 TrustLink</h3>
                <p>Kenya's #1 verified marketplace for fresh farm produce and agricultural services. Connecting farmers directly to consumers.</p>
                <div class="social-icons">
                    <a href="#">📘</a>
                    <a href="#">🐦</a>
                    <a href="#">📸</a>
                    <a href="#">📧</a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <a href="products.php">Browse Products</a>
                <a href="how-it-works.php">How It Works</a>
                <a href="#about">About Us</a>
                <a href="#faq">FAQ</a>
                <a href="contact.php">Contact</a>
            </div>
            <div class="footer-section">
                <h4>For Farmers</h4>
                <a href="register.php?role=farmer">Start Selling</a>
                <a href="farmer-guide.php">Farmer's Guide</a>
                <a href="trust-score.php">Understanding Trust Score</a>
                <a href="verification.php">Get Verified</a>
            </div>
            <div class="footer-section">
                <h4>Our Mission & Vision</h4>
                <p><strong>Mission:</strong> Empower farmers, ensure food safety, fair trade.</p>
                <p><strong>Vision:</strong> A hunger-free Kenya with thriving agricultural communities.</p>
            </div>
            <div class="footer-section">
                <h4>Contact Us</h4>
                <p>📍 <strong>Address:</strong> Westlands, Nairobi, Kenya</p>
                <p>📞 <strong>Phone:</strong> <a href="tel:+254793472206">0793472206</a> (Chrispinus, Founder)</p>
                <p>✉️ <strong>Email:</strong> <a href="mailto:chriswalles288@gmail.com">chriswalles288@gmail.com</a></p>
                <p>🕒 Mon-Fri: 8am - 6pm | Sat: 9am - 2pm</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 TrustLink. All rights reserved. | Designed by Chrispinus | #FarmToTableKE</p>
        </div>
    </div>
</footer>

<script src="/assets/js/api.js"></script>
<script>
    // Helper: format price
    function formatPrice(price) {
        return new Intl.NumberFormat().format(price);
    }

    // Helper: render stars (simplified)
    function renderStars(rating) {
        const full = Math.floor(rating);
        let stars = '';
        for (let i = 0; i < full; i++) stars += '⭐';
        for (let i = full; i < 5; i++) stars += '☆';
        return stars;
    }

    // Load featured products (carousel)
    async function loadFeaturedProducts() {
        const container = document.getElementById('featuredCarousel');
        container.innerHTML = '<div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div>';
        try {
            const response = await API.get('/products/get_products.php?per_page=8&sort=newest');
            if (response.success && response.data) {
                container.innerHTML = response.data.map(product => `
                    <div class="product-card" style="min-width: 250px;" onclick="location.href='product-detail.php?id=${product.id}'">
                        <img src="${product.primary_image || '../assets/images/default/product-placeholder.jpg'}" alt="${product.name}" class="product-image">
                        <div class="product-info">
                            <h3>${product.name}</h3>
                            <div class="product-price">KES ${formatPrice(product.price)} / ${product.unit_abbr}</div>
                            <div class="product-rating">${renderStars(product.avg_rating || 0)} ${product.avg_rating ? product.avg_rating.toFixed(1) : 'No ratings'}</div>
                        </div>
                    </div>
                `).join('');
            }
        } catch (e) { console.error(e); }
    }

    // Load flash sale products (with discount logic)
    async function loadFlashProducts() {
        const container = document.getElementById('flashProducts');
        container.innerHTML = '<div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div>';
        try {
            const response = await API.get('/products/get_products.php?per_page=4&sort=newest');
            if (response.success && response.data) {
                container.innerHTML = response.data.slice(0,4).map(product => {
                    const originalPrice = product.price;
                    const discountedPrice = originalPrice * 0.7;
                    return `
                        <div class="product-card" onclick="location.href='product-detail.php?id=${product.id}'">
                            <span class="flash-sale">-30%</span>
                            <img src="${product.primary_image || '../assets/images/default/product-placeholder.jpg'}" class="product-image">
                            <div class="product-info">
                                <h3>${product.name}</h3>
                                <div class="product-price">
                                    <span style="text-decoration: line-through; color: gray;">KES ${formatPrice(originalPrice)}</span>
                                    <span style="color: red;"> KES ${formatPrice(discountedPrice)}</span>
                                </div>
                                <div class="product-rating">${renderStars(product.avg_rating || 0)}</div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        } catch (e) { console.error(e); }
    }

    // Load personalized recommendations
    async function loadRecommendations() {
        const container = document.getElementById('recommendedProducts');
        container.innerHTML = '<div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div>';
        try {
            const response = await API.get('/products/get_products.php?per_page=4&sort=trust_desc');
            if (response.success && response.data) {
                container.innerHTML = response.data.map(product => `
                    <div class="product-card" onclick="location.href='product-detail.php?id=${product.id}'">
                        <img src="${product.primary_image || '../assets/images/default/product-placeholder.jpg'}" class="product-image">
                        <div class="product-info">
                            <h3>${product.name}</h3>
                            <div class="product-price">KES ${formatPrice(product.price)} / ${product.unit_abbr}</div>
                            <div class="product-rating">${renderStars(product.avg_rating || 0)} ${product.avg_rating ? product.avg_rating.toFixed(1) : 'New'}</div>
                        </div>
                    </div>
                `).join('');
            }
        } catch (e) { console.error(e); }
    }

    // Animated counters (with Intersection Observer)
    function animateCounter(element, target) {
        let current = 0;
        const step = Math.ceil(target / 50);
        const interval = setInterval(() => {
            current += step;
            if (current >= target) {
                element.innerText = target.toLocaleString();
                clearInterval(interval);
            } else {
                element.innerText = current.toLocaleString();
            }
        }, 30);
    }

    function startCounters() {
        const farmersEl = document.getElementById('farmersCount');
        const ordersEl = document.getElementById('ordersCount');
        const productsEl = document.getElementById('productsCount');
        const buyersEl = document.getElementById('buyersCount');
        animateCounter(farmersEl, 1250);
        animateCounter(ordersEl, 5400);
        animateCounter(productsEl, 3420);
        animateCounter(buyersEl, 2890);
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                startCounters();
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    observer.observe(document.querySelector('.stats-section'));

    // Carousel scrolling
    const carousel = document.getElementById('featuredCarousel');
    const prevBtn = document.getElementById('carouselPrev');
    const nextBtn = document.getElementById('carouselNext');
    if (carousel && prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => {
            carousel.scrollBy({ left: -300, behavior: 'smooth' });
        });
        nextBtn.addEventListener('click', () => {
            carousel.scrollBy({ left: 300, behavior: 'smooth' });
        });
    }

    // Flash sale countdown timer (ends at midnight)
    function updateFlashTimer() {
        const now = new Date();
        const end = new Date();
        end.setHours(23, 59, 59, 999);
        const diff = end - now;
        if (diff <= 0) {
            document.getElementById('flashTimer').innerText = 'Sale ended';
            return;
        }
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        document.getElementById('flashTimer').innerText = `Ends in ${hours.toString().padStart(2,'0')}:${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
        setTimeout(updateFlashTimer, 1000);
    }
    updateFlashTimer();

    // Live Activity Ticker (random activities)
    const activities = [
        "🌾 John from Nairobi just bought Sukuma Wiki",
        "🥭 Mary from Mombasa ordered Fresh Mangoes",
        "🐄 Peter from Kisumu received his order of Dairy",
        "⭐ Alice from Eldoret rated Farmer Peter 5 stars",
        "🌱 New farmer registered from Kiambu",
        "🍅 Tomatoes from Naivasha now in stock",
        "🚜 Tractor hiring service launched in Nakuru"
    ];
    let tickerContent = "";
    function updateTicker() {
        tickerContent = activities.map(a => `&nbsp;&nbsp;• ${a} &nbsp;&nbsp;`).join('');
        document.getElementById('liveTicker').innerHTML = tickerContent;
        setTimeout(() => {
            const first = activities.shift();
            activities.push(first);
            updateTicker();
        }, 8000);
    }
    updateTicker();

    // Category click redirect
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', () => {
            const category = card.getAttribute('data-category');
            window.location.href = `products.php?category=${category}`;
        });
    });

    // FAQ Accordion
    document.querySelectorAll('.faq-question').forEach(question => {
        question.addEventListener('click', () => {
            question.classList.toggle('active');
            const answer = question.nextElementSibling;
            if (answer.style.display === 'block') {
                answer.style.display = 'none';
            } else {
                answer.style.display = 'block';
            }
        });
    });

    // Newsletter subscription (demo)
    document.getElementById('newsletterForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        alert('Thank you for subscribing! You will receive our farm fresh updates.');
        e.target.reset();
    });

    // Load all sections
    loadFeaturedProducts();
    loadFlashProducts();
    loadRecommendations();

    // Mobile menu toggle (basic)
    document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
        document.querySelector('.nav-links').classList.toggle('active');
    });
</script>
</body>
</html>
```