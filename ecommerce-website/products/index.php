<?php
// ========================================
// FILE: products/index.php (MAKE SURE THIS IS IN THE CORRECT LOCATION)
// ========================================
require_once '../config.php';

// Get categories
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = $stmt->fetchAll();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get total products count
$total_stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
$total_products = $total_stmt->fetch()['total'];
$total_pages = ceil($total_products / $per_page);

// Get products with pagination
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $per_page, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Get featured products
$featured_stmt = $pdo->query("
    SELECT * FROM products 
    WHERE featured = 1 AND status = 'active' 
    ORDER BY RAND() 
    LIMIT 4
");
$featured_products = $featured_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - <?= SITE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        
        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .nav-links .cart-btn {
            background: white;
            color: #667eea;
            font-weight: bold;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 18px;
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #ccc;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .featured-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ffc107;
            color: #212529;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            font-size: 12px;
            color: #667eea;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .product-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .product-price {
            font-size: 24px;
            color: #28a745;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .product-stock {
            font-size: 12px;
            margin-bottom: 15px;
        }
        
        .in-stock { color: #28a745; }
        .low-stock { color: #ffc107; }
        .out-of-stock { color: #dc3545; }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-block;
            text-align: center;
            flex: 1;
        }
        
        .btn:hover { background: #5568d3; }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover { background: #5a6268; }
        
        /* Categories Sidebar */
        .layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 40px;
        }
        
        @media (max-width: 768px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }
        
        .sidebar {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .sidebar h3 {
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .category-list {
            list-style: none;
        }
        
        .category-item {
            margin-bottom: 10px;
        }
        
        .category-item a {
            display: block;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .category-item a:hover {
            background: #f8f9fa;
        }
        
        .category-item.active a {
            background: #667eea;
            color: white;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .pagination a, 
        .pagination span {
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
        }
        
        /* Featured Section */
        .featured-section {
            margin-bottom: 50px;
        }
        
        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* No Products */
        .no-products {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            grid-column: 1 / -1;
        }
        
        .no-products .icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .no-products h3 {
            color: #666;
            margin-bottom: 15px;
        }
        
        /* Search Bar */
        .search-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-form input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-form button {
            padding: 12px 30px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🛒 <?= SITE_NAME ?></a>
            <div class="nav-links">
                <a href="../index.php">Home</a>
                <a href="index.php" class="active">Products</a>
                <?php if (isLoggedIn()): ?>
                    <?php if (isset($_SESSION['admin_id'])): ?>
                        <a href="../admin/index.php">Admin Panel</a>
                        <a href="../admin/logout.php">Logout</a>
                    <?php else: ?>
                        <a href="../cart/index.php" class="cart-btn">🛒 Cart</a>
                        <a href="../user/dashboard.php">My Account</a>
                        <a href="../auth/logout.php">Logout</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="../auth/login.php">Login</a>
                    <a href="../auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <h1>Browse Products</h1>
            <p>Discover amazing products at great prices</p>
        </div>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <form action="search.php" method="GET" class="search-form">
                <input type="text" name="q" placeholder="Search for products..." 
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button type="submit" class="btn">Search</button>
            </form>
        </div>
        
        <?php if (!empty($featured_products)): ?>
        <div class="featured-section">
            <h2 class="section-title">🌟 Featured Products</h2>
            <div class="products-grid">
                <?php foreach ($featured_products as $product): ?>
                <div class="product-card">
                    <a href="detail.php?id=<?= $product['id'] ?>">
                        <div class="product-image">
                            <?php if (!empty($product['image'])): ?>
                                <!-- CORRECTED IMAGE PATH -->
                                <img src="../assets/uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 48px; color: #ccc; background: #f0f0f0;">📦</div>
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f0f0; color: #ccc; font-size: 48px;">📦</div>
                            <?php endif; ?>
                            <div class="featured-badge">FEATURED</div>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                            <div class="product-description">
                                <?= substr(htmlspecialchars($product['description'] ?? ''), 0, 60) ?>...
                            </div>
                            <div class="product-actions">
                                <a href="detail.php?id=<?= $product['id'] ?>" class="btn">View Details</a>
                                <a href="../cart/add.php?id=<?= $product['id'] ?>" class="btn btn-secondary">
                                    Add to Cart

                                    
                                </a>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="layout">
            <!-- Categories Sidebar -->
            <div class="sidebar">
                <h3>Categories</h3>
                <ul class="category-list">
                    <li class="category-item <?= (!isset($_GET['category'])) ? 'active' : '' ?>">
                        <a href="index.php">All Products</a>
                    </li>
                    <?php foreach ($categories as $category): ?>
                    <li class="category-item <?= (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'active' : '' ?>">
                        <a href="category.php?id=<?= $category['id'] ?>">
                            <?= htmlspecialchars($category['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <h3 style="margin-top: 30px;">Filters</h3>
                <div style="margin-top: 15px;">
                    <a href="?sort=price_asc" class="btn btn-secondary" style="margin-bottom: 10px; width: 100%;">
                        Price: Low to High
                    </a>
                    <a href="?sort=price_desc" class="btn btn-secondary" style="margin-bottom: 10px; width: 100%;">
                        Price: High to Low
                    </a>
                    <a href="?sort=newest" class="btn btn-secondary" style="width: 100%;">
                        Newest First
                    </a>
                </div>
            </div>
            
            <!-- Main Products Grid -->
            <div>
                <h2 style="margin-bottom: 20px; color: #333;">
                    All Products (<?= $total_products ?>)
                </h2>
                
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <div class="icon">📦</div>
                        <h3>No products found</h3>
                        <p style="color: #666; margin-bottom: 20px;">
                            Check back later for new products.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <a href="detail.php?id=<?= $product['id'] ?>">
                                <div class="product-image">
                                    <?php if (!empty($product['image'])): ?>
                                        <!-- CORRECTED IMAGE PATH -->
                                        <img src="../assets/uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                        <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 48px; color: #ccc; background: #f0f0f0;">📦</div>
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f0f0; color: #ccc; font-size: 48px;">📦</div>
                                    <?php endif; ?>
                                    <?php if ($product['featured']): ?>
                                        <div class="featured-badge">⭐</div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-category">
                                        <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>
                                    </div>
                                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="product-description">
                                        <?= substr(htmlspecialchars($product['description'] ?? ''), 0, 80) ?>...
                                    </div>
                                    <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                                    <div class="product-stock <?= 
                                        ($product['quantity'] > 10) ? 'in-stock' : 
                                        (($product['quantity'] > 0) ? 'low-stock' : 'out-of-stock')
                                    ?>">
                                        <?php if ($product['quantity'] > 10): ?>
                                            ✅ In Stock
                                        <?php elseif ($product['quantity'] > 0): ?>
                                            ⚠️ Only <?= $product['quantity'] ?> left
                                        <?php else: ?>
                                            ❌ Out of Stock
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-actions">
                                        <a href="detail.php?id=<?= $product['id'] ?>" class="btn">View Details</a>
                                        <?php if ($product['quantity'] > 0): ?>
                                            <a href="../cart/add.php?id=<?= $product['id'] ?>" class="btn btn-secondary">
                                                Add to Cart
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer style="background: #333; color: white; text-align: center; padding: 30px 20px; margin-top: 60px;">
        <p>&copy; 2024 <?= SITE_NAME ?>. All rights reserved.</p>
        <p style="margin-top: 10px; color: #aaa;">
            <a href="../index.php" style="color: #aaa;">Home</a> • 
            <a href="index.php" style="color: #aaa;">Products</a> • 
            <a href="../contact.php" style="color: #aaa;">Contact</a>
        </p>
    </footer>
</body>
</html>