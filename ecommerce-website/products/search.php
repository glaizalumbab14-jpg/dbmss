<?php
// ========================================
// FILE: products/search.php
// ========================================
require_once '../config.php';

$search_query = trim($_GET['q'] ?? '');
$category_filter = $_GET['category'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'relevance';

if (empty($search_query)) {
    redirect('index.php');
}

// Get all categories for filter
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = $stmt->fetchAll();

// Build search query - Fix: Use exact word matching instead of too broad LIKE
$where_conditions = ["p.status = 'active'"];
$params = [];

if (!empty($search_query)) {
    // Split search query into words
    $search_words = preg_split('/\s+/', $search_query);
    $search_conditions = [];
    
    foreach ($search_words as $word) {
        if (strlen($word) > 1) { // Only search for words with at least 2 characters
            $search_conditions[] = "p.name LIKE ?";
            $search_conditions[] = "p.description LIKE ?";
            $params[] = "%$word%";
            $params[] = "%$word%";
        }
    }
    
    if (!empty($search_conditions)) {
        $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
    }
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($min_price) && is_numeric($min_price)) {
    $where_conditions[] = "p.price >= ?";
    $params[] = floatval($min_price);
}

if (!empty($max_price) && is_numeric($max_price)) {
    $where_conditions[] = "p.price <= ?";
    $params[] = floatval($max_price);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : 'WHERE p.status = "active"';

// Build ORDER BY clause
$order_by = match($sort) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'newest' => 'p.created_at DESC',
    default => 'p.name ASC' // Default alphabetical order
};

// Get total count
$count_sql = "
    SELECT COUNT(*) as total 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause
";

try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetch()['total'];
} catch (Exception $e) {
    $total_products = 0;
    error_log("Search error: " . $e->getMessage());
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_products / $per_page);

// Get products with pagination
$sql = "
    SELECT p.*, c.name as category_name
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause
    ORDER BY $order_by
    LIMIT ? OFFSET ?
";

// Add pagination parameters
$query_params = array_merge($params, [$per_page, $offset]);

$products = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query_params);
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $products = [];
    error_log("Product search error: " . $e->getMessage());
}

