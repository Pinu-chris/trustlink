<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Products</title>
</head>
<body>

<div class="container">
    <h2>My Products</h2>
    <div id="productsList">Loading...</div>

    <style>
/* ===== GLOBAL ===== */
body {
    font-family: 'JetBrains Mono', monospace;
    background: #f5f7fa;
    margin: 0;
    padding: 0;
}

/* Container */
.container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 1rem;
}

/* Title */
h2 {
    font-size: 1.6rem;
    margin-bottom: 1.5rem;
    border-bottom: 3px solid #4CAF50;
    display: inline-block;
    padding-bottom: 0.3rem;
}

/* ===== GRID ===== */
#productsList {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.5rem;
}

/* ===== CARD ===== */
.card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    padding: 1rem;
    transition: 0.25s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.12);
}

/* ===== IMAGE ===== */
.card img {
    width: 100%;
    height: 160px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 0.8rem;
    background: #eee;
}

/* ===== TEXT ===== */
.card h3 {
    font-size: 1rem;
    margin: 0.3rem 0;
}

.card p {
    margin: 0.2rem 0;
    font-size: 0.9rem;
    color: #555;
}

/* Price highlight */
.card p:nth-child(3) {
    font-weight: bold;
    color: #2e7d32;
    font-size: 1.1rem;
}

/* ===== BUTTONS ===== */
.card button {
    margin-top: 0.5rem;
    padding: 0.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: 0.2s;
}

/* Edit */
.card button:first-of-type {
    background: #2196F3;
    color: white;
}

.card button:first-of-type:hover {
    background: #1976D2;
}

/* Delete */
.card button:last-of-type {
    background: #f44336;
    color: white;
}

.card button:last-of-type:hover {
    background: #d32f2f;
}

/* ===== EMPTY STATE ===== */
.empty {
    background: white;
    padding: 2rem;
    text-align: center;
    border-radius: 10px;
    color: #777;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 600px) {
    .card img {
        height: 120px;
    }
}
</style>
</div>

<script src="../assets/js/api.js"></script>
<script>
async function loadMyProducts() {
    try {
        const res = await API.get('/products/get_my_products.php');
        const container = document.getElementById('productsList');

        if (!res.data.length) {
            container.innerHTML = "No products yet.";
            return;
        }

        container.innerHTML = res.data.map(p => `
            <div class="card">
               <img src="${getProductImage(p)}" width="100">
                <h3>${p.name}</h3>
                <p>KES ${p.price}</p>
                <p>${p.quantity} ${p.unit}</p>

                <button onclick="editProduct(${p.id})">✏️ Edit</button>
                <button onclick="deleteProduct(${p.id})">🗑 Delete</button>
            </div>
        `).join('');

    } catch (e) {
        console.error(e);
    }
}

async function deleteProduct(id) {
    if (!confirm("Delete this product?")) return;

    try {
        await API.delete(`/products/delete_product.php?id=${id}`);
        loadMyProducts();
    } catch (e) {
        alert("Delete failed");
    }
}
function getProductImage(product) {
    let imagePath = product.primary_image || product.image || product.image_url || product.image_path || product.photo;

    if (!imagePath || imagePath.trim() === '') {
        return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="200"%3E%3Crect width="300" height="200" fill="%23e0e0e0"/%3E%3Ctext x="50%25" y="50%25" font-size="14" fill="%23999" text-anchor="middle"%3ENo Image%3C/text%3E%3C/svg%3E';
    }

    // If full URL
    if (imagePath.startsWith('http')) return imagePath;

    // If already absolute
    if (imagePath.startsWith('/')) return imagePath;

    // ✅ CORRECT PATH
    return '../assets/images/uploads/products/' + imagePath;
}

function editProduct(id) {
    window.location.href = `edit-product.php?id=${id}`;
}

loadMyProducts();
</script>