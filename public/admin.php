<?php
// 1. Load the Shield (Correct path to your middleware)
require_once dirname(__DIR__, 1) . '/utils/auth_middleware.php';

/** * 2. ACTIVATE THE SECURITY
 * require_admin() does 3 things automatically:
 * - Checks if the user is logged in (Redirects to login if not)
 * - Checks if the user is an 'admin' (Redirects/Errors if not)
 * - Checks if the user's account is still ACTIVE in the database
 */
$adminData = require_admin(); // ✅ This looks in the Global space where we put it

// 3. Set your variables from the $adminData returned by the middleware
$userId        = $adminData['id'];
$userName      = $adminData['name'] ?? 'Admin';
$userRole      = $adminData['role'] ?? 'admin';
$userPhone     = $adminData['phone'] ?? '';
$userEmail     = $adminData['email'] ?? '';
$userAdminType = $adminData['admin_type'] ?? null;

// 4. (Optional) Disable error display for production
ini_set('display_errors', 0);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - TrustLink</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/">
                    <span class="logo-icon">🌾</span>
                    <span class="logo-text">TrustLink Admin</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="admin.php" class="nav-link">Dashboard</a>
                <a href="products.php" class="nav-link">Browse Products</a>
                <a href="cart.php" class="cart-icon-link" id="cartIconLink">
                    <span class="cart-icon">🛒</span>
                    <span id="cartCount" class="cart-badge">0</span>
                </a>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($userName); ?></span>
                    <span class="admin-badge">Admin</span>
                    <a href="#" id="logoutBtn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Admin Panel</h1>
        <div class="admin-tabs">
            <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
            <button class="tab-btn" data-tab="users">Users</button>
            <button class="tab-btn" data-tab="adminManagement">Admin Management</button>
            <button class="tab-btn" data-tab="orders">Orders</button>
            <button class="tab-btn" data-tab="products">Products</button>
            <button class="tab-btn" data-tab="reports">Reports</button>
        </div>

        <div id="dashboardTab" class="tab-content active"><div class="loading">Loading dashboard...</div></div>
        <div id="usersTab" class="tab-content"><div class="loading">Loading users...</div></div>
        <div id="adminManagementTab" class="tab-content"><div class="loading">Loading admin list...</div></div>
        <div id="ordersTab" class="tab-content"><div class="loading">Loading orders...</div></div>
        <div id="productsTab" class="tab-content"><div class="loading">Loading products...</div></div>
        <div id="reportsTab" class="tab-content"><div class="loading">Loading reports...</div></div>
    </div>

    <!-- User Detail Modal -->
    <div id="userModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>User Details</h3>
            <div id="userModalContent">Loading...</div>
        </div>
    </div>

    <!-- Create Admin Modal -->
    <div id="createAdminModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Create New Admin</h3>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="adminName" placeholder="e.g., Jane Doe">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" id="adminPhone" placeholder="0712345678">
            </div>
            <div class="form-group">
                <label>Password (min 6 characters)</label>
                <input type="password" id="adminPassword" placeholder="******">
            </div>
            <div class="form-group">
                <button class="btn btn-primary" onclick="createAdmin()">Create Admin</button>
                <button class="btn btn-outline" onclick="closeCreateAdminModal()">Cancel</button>
            </div>
            <div id="createAdminMessage" style="margin-top:10px;"></div>
        </div>
    </div>

    <script src="../assets/js/api.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        // ============================================================
        // User data & helpers
        // ============================================================
        const currentUser = {
            id: <?php echo json_encode($userId); ?>,
            name: <?php echo json_encode($userName); ?>,
            role: <?php echo json_encode($userRole); ?>,
            phone: <?php echo json_encode($userPhone); ?>,
            email: <?php echo json_encode($userEmail); ?>,
            admin_type: <?php echo json_encode($userAdminType); ?>
        };
        console.log('Admin user:', currentUser);

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        if (typeof showToast !== 'function') {
            window.showToast = function(msg, type) { alert(msg); };
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            return new Date(dateStr).toLocaleDateString('en-KE', {
                year: 'numeric', month: 'short', day: 'numeric'
            });
        }

        // ============================================================
        // Cart count
        // ============================================================
        async function updateCartCount() {
            try {
                const resp = await API.get('/cart/get_cart_count.php');
                if (resp.success && resp.data) {
                    const badge = document.getElementById('cartCount');
                    if (badge) {
                        badge.textContent = resp.data.count;
                        badge.style.display = resp.data.count > 0 ? 'inline-block' : 'none';
                    }
                }
            } catch(e) { console.error(e); }
        }

        // ============================================================
        // Tab switching
        // ============================================================
        let currentTab = 'dashboard';
        async function loadTab(tab) {
            switch(tab) {
                case 'dashboard': await loadDashboard(); break;
                case 'users': await loadUsers(); break;
                case 'adminManagement': await loadAdminManagement(); break;
                case 'orders': await loadAllOrders(); break;
                case 'products': await loadAllProducts(); break;
                case 'reports': await loadReports(); break;
            }
        }


                        function renderCharts(dailyRevenue, dailyNewUsers, dailyNewProducts) {
                    const labels = dailyRevenue.map(item => item.date);
                    
                    // Revenue Chart
                    new Chart(document.getElementById('revenueChart'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Daily Revenue (KES)',
                                data: dailyRevenue.map(item => item.total),
                                borderColor: '#2ecc71',
                                tension: 0.1,
                                fill: true,
                                backgroundColor: 'rgba(46, 204, 113, 0.1)'
                            }]
                        },
                        options: { responsive: true, plugins: { title: { display: true, text: 'Revenue Trend' } } }
                    });

                    // Users & Products Growth Chart
                    new Chart(document.getElementById('usersChart'), {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'New Users',
                                    data: dailyNewUsers.map(item => item.count),
                                    backgroundColor: '#3498db'
                                },
                                {
                                    label: 'New Products',
                                    data: dailyNewProducts.map(item => item.count),
                                    backgroundColor: '#f1c40f'
                                }
                            ]
                        },
                        options: { responsive: true, scales: { y: { beginAtZero: true } } }
                    });
                }

        // ============================================================
        // Dashboard tab
        // ============================================================
        async function loadDashboard() {
            const container = document.getElementById('dashboardTab');
            container.innerHTML = '<div class="loading">Loading dashboard...</div>';
            try {
                const resp = await API.get('/admin/get_reports.php', { period: 'month' });
                if (resp.success && resp.data) {
                    const d = resp.data;
                    container.innerHTML = `
                        <div class="admin-stats-grid">
                            <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value">${d.users.total.toLocaleString()}</div><div class="stat-label">Total Users</div><div class="stat-trend">+${d.users.new_in_period} this month</div></div>
                            <div class="stat-card"><div class="stat-icon">🛒</div><div class="stat-value">${d.orders.total.toLocaleString()}</div><div class="stat-label">Total Orders</div><div class="stat-trend">${d.orders.orders_in_period} this month</div></div>
                            <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-value">KES ${d.orders.total_revenue.toLocaleString()}</div><div class="stat-label">Total Revenue</div><div class="stat-trend">KES ${d.orders.revenue_in_period.toLocaleString()} this month</div></div>
                            <div class="stat-card"><div class="stat-icon">📦</div><div class="stat-value">${d.products.active.toLocaleString()}</div><div class="stat-label">Active Products</div><div class="stat-trend">${d.products.total} total</div></div>
                        </div>
                        <div class="admin-section"><h3>Top Performing Farmers</h3><div class="data-table">\n<table><thead><tr><th>Farmer</th><th>Trust Score</th><th>Orders</th><th>Earnings</th></tr></thead><tbody>
                            ${d.top_farmers && d.top_farmers.length ? d.top_farmers.map(f => `<tr><td>${escapeHtml(f.name)}</td><td>${f.trust_score.toFixed(1)} ★</td><td>${f.total_orders}</td><td>KES ${f.total_earnings.toLocaleString()}</td></tr>`).join('') : '<tr><td colspan="4">No data</td></tr>'}
                        </tbody></table></div></div>
                    `;
                } else container.innerHTML = '<div class="error">Failed to load dashboard</div>';
            } catch(e) { container.innerHTML = '<div class="error">Failed to load dashboard</div>'; }
        }

        // ============================================================
        // Users tab
        // ============================================================
        async function loadUsers() {
            const container = document.getElementById('usersTab');
            container.innerHTML = '<div class="loading">Loading users...</div>';
            try {
                const resp = await API.get('/admin/get_users.php', { per_page: 100 });
                if (resp.success && resp.data) {
                    container.innerHTML = `
                        <div class="admin-section"><div class="section-header"><h3>All Users</h3><div class="search-box"><input type="text" id="userSearch" placeholder="Search..." onkeyup="searchUsers()"></div></div>
                        <div class="data-table"><table id="usersTable"><thead><tr><th>Name</th><th>Phone</th><th>Role</th><th>Trust</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
                            ${resp.data.map(user => `
                                <tr>
                                    <td>${escapeHtml(user.name)}</td>
                                    <td>${escapeHtml(user.phone)}</td>
                                    <td><span class="role-badge role-${user.role}">${user.role_display || user.role}</span></td>
                                    <td>${user.trust_score ? user.trust_score.toFixed(1) : '0.0'} ★</td>
                                    <td><span class="status-badge ${user.status ? 'active' : 'suspended'}">${user.status ? 'Active' : 'Suspended'}</span></td>
                                    <td>${formatDate(user.created_at)}</td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-outline" onclick="viewUser(${user.id})">View</button>

                                        <button class="btn btn-sm ${user.status ? 'btn-warning' : 'btn-success'}"
                                            onclick="toggleUserStatus(${user.id}, ${!user.status})">
                                            ${user.status ? 'Suspend' : 'Activate'}
                                        </button>

                                        ${!user.id_verified ? `
                                            <button class="btn btn-sm btn-primary" onclick="verifyUser(${user.id})">
                                                Verify ID
                                            </button>
                                        ` : ''}
 

                                            <!-- 🔥 ADD THIS -->
                                        <button class="btn btn-sm btn-warning" 
                                            onclick="resetUserPassword(${user.id})">
                                            Reset Password
                                        </button>


                                        <!-- ✅ ADD THIS BLOCK -->
                                        ${currentUser.admin_type === 'founder' && user.id !== currentUser.id ? `
                                            ${user.role !== 'admin' ? `
                                                <button class="btn btn-sm btn-success" onclick="promoteUser(${user.id})">
                                                    Promote
                                                </button>
                                            ` : ''}

                                            ${user.role === 'admin' && user.admin_type !== 'founder' ? `
                                                <button class="btn btn-sm btn-danger" onclick="demoteAdmin(${user.id})">
                                                    Demote
                                                </button>
                                            ` : ''}
                                        ` : ''}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody></table></div></div>
                    `;
                } else container.innerHTML = '<div class="error">Failed to load users</div>';
            } catch(e) { container.innerHTML = '<div class="error">Failed to load users</div>'; }
        }

        window.searchUsers = function() {
            const term = document.getElementById('userSearch')?.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            rows.forEach(r => r.style.display = r.textContent.toLowerCase().includes(term) ? '' : 'none');
        };

        async function toggleUserStatus(userId, activate) {
            const action = activate ? 'activate' : 'suspend';
            if (!confirm(`Are you sure you want to ${action} this user?`)) return;
            try {
                const resp = await API.post('/admin/suspend_user.php', { user_id: userId, action });
                if (resp.success) { showToast(resp.message, 'success'); loadUsers(); }
                else showToast(resp.message || 'Failed', 'error');
            } catch(e) { showToast('Error', 'error'); }
        }

        async function verifyUser(userId) {
            if (!confirm('Verify this user\'s ID?')) return;
            try {
                const resp = await API.post('/admin/verify_user.php', { user_id: userId, action: 'verify_id' });
                if (resp.success) { showToast(resp.message, 'success'); loadUsers(); }
                else showToast(resp.message || 'Failed', 'error');
            } catch(e) { showToast('Error', 'error'); }
        }

        async function resetUserPassword(userId) {
                if (!confirm('Generate a temporary password for this user? They will be forced to change it on next login.')) return;

                try {
                    const response = await API.post('/admin/reset_user_password.php', { user_id: userId });

                    if (response.success) {
                        const tempPassword = response.data.temporary_password;

                        alert(`Temporary password generated: ${tempPassword}\nPlease share it securely with the user.`);
                    } else {
                        showToast(response.message || 'Failed to reset password', 'error');
                    }
                } catch (error) {
                    console.error(error);
                    showToast('Error resetting password', 'error');
                }
            }

        // ============================================================
        // User Detail Modal
        // ============================================================
        async function viewUser(userId) {
            const modal = document.getElementById('userModal');
            const modalContent = document.getElementById('userModalContent');
            modalContent.innerHTML = '<div class="loading">Loading...</div>';
            modal.style.display = 'block';
            try {
                const resp = await API.get(`/admin/get_user.php?id=${userId}`);
                if (resp.success && resp.data) {
                    const u = resp.data;
                    modalContent.innerHTML = `
                        <p><strong>Name:</strong> ${escapeHtml(u.name)}</p>
                        <p><strong>Phone:</strong> ${escapeHtml(u.phone)}</p>
                        <p><strong>Email:</strong> ${escapeHtml(u.email || 'N/A')}</p>
                        <p><strong>Role:</strong> ${u.role_display || u.role}</p>
                        <p><strong>Trust Score:</strong> ${u.trust_score ? u.trust_score.toFixed(1) : '0.0'} ★</p>
                        <p><strong>Verification:</strong> ${u.verification_tier || 'basic'}</p>
                        <p><strong>Location:</strong> ${u.county ? `${u.county}${u.subcounty ? ', ' + u.subcounty : ''}` : 'Not set'}</p>
                        <p><strong>Status:</strong> ${u.status ? 'Active' : 'Suspended'}</p>
                        <p><strong>ID Verified:</strong> ${u.id_verified ? 'Yes' : 'No'}</p>
                        <p><strong>Joined:</strong> ${new Date(u.created_at).toLocaleDateString()}</p>
                        ${u.statistics ? `<p><strong>Orders:</strong> ${u.statistics.total_orders || 0}</p><p><strong>Total:</strong> KES ${(u.statistics.total_spent || u.statistics.total_earnings || 0).toLocaleString()}</p>` : ''}
                    `;
                } else modalContent.innerHTML = '<div class="error">Failed to load user details</div>';
            } catch(e) { modalContent.innerHTML = '<div class="error">Failed to load user details</div>'; }
        }

        // Close user modal
        document.querySelector('#userModal .close-modal')?.addEventListener('click', () => document.getElementById('userModal').style.display = 'none');
        window.addEventListener('click', (e) => { if (e.target === document.getElementById('userModal')) document.getElementById('userModal').style.display = 'none'; });

        // ============================================================
        // Admin Management
        // ============================================================
        async function loadAdminManagement() {
            const container = document.getElementById('adminManagementTab');
            container.innerHTML = '<div class="loading">Loading admin list...</div>';
            try {
                const resp = await API.get('/admin/get_admins.php');
                if (resp.success && resp.data) {
                    const isFounder = currentUser.admin_type === 'founder';
                    container.innerHTML = `
                        <div class="admin-section"><h3>Administrators</h3><div class="data-table"><table><thead><tr><th>Name</th><th>Phone</th><th>Type</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
                            ${resp.data.map(admin => `
                                <tr>
                                    <td>${escapeHtml(admin.name)}</td>
                                    <td>${escapeHtml(admin.phone)}</td>
                                    <td>${admin.admin_type === 'founder' ? 'Founder' : 'Admin'}</td>
                                    <td>${formatDate(admin.created_at)}</td>
                                    <td class="action-buttons">
                                        ${isFounder && admin.id !== currentUser.id && admin.admin_type !== 'founder' ? `
                                            <button class="btn btn-sm btn-danger" onclick="demoteAdmin(${admin.id})">Demote</button>
                                            <button class="btn btn-sm btn-primary" onclick="changeAdminPassword(${admin.id})">Change Password</button>
                                        ` : ''}
                                        ${isFounder && admin.id !== currentUser.id && admin.admin_type === 'founder' ? 'Protected' : ''}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody></table></div>
                        ${isFounder ? `
                            <div style="margin-top:20px;">
                                <h4>Promote User to Admin</h4>
                                <div style="display:flex;gap:10px;">
                                    <input type="number" id="promoteUserId" placeholder="User ID" style="flex:1;">
                                    <button class="btn btn-primary" onclick="promoteUser()">Promote</button>
                                </div>
                            </div>
                            <div style="margin-top:20px;">
                                <button class="btn btn-primary" onclick="openCreateAdminModal()">➕ Create New Admin</button>
                            </div>
                        ` : ''}
                    `;
                } else container.innerHTML = '<div class="error">Failed to load admin list</div>';
            } catch(e) { container.innerHTML = '<div class="error">Failed to load admin list</div>'; }
        }

        async function demoteAdmin(userId) {
            if (!confirm('Demote this admin to regular user?')) return;
            try {
                const resp = await API.post('/admin/manage_admins.php', { action: 'demote', user_id: userId });
                if (resp.success) { showToast(resp.message, 'success'); loadAdminManagement(); }
                else showToast(resp.message || 'Failed', 'error');
            } catch(e) { showToast('Error', 'error'); }
        }

                async function promoteUser(userId) {
                    if (!confirm('Promote this user to admin?')) return;

                    try {
                        const resp = await API.post('/admin/manage_admins.php', {
                            action: 'promote',
                            user_id: userId
                        });

                        if (resp.success) {
                            showToast('User promoted successfully', 'success');
                            loadUsers(); // refresh users table
                        } else {
                            showToast(resp.message || 'Promotion failed', 'error');
                        }
                    } catch (e) {
                        console.error(e);
                        showToast('Error promoting user', 'error');
                    }
                }

        async function changeAdminPassword(userId) {
            const newPassword = prompt('Enter new password (min 6 characters):');
            if (!newPassword || newPassword.length < 6) { alert('Password must be at least 6 characters'); return; }
            try {
                const resp = await API.post('/admin/manage_admins.php', { action: 'change_password', user_id: userId, password: newPassword });
                if (resp.success) showToast(resp.message, 'success');
                else showToast(resp.message || 'Failed', 'error');
            } catch(e) { showToast('Error', 'error'); }
        }

        // ============================================================
        // Create Admin Modal
        // ============================================================
        function openCreateAdminModal() {
            document.getElementById('createAdminModal').style.display = 'block';
        }
        function closeCreateAdminModal() {
            document.getElementById('createAdminModal').style.display = 'none';
            document.getElementById('adminName').value = '';
            document.getElementById('adminPhone').value = '';
            document.getElementById('adminPassword').value = '';
            document.getElementById('createAdminMessage').innerHTML = '';
        }
        async function createAdmin() {
            const name = document.getElementById('adminName').value.trim();
            const phone = document.getElementById('adminPhone').value.trim();
            const password = document.getElementById('adminPassword').value;
            const msgDiv = document.getElementById('createAdminMessage');
            msgDiv.innerHTML = '';

            if (!name || !phone || !password) {
                msgDiv.innerHTML = '<div class="error">All fields are required</div>';
                return;
            }
            if (password.length < 6) {
                msgDiv.innerHTML = '<div class="error">Password must be at least 6 characters</div>';
                return;
            }
            if (!/^(07|01)[0-9]{8}$/.test(phone)) {
                msgDiv.innerHTML = '<div class="error">Invalid Kenyan phone number</div>';
                return;
            }
            try {
                const response = await API.post('/admin/create_admin.php', { name, phone, password });
                if (response.success) {
                    msgDiv.innerHTML = '<div class="success">Admin created successfully! Refreshing list...</div>';
                    closeCreateAdminModal();
                    loadAdminManagement();
                } else {
                    msgDiv.innerHTML = `<div class="error">${response.message || 'Failed to create admin'}</div>`;
                }
            } catch (error) {
                console.error('Create admin error:', error);
                msgDiv.innerHTML = '<div class="error">Server error. Check console.</div>';
            }
        }

        // Close create admin modal
        document.querySelectorAll('#createAdminModal .close-modal').forEach(close => {
            close.addEventListener('click', () => document.getElementById('createAdminModal').style.display = 'none');
        });
        window.addEventListener('click', (e) => {
            if (e.target === document.getElementById('createAdminModal')) document.getElementById('createAdminModal').style.display = 'none';
        });

        // ============================================================
        // Orders & Products
        // ============================================================
        async function loadAllOrders() {
            const container = document.getElementById('ordersTab');
            container.innerHTML = '<div class="loading">Loading orders...</div>';
            try {
                const resp = await API.get('/orders/get_orders.php', { role: 'admin', per_page: 100 });
                if (resp.success && resp.data) {
                    container.innerHTML = `<div class="admin-section"><h3>All Orders</h3><div class="data-table"><table><thead><tr><th>Order Code</th><th>Buyer</th><th>Farmer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead><tbody>
                        ${resp.data.map(order => `
                            <tr>
                                <td><a href="order-detail.php?id=${order.id}">${escapeHtml(order.order_code)}</a></td>
                                <td>${escapeHtml(order.buyer.name)}</td>
                                <td>${escapeHtml(order.farmer.name)}</td>
                                <td>KES ${order.total.toLocaleString()}</td>
                                <td><span class="status-badge status-${order.status}">${order.status_display}</span></td>
                                <td>${formatDate(order.created_at)}</td>
                            </tr>
                        `).join('')}
                    </tbody></table></div></div>`;
                } else container.innerHTML = '<div class="error">Failed to load orders</div>';
            } catch(e) { container.innerHTML = '<div class="error">Failed to load orders</div>'; }
        }

        async function loadAllProducts() {
            const container = document.getElementById('productsTab');
            container.innerHTML = '<div class="loading">Loading products...</div>';
            try {
                const resp = await API.get('/products/get_products.php', { per_page: 100 });
                if (resp.success && resp.data) {
                    container.innerHTML = `<div class="admin-section"><h3>All Products</h3><div class="data-table"><table><thead><tr><th>Name</th><th>Farmer</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead><tbody>
                        ${resp.data.map(p => `
                            <tr>
                                <td>${escapeHtml(p.name)}</td>
                                <td>${escapeHtml(p.farmer.name)}</td>
                                <td>KES ${p.price.toLocaleString()}</td>
                                <td>${p.quantity} ${p.unit_abbr}</td>
                                <td><span class="status-badge ${p.quantity > 0 ? 'in-stock' : 'out-of-stock'}">${p.quantity > 0 ? 'In Stock' : 'Out of Stock'}</span></td>
                            </tr>
                        `).join('')}
                    </tbody></table></div></div>`;
                } else container.innerHTML = '<div class="error">Failed to load products</div>';
            } catch(e) { container.innerHTML = '<div class="error">Failed to load products</div>'; }
        }

        // ============================================================
        // Reports & Charts
        // ============================================================
        async function loadReports() {
            const container = document.getElementById('reportsTab');
            container.innerHTML = '<div class="loading">Loading reports...</div>';
            try {
                const resp = await API.get('/admin/get_reports.php', { period: 'month' });
                if (resp.success && resp.data) {
                    const d = resp.data;
                    container.innerHTML = `
                        <div class="admin-section"><div style="display:flex;justify-content:space-between;"><h3>Platform Reports</h3><button class="btn btn-primary" onclick="exportReport()">📊 Export to Excel</button></div>
                        <div class="report-grid">
                            <div class="report-card"><h4>📊 User Statistics</h4><p>Total Users: ${d.users.total.toLocaleString()}</p><p>Buyers: ${d.users.buyers.toLocaleString()}</p><p>Farmers: ${d.users.farmers.toLocaleString()}</p><p>Verified: ${d.users.verified.toLocaleString()}</p><p>New this month: +${d.users.new_in_period}</p></div>
                            <div class="report-card"><h4>🛒 Order Statistics</h4><p>Total Orders: ${d.orders.total.toLocaleString()}</p><p>Completed: ${d.orders.completed.toLocaleString()}</p><p>Cancelled: ${d.orders.cancelled.toLocaleString()}</p><p>Average Order: KES ${d.orders.average_order_value.toLocaleString()}</p><p>Orders this month: ${d.orders.orders_in_period}</p></div>
                            <div class="report-card"><h4>⭐ Review Statistics</h4><p>Total Reviews: ${d.reviews.total.toLocaleString()}</p><p>Average Rating: ${d.reviews.average_rating.toFixed(1)} ★</p><p>5★: ${d.reviews.distribution[5]}</p><p>4★: ${d.reviews.distribution[4]}</p><p>3★: ${d.reviews.distribution[3]}</p><p>2★: ${d.reviews.distribution[2]}</p><p>1★: ${d.reviews.distribution[1]}</p></div>
                        </div></div>
                        <div class="admin-section"><h3>Daily Trends (Last 30 Days)</h3><canvas id="revenueChart" width="400" height="200"></canvas><canvas id="usersChart" width="400" height="200"></canvas></div>
                    `;
                    setTimeout(() => {
                        if (d.daily_revenue && d.daily_new_users && d.daily_new_products) {
                            renderCharts(d.daily_revenue, d.daily_new_users, d.daily_new_products);
                        } else {
                            console.warn('Daily trend data missing from API');
                        }
                    }, 100);
                } else container.innerHTML = '<div class="error">Failed to load reports</div>';
            } catch(e) { container.innerHTML = '<div class="error">Failed to load reports</div>'; }
        }

        function renderCharts(dailyRevenue, dailyNewUsers, dailyNewProducts) {
            const ctxRev = document.getElementById('revenueChart')?.getContext('2d');
            const ctxUsers = document.getElementById('usersChart')?.getContext('2d');
            if (!ctxRev || !ctxUsers) return;
            const labels = dailyRevenue.map(d => d.date);
            new Chart(ctxRev, {
                type: 'line',
                data: { labels, datasets: [{ label: 'Daily Revenue (KES)', data: dailyRevenue.map(d => d.revenue), borderColor: '#2e7d32', fill: false }] }
            });
            new Chart(ctxUsers, {
                type: 'bar',
                data: { labels, datasets: [
                    { label: 'New Users', data: dailyNewUsers.map(d => d.count), backgroundColor: '#2196f3' },
                    { label: 'New Products', data: dailyNewProducts.map(d => d.count), backgroundColor: '#ff9800' }
                ] }
            });
        }

        async function exportReport() {
            window.location.href = `/api/admin/export_report.php?period=month`;
        }

        // ============================================================
        // Tab switching
        // ============================================================
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                const tab = btn.dataset.tab;
                document.getElementById(`${tab}Tab`).classList.add('active');
                loadTab(tab);
            });
        });

        // ============================================================
        // Logout
        // ============================================================
        document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
            e.preventDefault();
            await API.post('/auth/logout.php');
            window.location.href = 'login.php';
        });

        // ============================================================
        // Initialize
        // ============================================================
        document.addEventListener('DOMContentLoaded', () => {
            loadDashboard();
            updateCartCount();
        });

                    document.addEventListener('DOMContentLoaded', () => {
                // Tab switching logic
                const tabs = document.querySelectorAll('.tab-btn');
                tabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                        const target = tab.dataset.tab;
                        
                        // UI Update
                        tabs.forEach(t => t.classList.remove('active'));
                        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                        
                        tab.classList.add('active');
                        document.getElementById(`${target}Tab`).classList.add('active');
                        
                        // Data Loading
                        loadTab(target);
                    });
                });

                // Initial load
                loadDashboard();
                updateCartCount();

                // Logout logic
                document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
                    e.preventDefault();
                    if(confirm('Are you sure you want to logout?')) {
                        try {
                            const resp = await API.post('/auth/logout.php');
                            window.location.href = 'login.php';
                        } catch(e) { window.location.href = 'login.php'; }
                    }
                });
            });

            // Placeholder for export function
            function exportReport() {
                window.location.href = '../api/admin/export_report.php';
            }
    </script>