// Get product counts per category for the filter sidebar
$category_counts = [];
foreach ($categories as $category) {
    try {
        $count_sql = "
            SELECT COUNT(*) as count 
            FROM products p 
            WHERE p.category_id = ? 
            AND p.status = 'active'
        ";
        
        // Add search conditions if there's a search query
        if (!empty($search_query)) {
            $search_words = preg_split('/\s+/', $search_query);
            $search_conditions = [];
            $count_params = [$category['id']];
            
            foreach ($search_words as $word) {
                if (strlen($word) > 1) {
                    $search_conditions[] = "p.name LIKE ?";
                    $search_conditions[] = "p.description LIKE ?";
                    $count_params[] = "%$word%";
                    $count_params[] = "%$word%";
                }
            }
            
            if (!empty($search_conditions)) {
                $count_sql .= " AND (" . implode(" OR ", $search_conditions) . ")";
            }
            
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($count_params);
        } else {
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute([$category['id']]);
        }
        
        $category_counts[$category['id']] = $count_stmt->fetch()['count'];
    } catch (Exception $e) {
        $category_counts[$category['id']] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results for "<?= htmlspecialchars($search_query) ?>" - <?= SITE_NAME ?></title>
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
        
        /* Search Header */
        .search-header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .search-header p {
            color: #666;
            font-size: 16px;
        }
        
        .search-query {
            color: #667eea;
            font-weight: bold;
        }
        
        /* Layout */
        .search-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
        }
        
        @media (max-width: 992px) {
            .search-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Filters Sidebar */
        .filters-sidebar {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filters-header h3 {
            color: #333;
        }
        
        .clear-filters {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .clear-filters:hover {
            text-decoration: underline;
        }
        
        .filter-group {
            margin-bottom: 25px;
        }
        
        .filter-group h4 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .category-list {
            list-style: none;
        }
        
        .category-item {
            margin-bottom: 8px;
        }
        
        .category-item a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #333;
            text-decoration: none;
            padding: 8px 12px;
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
        
        .category-count {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        
        .category-item.active .category-count {
            background: rgba(255,255,255,0.3);
        }
        
        /* Price Filter - Fixed layout */
.filter-group {
    margin-bottom: 25px;
}

.filter-group h4 {
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.price-filter-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.price-inputs-container {
    display: flex;
    gap: 10px;
}

.price-input-wrapper {
    flex: 1;
    position: relative;
}

.price-input-wrapper label {
    position: absolute;
    top: -20px;
    left: 0;
    font-size: 12px;
    color: #666;
    background: white;
    padding: 0 5px;
    margin-left: 8px;
}

.price-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    box-sizing: border-box;
}

.price-input::placeholder {
    color: #999;
    opacity: 0.7;
}

.price-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}

.apply-price {
    width: 100%;
    padding: 12px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.apply-price:hover {
    background: #5568d3;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.apply-price:active {
    transform: translateY(0);
}

/* For smaller screens */
@media (max-width: 480px) {
    .price-inputs-container {
        flex-direction: column;
        gap: 15px;
    }
    
    .price-input {
        padding: 12px;
    }
}
        /* Sort Options */
        .sort-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .sort-btn {
            padding: 8px 15px;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            font-size: 14px;
        }
        
        .sort-btn:hover {
            background: #e9ecef;
        }
        
        .sort-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        /* Results Info */
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            position: relative;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: #f0f0f0;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-rating {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            color: #ffc107;
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
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #999;
            margin-bottom: 15px;
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
        
        .btn-secondary:hover { background: #5a6268; }
        
        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            grid-column: 1 / -1;
        }
        
        .no-results .icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .no-results h3 {
            color: #666;
            margin-bottom: 15px;
        }
        
        .search-suggestions {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .search-suggestions h4 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .suggestion-list {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        
        .suggestion-list a {
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            color: #667eea;
            text-decoration: none;
            border: 1px solid #dee2e6;
            font-size: 14px;
        }
        
        .suggestion-list a:hover {
            background: #667eea;
            color: white;
        }
        
        /* Search Tips */
        .search-tips {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            font-size: 14px;
            color: #856404;
        }
        
        .search-tips h5 {
            margin-bottom: 10px;
            color: #856404;
        }
        
        .search-tips ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .search-tips li {
            margin-bottom: 5px;
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
        
        /* Footer */
        footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 60px;
        }
        
        /* Loading Animation */
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        <!-- Search Header -->
        <div class="search-header">
            <h1>Search Results</h1>
            <p>
                <?php if ($total_products > 0): ?>
                    Found <strong><?= $total_products ?></strong> products for 
                    "<span class="search-query"><?= htmlspecialchars($search_query) ?></span>"
                <?php else: ?>
                    No products found for "<span class="search-query"><?= htmlspecialchars($search_query) ?></span>"
                <?php endif; ?>
            </p>
        </div>
        
        <div class="search-layout">
            <!-- Filters Sidebar -->
            <div class="filters-sidebar">
                <div class="filters-header">
                    <h3>Filters</h3>
                    <a href="search.php?q=<?= urlencode($search_query) ?>" class="clear-filters">
                        Clear All
                    </a>
                </div>
                
                <!-- Categories Filter -->
                <div class="filter-group">
                    <h4>Categories</h4>
                    <ul class="category-list">
                        <li class="category-item <?= empty($category_filter) ? 'active' : '' ?>">
                            <a href="search.php?q=<?= urlencode($search_query) ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=<?= $sort ?>">
                                All Categories
                                <span class="category-count"><?= $total_products ?></span>
                            </a>
                        </li>
                        <?php foreach ($categories as $category): ?>
                        <?php 
                            $category_count = $category_counts[$category['id']] ?? 0;
                        ?>
                        <?php if ($category_count > 0): ?>
                        <li class="category-item <?= $category_filter == $category['id'] ? 'active' : '' ?>">
                            <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category['id'] ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=<?= $sort ?>">
                                <?= htmlspecialchars($category['name']) ?>
                                <span class="category-count"><?= $category_count ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Price Filter - FIXED PLACEHOLDER TEXT -->
                <div class="filter-group">
                    <h4>Price Range</h4>
                    <form method="GET" action="search.php" id="priceFilterForm">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search_query) ?>">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        
                        <div class="price-range">
                            <input type="number" name="min_price" class="price-input" 
                                   placeholder="Minimum Price" value="<?= htmlspecialchars($min_price) ?>" 
                                   step="0.01" min="0" oninput="validatePrice(this)">
                            <input type="number" name="max_price" class="price-input" 
                                   placeholder="Maximum Price" value="<?= htmlspecialchars($max_price) ?>" 
                                   step="0.01" min="0" oninput="validatePrice(this)">
                        </div>
                        <button type="submit" class="apply-price">Apply Price Filter</button>
                    </form>
                </div>
                
                <!-- Sort Options -->
                <div class="filter-group">
                    <h4>Sort By</h4>
                    <div class="sort-options">
                        <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=relevance" 
                           class="sort-btn <?= $sort == 'relevance' ? 'active' : '' ?>">
                            Relevance
                        </a>
                        <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=price_asc" 
                           class="sort-btn <?= $sort == 'price_asc' ? 'active' : '' ?>">
                            Price: Low to High
                        </a>
                        <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=price_desc" 
                           class="sort-btn <?= $sort == 'price_desc' ? 'active' : '' ?>">
                            Price: High to Low
                        </a>
                        <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=newest" 
                           class="sort-btn <?= $sort == 'newest' ? 'active' : '' ?>">
                            Newest
                        </a>
                    </div>
                </div>
                
                <!-- Search Tips -->
                <?php if ($total_products == 0 && !empty($search_query)): ?>
                <div class="search-tips">
                    <h5>Search Tips:</h5>
                    <ul>
                        <li>Try using more specific keywords</li>
                        <li>Check your spelling</li>
                        <li>Use fewer keywords for broader results</li>
                        <li>Try related terms or synonyms</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Main Results -->
            <div>
                <?php if ($total_products > 0): ?>
                    <div class="results-info">
                        <div>
                            Showing <?= count($products) ?> of <?= $total_products ?> products
                        </div>
                        <div>
                            Sorted by: 
                            <strong>
                                <?= match($sort) {
                                    'price_asc' => 'Price: Low to High',
                                    'price_desc' => 'Price: High to Low',
                                    'newest' => 'Newest First',
                                    default => 'Relevance'
                                } ?>
                            </strong>
                        </div>
                    </div>
                    
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                        <a href="detail.php?id=<?= $product['id'] ?>" class="product-card">
                            <div class="product-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="../assets/uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                    <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 48px; color: #ccc; background: #f0f0f0;">📦</div>
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f0f0; color: #ccc; font-size: 48px;">📦</div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['featured'])): ?>
                                    <div style="position: absolute; top: 10px; left: 10px; background: #ffc107; color: #212529; padding: 5px 10px; border-radius: 3px; font-size: 12px; font-weight: bold;">
                                        ⭐
                                    </div>
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
                                <div class="product-meta">
                                    <span class="<?= 
                                        ($product['quantity'] > 10) ? 'in-stock' : 
                                        (($product['quantity'] > 0) ? 'low-stock' : 'out-of-stock')
                                    ?>" style="color: <?= 
                                        ($product['quantity'] > 10) ? '#28a745' : 
                                        (($product['quantity'] > 0) ? '#ffc107' : '#dc3545')
                                    ?>">
                                        <?php if ($product['quantity'] > 10): ?>
                                            ✅ In Stock
                                        <?php elseif ($product['quantity'] > 0): ?>
                                            ⚠️ Low Stock
                                        <?php else: ?>
                                            ❌ Out of Stock
                                        <?php endif; ?>
                                    </span>
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
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=<?= $sort ?>&page=<?= $page - 1 ?>">
                                ← Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php 
                        // Show limited pagination links
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="search.php?q=' . urlencode($search_query) . '&category=' . $category_filter . '&min_price=' . $min_price . '&max_price=' . $max_price . '&sort=' . $sort . '&page=1">1</a>';
                            if ($start_page > 2) echo '<span>...</span>';
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=<?= $sort ?>&page=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1) echo '<span>...</span>'; ?>
                            <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=<?= $sort ?>&page=<?= $total_pages ?>">
                                <?= $total_pages ?>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="search.php?q=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&sort=<?= $sort ?>&page=<?= $page + 1 ?>">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <div class="icon">🔍</div>
                        <h3>No products found for "<?= htmlspecialchars($search_query) ?>"</h3>
                        <p style="color: #666; margin-bottom: 20px;">
                            We couldn't find any products matching your search. Try using different keywords or browse our categories.
                        </p>
                        <a href="index.php" class="btn" style="display: inline-block; width: auto; margin-bottom: 20px;">Browse All Products</a>
                        
                        <?php 
                        // Get popular search suggestions from existing products
                        $suggestions_stmt = $pdo->query("
                            SELECT DISTINCT name 
                            FROM products 
                            WHERE status = 'active' 
                            AND LENGTH(name) > 3
                            ORDER BY RAND() 
                            LIMIT 6
                        ");
                        $suggestions = $suggestions_stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (!empty($suggestions)): 
                        ?>
                        <div class="search-suggestions">
                            <h4>Try searching for:</h4>
                            <ul class="suggestion-list">
                                <?php foreach ($suggestions as $suggestion): 
                                    // Extract first meaningful word from product name
                                    $words = preg_split('/\s+/', $suggestion);
                                    $first_word = '';
                                    foreach ($words as $word) {
                                        if (strlen($word) > 2) {
                                            $first_word = $word;
                                            break;
                                        }
                                    }
                                    if (!empty($first_word)):
                                ?>
                                <li><a href="search.php?q=<?= urlencode($first_word) ?>"><?= htmlspecialchars($first_word) ?></a></li>
                                <?php endif; endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer>
        <p>&copy; 2024 <?= SITE_NAME ?>. All rights reserved.</p>
        <p style="margin-top: 10px; color: #aaa;">
            <a href="../index.php" style="color: #aaa;">Home</a> • 
            <a href="index.php" style="color: #aaa;">Products</a> • 
            <a href="../contact.php" style="color: #aaa;">Contact</a>
        </p>
    </footer>
    
    <script>
        // Price validation
        function validatePrice(input) {
            const value = parseFloat(input.value);
            if (value < 0) {
                input.value = 0;
            }
            
            const minPrice = document.querySelector('input[name="min_price"]');
            const maxPrice = document.querySelector('input[name="max_price"]');
            
            if (minPrice.value && maxPrice.value && parseFloat(minPrice.value) > parseFloat(maxPrice.value)) {
                input.style.borderColor = '#dc3545';
            } else {
                input.style.borderColor = '#dee2e6';
            }
        }
        
        // Form validation
        document.getElementById('priceFilterForm').addEventListener('submit', function(e) {
            const minPrice = document.querySelector('input[name="min_price"]');
            const maxPrice = document.querySelector('input[name="max_price"]');
            
            if (minPrice.value && maxPrice.value && parseFloat(minPrice.value) > parseFloat(maxPrice.value)) {
                e.preventDefault();
                alert('Minimum price cannot be greater than maximum price.');
                minPrice.focus();
                return false;
            }
            
            return true;
        });
        
        // Show loading state when filtering
        document.querySelectorAll('.sort-btn, .category-item a, .apply-price').forEach(link => {
            link.addEventListener('click', function() {
                // Show loading indicator for main results area
                const resultsArea = document.querySelector('.search-layout > div:last-child');
                if (resultsArea) {
                    resultsArea.innerHTML = `
                        <div class="loading" style="display: block;">
                            <div class="spinner"></div>
                            <p>Loading search results...</p>
                        </div>
                    `;
                }
            });
        });
    </script>
</body>
</html>