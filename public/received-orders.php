<?php
// Start session and check authentication
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user info from session
$userId = $_SESSION['user_id'] ?? $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? $_SESSION['user_name'] ?? 'Farmer';
$userRole = $_SESSION['user_role'] ?? $_SESSION['user_role'] ?? '';

// Check if user is a farmer (only farmers can view received orders)
if ($userRole !== 'farmer') {
    // If buyer tries to access, redirect to my-orders.php
    if ($userRole === 'buyer') {
        header('Location: my-orders.php');
        exit;
    }
    // For other roles, redirect to dashboard
    header('Location: dashboard.php');
    exit;
}

$userPhone = $_SESSION['user_phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Received Orders - TrustLink</title>
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
 
                <a href="cart.php" class="cart-icon-link" id="cartIconLink">
                    <span class="cart-icon">🛒</span>
                    <span id="cartCount" class="cart-badge">0</span>
                </a>
                <a href="products.php" class="nav-link">Browse Products</a>
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="add-product.php" class="nav-link">Add Product</a>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($userName); ?></span>
                    <a href="#" id="logoutBtn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Received Orders</h1>
        
        <div class="order-filters">
            <button class="filter-btn active" data-status="all">All Orders</button>
            <button class="filter-btn" data-status="pending">Pending</button>
            <button class="filter-btn" data-status="accepted">Accepted</button>
            <button class="filter-btn" data-status="completed">Completed</button>
        </div>
        
        <div id="ordersContent" class="orders-container">
            <div class="loading">Loading orders...</div>
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
        
        let currentStatus = 'all';
        

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



        async function loadOrders() {
            // Double-check authentication (though PHP already does)
            if (!currentUser.id) {
                window.location.href = 'login.php';
                return;
            }
            
            // Verify farmer role
            if (currentUser.role !== 'farmer') {
                showToast('Only farmers can access received orders', 'error');
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1500);
                return;
            }
            
            const params = { role: 'farmer', per_page: 20 };
            if (currentStatus !== 'all') {
                params.status = currentStatus;
            }
            
            try {
                const response = await API.get('/orders/get_orders.php', params);
                if (response.success) {
                    renderOrders(response.data);
                } else {
                    document.getElementById('ordersContent').innerHTML = '<div class="error">Failed to load orders</div>';
                }
            } catch (error) {
                console.error('Failed to load orders:', error);
                document.getElementById('ordersContent').innerHTML = '<div class="error">Failed to load orders</div>';
            }
        }
        
        function renderOrders(orders) {
            const container = document.getElementById('ordersContent');
            
            if (!orders || orders.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <p>No orders received yet.</p>
                        <a href="products.php" class="btn btn-primary">Browse Products</a>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = orders.map(order => `
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <span class="order-code">${escapeHtml(order.order_code)}</span>
                            <span class="order-status status-${order.status}">${order.status_display}</span>
                        </div>
                        <span class="order-date">${formatDate(order.created_at)}</span>
                    </div>
                    
                    <div class="order-details">
                        <div class="order-summary">
                            <span><strong>Buyer:</strong> ${escapeHtml(order.buyer.name)}</span>
                            <span><strong>Phone:</strong> ${escapeHtml(order.buyer.phone)}</span>
                            <span><strong>Items:</strong> ${order.item_count}</span>
                            <span><strong>Total:</strong> KES ${order.total.toLocaleString()}</span>
                        </div>
                        <div class="delivery-info">
                            <span>📍 <strong>Delivery Location:</strong> ${escapeHtml(order.location || order.delivery_location || 'Not specified')}</span>
                        </div>
                        ${order.instructions ? `
                            <div class="delivery-info">
                                <span>📝 <strong>Instructions:</strong> ${escapeHtml(order.instructions)}</span>
                            </div>
                        ` : ''}
                        <div class="order-actions">
                            <a href="order-detail.php?id=${order.id}" class="btn btn-sm btn-outline">View Details</a>
                            ${order.status === 'pending' ? `
                                <button class="btn btn-sm btn-primary" onclick="updateOrderStatus(${order.id}, 'accepted')">✅ Accept Order</button>
                            ` : ''}
                            ${order.status === 'accepted' ? `
                                <button class="btn btn-sm btn-success" onclick="updateOrderStatus(${order.id}, 'completed')">✓ Mark Completed</button>
                            ` : ''}
                            ${order.status === 'completed' ? `
                                <span class="badge-success">✓ Order Completed</span>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        async function updateOrderStatus(orderId, status) {
            const confirmMessage = status === 'accepted' 
                ? 'Are you sure you want to accept this order?' 
                : 'Are you sure you want to mark this order as completed?';
            
            if (!confirm(confirmMessage)) return;
            
            try {
                const response = await API.put('/orders/update_order_status.php', { 
                    order_id: orderId, 
                    status: status 
                });
                
                if (response.success) {
                    const statusMessage = status === 'accepted' 
                        ? 'Order accepted successfully!' 
                        : 'Order marked as completed!';
                    showToast(statusMessage, 'success');
                    loadOrders(); // Reload the orders list
                } else {
                    showToast(response.message || 'Failed to update order', 'error');
                }
            } catch (error) {
                console.error('Update order error:', error);
                showToast(error.message || 'Failed to update order', 'error');
            }
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            return new Date(dateStr).toLocaleDateString('en-KE', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentStatus = btn.dataset.status;
                loadOrders();
            });
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', loadOrders); currentStatus = 'all'; updateCartCount();
    </script>
    <script src="../assets/js/api.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>