</body>
</html>
































































<?php if (false): ?>
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
$userName = $_SESSION['user_name'] ?? $_SESSION['user_name'] ?? 'Admin';
$userRole = $_SESSION['user_role'] ?? $_SESSION['user_role'] ?? '';

// CRITICAL: Check if user is admin
if ($userRole !== 'admin') {
    // Redirect non-admin users to appropriate dashboard
    if ($userRole === 'farmer') {
        header('Location: dashboard.php');
        exit;
    } elseif ($userRole === 'buyer') {
        header('Location: dashboard.php');
        exit;
    } else {
        header('Location: login.php');
        exit;
    }
}

$userPhone = $_SESSION['user_phone'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - TrustLink</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/">
                    <span class="logo-icon">🌾</span>
                    <span class="logo-text">TrustLink Admin</span>
                </a>
            </div>

                    <!-- User Details Modal -->
                    <div id="userModal" class="modal" style="display: none;">
                        <div class="modal-content">
                            <span class="close-modal">&times;</span>
                            <h3>User Details</h3>
                            <div id="userModalContent">Loading...</div>
                        </div>
                    </div>


            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="products.php" class="nav-link">Browse Products</a>
                    <a href="cart.php" class="cart-icon-link" id="cartIconLink">
                        <span class="cart-icon">🛒</span>
                        <span id="cartCount" class="cart-badge">0</span>
                    </a>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($userName); ?></span>
                    <span class="admin-badge">Admin</span>
                    <a href="#" id="logoutBtn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Admin Panel</h1>
        
        <div class="admin-tabs">
            <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
            <button class="tab-btn" data-tab="users">Users</button>
            <button class="tab-btn" data-tab="adminManagement">Admin Management</button>
            <button class="tab-btn" data-tab="orders">Orders</button>
            <button class="tab-btn" data-tab="products">Products</button>
            <button class="tab-btn" data-tab="reports">Reports</button>
        </div>
        
        <div id="dashboardTab" class="tab-content active">
            <div class="loading">Loading dashboard...</div>
        </div>
        
        <div id="usersTab" class="tab-content">
            <div class="loading">Loading users...</div>
        </div>

        <div id="adminManagementTab" class="tab-content">
            <div class="loading">Loading admin list...</div>
        </div>
        
        <div id="ordersTab" class="tab-content">
            <div class="loading">Loading orders...</div>
        </div>
        
        <div id="productsTab" class="tab-content">
            <div class="loading">Loading products...</div>
        </div>
        
        <div id="reportsTab" class="tab-content">
            <div class="loading">Loading reports...</div>
        </div>
    </div>

    <script>
        // Pass admin user data from PHP to JavaScript
        const currentUser = {
            id: <?php echo json_encode($userId); ?>,
            name: <?php echo json_encode($userName); ?>,
            role: <?php echo json_encode($userRole); ?>,
            phone: <?php echo json_encode($userPhone); ?>,
            email: <?php echo json_encode($userEmail); ?>
        };
        
        console.log('Admin user (from PHP):', currentUser);
        
        // Double-check admin role (though PHP already does)
        if (currentUser.role !== 'admin') {
            showToast('Unauthorized access. Redirecting...', 'error');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        }
        
        let currentTab = 'dashboard';
        
        async function updateCartCount() {
            try {
                const response = await API.get('../cart/get_cart_count.php');
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


        async function loadTab(tab) {
            switch(tab) {
                case 'dashboard':
                    await loadDashboard();
                    break;
                case 'users':
                    await loadUsers();
                    break;
                case 'adminManagement':
                    await loadAdminManagement();
                    break;
                case 'orders':
                    await loadAllOrders();
                    break;
                case 'products':
                    await loadAllProducts();
                    break;
                case 'reports':
                    await loadReports();
                    break;
            }
        }
        
        async function loadDashboard() {
            const container = document.getElementById('dashboardTab');
            container.innerHTML = '<div class="loading">Loading dashboard statistics...</div>';
            
            try {
                const response = await API.get('../admin/get_reports.php', { period: 'month' });
                if (response.success && response.data) {
                    const data = response.data;
                    container.innerHTML = `
                        <div class="admin-stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon">👥</div>
                                <div class="stat-value">${data.users.total.toLocaleString()}</div>
                                <div class="stat-label">Total Users</div>
                                <div class="stat-trend">+${data.users.new_in_period} this month</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">🛒</div>
                                <div class="stat-value">${data.orders.total.toLocaleString()}</div>
                                <div class="stat-label">Total Orders</div>
                                <div class="stat-trend">${data.orders.orders_in_period} this month</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">💰</div>
                                <div class="stat-value">KES ${data.orders.total_revenue.toLocaleString()}</div>
                                <div class="stat-label">Total Revenue</div>
                                <div class="stat-trend">KES ${data.orders.revenue_in_period.toLocaleString()} this month</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">📦</div>
                                <div class="stat-value">${data.products.active.toLocaleString()}</div>
                                <div class="stat-label">Active Products</div>
                                <div class="stat-trend">${data.products.total} total products</div>
                            </div>
                        </div>
                        
                        <div class="admin-section">
                            <h3>Top Performing Farmers</h3>
                            <div class="data-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Farmer</th>
                                            <th>Trust Score</th>
                                            <th>Orders</th>
                                            <th>Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.top_farmers && data.top_farmers.length > 0 ? 
                                            data.top_farmers.map(f => `
                                                <tr>
                                                    <td>${escapeHtml(f.name)}</td>
                                                    <td>${f.trust_score.toFixed(1)} ★</td>
                                                    <td>${f.total_orders}</td>
                                                    <td>KES ${f.total_earnings.toLocaleString()}</td>
                                                </tr>
                                            `).join('') : 
                                            '<tr><td colspan="4">No data available</td></tr>'
                                        }
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = '<div class="error">Failed to load dashboard data</div>';
                }
            } catch (error) {
                console.error('Failed to load dashboard:', error);
                container.innerHTML = '<div class="error">Failed to load dashboard. Please try again.</div>';
            }
        }
        
        async function loadUsers() {
            const container = document.getElementById('usersTab');
            container.innerHTML = '<div class="loading">Loading users...</div>';
            
            try {
                const response = await API.get('../admin/get_users.php', { per_page: 100 });
                if (response.success && response.data) {
                    container.innerHTML = `
                        <div class="admin-section">
                            <div class="section-header">
                                <h3>All Users</h3>
                                <div class="search-box">
                                    <input type="text" id="userSearch" placeholder="Search by name or phone..." onkeyup="searchUsers()">
                                </div>
                            </div>
                            <div class="data-table">
                                <table id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone</th>
                                            <th>Role</th>
                                            <th>Trust Score</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.data.map(user => `
                                            <tr>
                                                <td>${escapeHtml(user.name)}</td>
                                                <td>${escapeHtml(user.phone)}</td>
                                                <td><span class="role-badge role-${user.role}">${user.role_display || user.role}</span></td>
                                                <td>${user.trust_score ? user.trust_score.toFixed(1) : '0.0'} ★</td>
                                                <td><span class="status-badge ${user.status ? 'active' : 'suspended'}">${user.status ? 'Active' : 'Suspended'}</span></td>
                                                <td>${user.created_at ? formatDate(user.created_at) : 'N/A'}</td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-outline" onclick="viewUser(${user.id})">View</button>
                                                    <button class="btn btn-sm ${user.status ? 'btn-warning' : 'btn-success'}" 
                                                            onclick="toggleUserStatus(${user.id}, ${!user.status})">
                                                        ${user.status ? 'Suspend' : 'Activate'}
                                                    </button>
                                                    ${!user.id_verified ? `<button class="btn btn-sm btn-primary" onclick="verifyUser(${user.id})">Verify ID</button>` : ''}
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = '<div class="error">Failed to load users</div>';
                }
            } catch (error) {
                console.error('Failed to load users:', error);
                container.innerHTML = '<div class="error">Failed to load users. Please try again.</div>';
            }
        }
        
        async function toggleUserStatus(userId, activate) {
            const action = activate ? 'activate' : 'suspend';
            const confirmMsg = `Are you sure you want to ${action} this user?`;
            
            if (!confirm(confirmMsg)) return;
            
            try {
                const response = await API.post('/admin/suspend_user.php', { user_id: userId, action });
                if (response.success) {
                    showToast(response.message, 'success');
                    loadUsers(); // Reload users list
                } else {
                    showToast(response.message || 'Failed to update user status', 'error');
                }
            } catch (error) {
                console.error('Toggle user status error:', error);
                showToast(error.message || 'Failed to update user status', 'error');
            }
        }
        
        async function verifyUser(userId) {
            if (!confirm('Verify this user\'s ID? This will increase their trust score.')) return;
            
            try {
                const response = await API.post('/admin/verify_user.php', { user_id: userId, action: 'verify_id' });
                if (response.success) {
                    showToast(response.message, 'success');
                    loadUsers(); // Reload users list
                } else {
                    showToast(response.message || 'Failed to verify user', 'error');
                }
            } catch (error) {
                console.error('Verify user error:', error);
                showToast(error.message || 'Failed to verify user', 'error');
            }
        }
        
                    function viewUser(userId) {
                    // Temporarily alert, or we could open a modal
                    alert(`View user ID ${userId} – this feature will be implemented soon.`);
                    // Alternatively, we could load user details in a modal.
                }

                                async function resetUserPassword(userId) {
                    if (!confirm('Generate a temporary password for this user? They will be forced to change it on next login.')) return;

                    try {
                        const response = await API.post('/admin/reset_user_password.php', { user_id: userId });

                        if (response.success) {
                            const tempPassword = response.data.temporary_password;

                            alert(`Temporary password generated: ${tempPassword}\nPlease share it securely with the user.`);
                        } else {
                            showToast(response.message || 'Failed to reset password', 'error');
                        }
                    } catch (error) {
                        console.error(error);
                        showToast('Error resetting password', 'error');
                    }
                }

                async function loadAdminManagement() {
    const container = document.getElementById('adminManagementTab');
    container.innerHTML = '<div class="loading">Loading admin users...</div>';
    try {
        const response = await API.get('../admin/get_admins.php');
        if (response.success && response.data) {
            const isFounder = currentUser.admin_type === 'founder';
            container.innerHTML = `
                <div class="admin-section">
                    <h3>Administrators</h3>
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Type</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${response.data.map(admin => `
                                    <tr>
                                        <td>${escapeHtml(admin.name)}</td>
                                        <td>${escapeHtml(admin.phone)}</td>
                                        <td>${admin.admin_type === 'founder' ? 'Founder' : 'Admin'}</td>
                                        <td>${formatDate(admin.created_at)}</td>
                                        <td class="action-buttons">
                                            ${isFounder && admin.id !== currentUser.id && admin.admin_type !== 'founder' ? `
                                                <button class="btn btn-sm btn-danger" onclick="demoteAdmin(${admin.id})">Demote</button>
                                                <button class="btn btn-sm btn-primary" onclick="changeAdminPassword(${admin.id})">Change Password</button>
                                            ` : ''}
                                            ${isFounder && admin.id !== currentUser.id && admin.admin_type === 'founder' ? 'Protected' : ''}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    ${isFounder ? `
                        <div style="margin-top: 20px;">
                            <h4>Promote User to Admin</h4>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="promoteUserId" placeholder="User ID or Phone" style="flex:1;">
                                <button class="btn btn-primary" onclick="promoteUser()">Promote</button>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        } else {
            container.innerHTML = '<div class="error">Failed to load admin list</div>';
        }
    } catch (error) {
        container.innerHTML = '<div class="error">Failed to load admin list</div>';
    }
}

async function demoteAdmin(userId) {
    if (!confirm('Demote this admin to regular user? This action cannot be undone.')) return;
    try {
        const response = await API.post('../admin/manage_admins.php', { action: 'demote', user_id: userId });
        if (response.success) {
            showToast(response.message, 'success');
            loadAdminManagement(); // refresh list
        } else {
            showToast(response.message || 'Failed to demote', 'error');
        }
    } catch (error) {
        showToast(error.message || 'Failed to demote', 'error');
    }
}

async function promoteUser() {
    const userId = document.getElementById('promoteUserId').value.trim();
    if (!userId || isNaN(userId)) {
        showToast('Please enter a valid numeric User ID', 'error');
        return;
    }
    if (!confirm(`Promote user ID ${userId} to admin?`)) return;
    try {
        const resp = await API.post('/admin/manage_admins.php', { action: 'promote', user_id: parseInt(userId) });
        if (resp.success) {
            showToast(resp.message, 'success');
            document.getElementById('promoteUserId').value = '';
            loadAdminManagement();
        } else {
            showToast(resp.message || 'Failed to promote', 'error');
        }
    } catch(e) { showToast('Error', 'error'); }
}

async function changeAdminPassword(userId) {
    const newPassword = prompt('Enter new password (min 6 characters):');
    if (!newPassword || newPassword.length < 6) {
        alert('Password must be at least 6 characters');
        return;
    }
    try {
        const response = await API.post('/admin/manage_admins.php', { action: 'change_password', user_id: userId, password: newPassword });
        if (response.success) {
            showToast(response.message, 'success');
        } else {
            showToast(response.message || 'Failed to change password', 'error');
        }
    } catch (error) {
        showToast(error.message || 'Failed to change password', 'error');
    }
}
        
        async function loadAllOrders() {
            const container = document.getElementById('ordersTab');
            container.innerHTML = '<div class="loading">Loading orders...</div>';
            
            try {
                const response = await API.get('/orders/get_orders.php', { role: 'admin', per_page: 100 });
                if (response.success && response.data) {
                    container.innerHTML = `
                        <div class="admin-section">
                            <h3>All Orders</h3>
                            <div class="data-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Order Code</th>
                                            <th>Buyer</th>
                                            <th>Farmer</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.data.map(order => `
                                            <tr>
                                                <td><a href="order-detail.php?id=${order.id}">${escapeHtml(order.order_code)}</a></td>
                                                <td>${escapeHtml(order.buyer.name)}</td>
                                                <td>${escapeHtml(order.farmer.name)}</td>
                                                <td>KES ${order.total.toLocaleString()}</td>
                                                <td><span class="status-badge status-${order.status}">${order.status_display}</span></td>
                                                <td>${formatDate(order.created_at)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = '<div class="error">Failed to load orders</div>';
                }
            } catch (error) {
                console.error('Failed to load orders:', error);
                container.innerHTML = '<div class="error">Failed to load orders. Please try again.</div>';
            }
        }
        
        async function loadAllProducts() {
            const container = document.getElementById('productsTab');
            container.innerHTML = '<div class="loading">Loading products...</div>';
            
            try {
                const response = await API.get('/products/get_products.php', { per_page: 100 });
                if (response.success && response.data) {
                    container.innerHTML = `
                        <div class="admin-section">
                            <h3>All Products</h3>
                            <div class="data-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Farmer</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.data.map(product => `
                                            <tr>
                                                <td>${escapeHtml(product.name)}</td>
                                                <td>${escapeHtml(product.farmer.name)}</td>
                                                <td>KES ${product.price.toLocaleString()}</td>
                                                <td>${product.quantity} ${product.unit_abbr}</td>
                                                <td><span class="status-badge ${product.quantity > 0 ? 'in-stock' : 'out-of-stock'}">${product.quantity > 0 ? 'In Stock' : 'Out of Stock'}</span></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = '<div class="error">Failed to load products</div>';
                }
            } catch (error) {
                console.error('Failed to load products:', error);
                container.innerHTML = '<div class="error">Failed to load products. Please try again.</div>';
            }
        }
        
        async function loadReports() {
            const container = document.getElementById('reportsTab');
            container.innerHTML = '<div class="loading">Loading reports...</div>';
            
            try {
                const response = await API.get('/admin/get_reports.php', { period: 'month' });
if (response.success && response.data) {
                    const data = response.data;
                    container.innerHTML = `
                        <div class="admin-section">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h3>Platform Reports</h3>
                                <button class="btn btn-primary" onclick="exportReport()">📊 Export to Excel</button>
                            </div>
                            <div class="report-grid">
                                <div class="report-card">
                                    <h4>📊 User Statistics</h4>
                                    <p><strong>Total Users:</strong> ${data.users.total.toLocaleString()}</p>
                                    <p><strong>Buyers:</strong> ${data.users.buyers.toLocaleString()}</p>
                                    <p><strong>Farmers:</strong> ${data.users.farmers.toLocaleString()}</p>
                                    <p><strong>Verified Users:</strong> ${data.users.verified.toLocaleString()}</p>
                                    <p><strong>New this month:</strong> +${data.users.new_in_period}</p>
                                </div>
                                <div class="report-card">
                                    <h4>🛒 Order Statistics</h4>
                                    <p><strong>Total Orders:</strong> ${data.orders.total.toLocaleString()}</p>
                                    <p><strong>Completed:</strong> ${data.orders.completed.toLocaleString()}</p>
                                    <p><strong>Cancelled:</strong> ${data.orders.cancelled.toLocaleString()}</p>
                                    <p><strong>Average Order:</strong> KES ${data.orders.average_order_value.toLocaleString()}</p>
                                    <p><strong>Orders this month:</strong> ${data.orders.orders_in_period}</p>
                                </div>
                                <div class="report-card">
                                    <h4>⭐ Review Statistics</h4>
                                    <p><strong>Total Reviews:</strong> ${data.reviews.total.toLocaleString()}</p>
                                    <p><strong>Average Rating:</strong> ${data.reviews.average_rating.toFixed(1)} ★</p>
                                    <p><strong>5-Star Reviews:</strong> ${data.reviews.distribution[5]}</p>
                                    <p><strong>4-Star Reviews:</strong> ${data.reviews.distribution[4]}</p>
                                    <p><strong>3-Star Reviews:</strong> ${data.reviews.distribution[3]}</p>
                                    <p><strong>2-Star Reviews:</strong> ${data.reviews.distribution[2]}</p>
                                    <p><strong>1-Star Reviews:</strong> ${data.reviews.distribution[1]}</p>
                                </div>
                            </div>
                        </div>
                        <div class="admin-section">
                            <h3>Daily Trends (Last 30 Days)</h3>
                            <canvas id="revenueChart" width="400" height="200"></canvas>
                            <canvas id="usersChart" width="400" height="200"></canvas>
                        </div>
                    `;

                    // Render charts after the DOM is updated
                    setTimeout(() => {
                        renderCharts(data.daily_revenue, data.daily_new_users, data.daily_new_products);
                    }, 100);
                }
                 else {
                    container.innerHTML = '<div class="error">Failed to load reports</div>';
                }
            } catch (error) {
                console.error('Failed to load reports:', error);
                container.innerHTML = '<div class="error">Failed to load reports. Please try again.</div>';
            }
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            return new Date(dateStr).toLocaleDateString('en-KE', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
function renderCharts(dailyRevenue, dailyNewUsers, dailyNewProducts) {
            const ctxRevenue = document.getElementById('revenueChart')?.getContext('2d');
            const ctxUsers = document.getElementById('usersChart')?.getContext('2d');
            if (!ctxRevenue || !ctxUsers) return;

            const labels = dailyRevenue.map(d => d.date);

            new Chart(ctxRevenue, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Daily Revenue (KES)',
                        data: dailyRevenue.map(d => d.revenue),
                        borderColor: '#2e7d32',
                        fill: false
                    }]
                }
            });

            new Chart(ctxUsers, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'New Users',
                            data: dailyNewUsers.map(d => d.count),
                            backgroundColor: '#2196f3'
                        },
                        {
                            label: 'New Products',
                            data: dailyNewProducts.map(d => d.count),
                            backgroundColor: '#ff9800'
                        }
                    ]
                }
            });
        }

        async function exportReport() {
            const period = document.querySelector('.tab-btn.active[data-tab="reports"]')?.dataset.period || 'month';
            window.location.href = `../api/admin/export_report.php?period=${period}`;
        }


        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                const tab = btn.dataset.tab;
                document.getElementById(`${tab}Tab`).classList.add('active');
                currentTab = tab;
                loadTab(tab);
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
        
        // Search function for users
        window.searchUsers = function() {
            const searchTerm = document.getElementById('userSearch')?.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        };
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadDashboard();
            updateCartCount();
        });
    </script>
    <script src="../assets/js/api.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
  <?php endif; ?>