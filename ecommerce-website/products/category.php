<?php
// ========================================
// FILE: products/category.php
// ========================================
require_once '../config.php';

$category_id = $_GET['id'] ?? null;

if (!$category_id) {
    redirect('index.php');
}

// Get category details
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND status = 'active'");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    redirect('index.php');
}

// Get all categories
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = $stmt->fetchAll();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get total products count for this category
$total_stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM products 
    WHERE category_id = ? AND status = 'active'
");
$total_stmt->execute([$category_id]);
$total_products = $total_stmt->fetch()['total'];
$total_pages = ceil($total_products / $per_page);

// Get products for this category
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.category_id = ? AND p.status = 'active'
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $category_id, PDO::PARAM_INT);
$stmt->bindValue(2, $per_page, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($category['name']) ?> - <?= SITE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        
        /* Navigation - Same as index.php */
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
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .category-header h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .category-header p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        /* Layout */
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
        
        /* Sidebar */
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
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .product-price {
            font-size: 24px;
            color: #28a745;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .product-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
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
        
        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
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
                <a href="index.php">Products</a>
                <?php if (isLoggedIn()): ?>
                    <?php if (isset($_SESSION['admin_id'])): ?>
                        <a href="../admin/index.php">Admin Panel</a>
                        <a href="../admin/logout.php">Logout</a>
                    <?php else: ?>
                        <a href="../cart/index.php">🛒 Cart</a>
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
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../index.php">Home</a> / 
            <a href="index.php">Products</a> / 
            <span><?= htmlspecialchars($category['name']) ?></span>
        </div>
        
        <!-- Category Header -->
        <div class="category-header">
            <h1><?= htmlspecialchars($category['name']) ?></h1>
            <p><?= htmlspecialchars($category['description'] ?? 'Browse all products in this category') ?></p>
        </div>
        
        <div class="layout">
            <!-- Categories Sidebar -->
            <div class="sidebar">
                <h3>All Categories</h3>
                <ul class="category-list">
                    <li class="category-item">
                        <a href="index.php">All Products</a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                    <li class="category-item <?= $cat['id'] == $category_id ? 'active' : '' ?>">
                        <a href="category.php?id=<?= $cat['id'] ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Main Products Grid -->
            <div>
                <h2 style="margin-bottom: 20px; color: #333;">
                    <?= htmlspecialchars($category['name']) ?> (<?= $total_products ?> products)
                </h2>
                
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <div class="icon">📦</div>
                        <h3>No products in this category yet</h3>
                        <p style="color: #666; margin-bottom: 20px;">
                            Check back later for new products.
                        </p>
                        <a href="index.php" class="btn">Browse All Products</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <a href="detail.php?id=<?= $product['id'] ?>">
                                <div class="product-image">
                                    <?php if ($product['image']): ?>
                                        <img src="../assets/uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php else: ?>
                                        📦
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                                    <div class="product-description">
                                        <?= substr(htmlspecialchars($product['description'] ?? ''), 0, 80) ?>...
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
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?id=<?= $category_id ?>&page=<?= $page - 1 ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?id=<?= $category_id ?>&page=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?id=<?= $category_id ?>&page=<?= $page + 1 ?>">Next →</a>
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
    </footer>
</body>
</html>