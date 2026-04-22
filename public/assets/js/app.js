/**
 * TRUSTLINK - Main Application Logic
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Global app state and common functionality
 */

// Global app state
const App = {
    user: null,
    isLoggedIn: false,
    
    /**
     * Initialize the application
     */
    async init() {
        await this.checkAuth();
        this.setupEventListeners();
        this.updateUI();
    },
    
    /**
     * Check authentication status
     */
    async checkAuth() {
        try {
            const response = await API.get('/users/get_profile.php');
            if (response.success && response.data) {
                this.user = response.data;
                this.isLoggedIn = true;
            }
        } catch (error) {
            this.user = null;
            this.isLoggedIn = false;
        }
    },
    
    /**
     * Setup global event listeners
     */
    setupEventListeners() {
        // Mobile menu toggle
        const mobileBtn = document.getElementById('mobileMenuBtn');
        if (mobileBtn) {
            mobileBtn.addEventListener('click', () => {
                document.querySelector('.nav-links')?.classList.toggle('active');
            });
        }
        
        // Logout button
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.logout();
            });
        }
    },
    
    /**
     * Update UI based on auth state
     */
    updateUI() {
        const authLinks = document.getElementById('authLinks');
        const userMenu = document.getElementById('userMenu');
        const userName = document.getElementById('userName');
        const userAvatar = document.getElementById('userAvatar');
        
        if (this.isLoggedIn && this.user) {
            // Show user menu
            if (authLinks) authLinks.style.display = 'none';
            if (userMenu) userMenu.style.display = 'block';
            if (userName) userName.textContent = this.user.name.split(' ')[0];
            if (userAvatar && this.user.profile_photo) {
                userAvatar.src = this.user.profile_photo;
            }
            
            // Show role-specific links
            const addProductLink = document.getElementById('addProductLink');
            const farmerOrderLink = document.getElementById('farmerOrderLink');
            const adminLink = document.getElementById('adminLink');
            
            if (addProductLink) {
                addProductLink.style.display = this.user.role === 'farmer' ? 'block' : 'none';
            }
            if (farmerOrderLink) {
                farmerOrderLink.style.display = this.user.role === 'farmer' ? 'block' : 'none';
            }
            if (adminLink) {
                adminLink.style.display = this.user.role === 'admin' ? 'block' : 'none';
            }
        } else {
            // Show login/register links
            if (authLinks) authLinks.style.display = 'flex';
            if (userMenu) userMenu.style.display = 'none';
        }
    },
    
    /**
     * Logout user
     */
    async logout() {
        try {
            await API.post('/auth/logout.php');
            this.user = null;
            this.isLoggedIn = false;
            this.updateUI();
            window.location.href = '/';
        } catch (error) {
            console.error('Logout failed:', error);
        }
    },
    
    /**
     * Redirect to login if not authenticated
     */
    requireAuth(redirectUrl = '/login.html') {
        if (!this.isLoggedIn) {
            window.location.href = redirectUrl;
            return false;
        }
        return true;
    },
    
    /**
     * Require specific role
     */
    requireRole(role, redirectUrl = '/') {
        if (!this.isLoggedIn || this.user?.role !== role) {
            window.location.href = redirectUrl;
            return false;
        }
        return true;
    }
};

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

// Make App available globally
window.App = App;