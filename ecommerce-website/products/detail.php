<?php
// ========================================
// FILE: products/detail.php
// ========================================
require_once '../config.php';

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    redirect('index.php');
}

// Get product details with category information
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.id as category_id 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('index.php');
}

// Get related products (same category)
$related_stmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND id != ? AND status = 'active'
    ORDER BY RAND() 
    LIMIT 4
");
$related_stmt->execute([$product['category_id'], $product_id]);
$related_products = $related_stmt->fetchAll();

// Check if reviews table exists
$table_exists = $pdo->query("SHOW TABLES LIKE 'reviews'")->rowCount() > 0;

if ($table_exists) {
    // Get product reviews
    $reviews_stmt = $pdo->prepare("
        SELECT r.*, u.username, u.profile_image 
        FROM reviews r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.product_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $reviews_stmt->execute([$product_id]);
    $reviews = $reviews_stmt->fetchAll();

    // Calculate average rating
    $avg_rating_stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE product_id = ? AND status = 'approved'");
    $avg_rating_stmt->execute([$product_id]);
    $avg_rating_result = $avg_rating_stmt->fetch();
    $avg_rating = $avg_rating_result['avg_rating'] ?? 0;
} else {
    $reviews = [];
    $avg_rating = 0;
}

// Increment view count (if column exists)
$check_column = $pdo->query("SHOW COLUMNS FROM products LIKE 'views'")->rowCount();
if ($check_column > 0) {
    $view_stmt = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
    $view_stmt->execute([$product_id]);
}

// Set default values for missing fields
$product['brand'] = $product['brand'] ?? 'AURORA';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - <?= SITE_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
        
        /* Navigation - Same as index.php */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
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
        
        .cart-btn {
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
        
        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 30px;
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
        
        /* Product Detail */
        .product-detail {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 60px;
        }
        
        .product-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding: 40px;
        }
        
        @media (max-width: 768px) {
            .product-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Product Images */
        .product-images {
            position: relative;
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .main-image:hover img {
            transform: scale(1.05);
        }
        
        .image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 120px;
            color: #dee2e6;
            background: #f8f9fa;
        }
        
        .featured-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ffc107;
            color: #212529;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            z-index: 1;
        }
        
        .stock-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #28a745;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            z-index: 1;
        }
        
        .stock-badge.low { background: #ffc107; }
        .stock-badge.out { background: #dc3545; }
        
        /* Product Info */
        .product-info {
            padding: 20px 0;
        }
        
        .category-tag {
            display: inline-block;
            background: #e9ecef;
            color: #667eea;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-bottom: 15px;
            text-decoration: none;
        }
        
        .category-tag:hover {
            background: #667eea;
            color: white;
        }
        
        .product-title {
            font-size: 36px;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .product-price {
            font-size: 42px;
            color: #28a745;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .product-rating {
            margin-bottom: 25px;
        }
        
        .stars {
            color: #ffc107;
            font-size: 20px;
            margin-right: 10px;
        }
        
        .rating-count {
            color: #666;
            font-size: 14px;
        }
        
        .product-description {
            font-size: 16px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 30px;
        }
        
        /* Specifications */
        .specs {
            margin-bottom: 30px;
        }
        
        .specs h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .spec-item {
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .spec-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .spec-value {
            color: #333;
            font-weight: 500;
        }
        
        /* Quantity and Actions */
        .quantity-selector {
            margin-bottom: 25px;
        }
        
        .quantity-selector label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 500;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quantity-btn:hover {
            background: #e9ecef;
        }
        
        .quantity-input {
            width: 80px;
            height: 40px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 16px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Additional Info */
        .additional-info {
            font-size: 14px;
            color: #666;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-item i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        /* Related Products */
        .related-section {
            margin-bottom: 60px;
        }
        
        .section-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .related-product {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .related-product:hover {
            transform: translateY(-5px);
        }
        
        .related-product-image {
            width: 100%;
            height: 180px;
            background: #f8f9fa;
        }
        
        .related-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-product-info {
            padding: 20px;
        }
        
        .related-product-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .related-product-price {
            color: #28a745;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        /* Reviews Section */
        .reviews-section {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 60px;
        }
        
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .average-rating {
            text-align: center;
        }
        
        .rating-large {
            font-size: 48px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stars-large {
            color: #ffc107;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .write-review-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .write-review-btn:hover {
            background: #5568d3;
        }
        
        .review-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 20px;
        }
        
        .review-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
        }
        
        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .reviewer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .reviewer-info {
            flex: 1;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .review-date {
            font-size: 12px;
            color: #999;
        }
        
        .review-stars {
            color: #ffc107;
        }
        
        .review-comment {
            color: #555;
            line-height: 1.6;
        }
        
        /* Modal for Review Form */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        
        .star-rating input {
            display: none;
        }
        
        .star-rating label {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
            padding: 5px;
        }
        
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffc107;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
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
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../index.php">Home</a> / 
            <a href="index.php">Products</a> / 
            <?php if ($product['category_name']): ?>
                <a href="category.php?id=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a> /
            <?php endif; ?>
            <span><?= htmlspecialchars($product['name']) ?></span>
        </div>
        
        <!-- Product Detail -->
        <div class="product-detail">
            <div class="product-content">
                <!-- Product Images -->
                <div class="product-images">
                    <div class="main-image">
                        <?php if (!empty($product['image'])): ?>
                            <img src="../assets/uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 id="main-product-image">
                        <?php else: ?>
                            <div class="image-placeholder">📦</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($product['featured']): ?>
                        <div class="featured-badge">⭐ FEATURED</div>
                    <?php endif; ?>
                    
                    <div class="stock-badge <?= 
                        ($product['quantity'] > 10) ? '' : 
                        (($product['quantity'] > 0) ? 'low' : 'out')
                    ?>">
                        <?php if ($product['quantity'] > 10): ?>
                            ✅ In Stock
                        <?php elseif ($product['quantity'] > 0): ?>
                            ⚠️ Low Stock
                        <?php else: ?>
                            ❌ Out of Stock
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Product Info -->
                <div class="product-info">
                    <?php if ($product['category_name']): ?>
                        <a href="category.php?id=<?= $product['category_id'] ?>" class="category-tag">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </a>
                    <?php endif; ?>
                    
                    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                    
                    <div class="product-rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= floor($avg_rating)): ?>
                                    ★
                                <?php else: ?>
                                    ☆
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-count">
                            <?= number_format($avg_rating, 1) ?> (<?= count($reviews) ?> reviews)
                        </span>
                    </div>
                    
                    <div class="product-description">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </div>
                    
                    <!-- Specifications -->
                    <div class="specs">
                        <h3>Product Specifications</h3>
                        <div class="specs-grid">
                            <div class="spec-item">
                                <div class="spec-label">SKU</div>
                                <div class="spec-value"><?= htmlspecialchars($product['sku']) ?></div>
                            </div>
                            <div class="spec-item">
                                <div class="spec-label">Brand</div>
                                <div class="spec-value"><?= htmlspecialchars($product['brand']) ?></div>
                            </div>
                            <?php if (!empty($product['weight']) && $product['weight'] != 'N/A'): ?>
                            <div class="spec-item">
                                <div class="spec-label">Weight</div>
                                <div class="spec-value"><?= htmlspecialchars($product['weight']) ?> kg</div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($product['dimensions']) && $product['dimensions'] != 'N/A'): ?>
                            <div class="spec-item">
                                <div class="spec-label">Dimensions</div>
                                <div class="spec-value"><?= htmlspecialchars($product['dimensions']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quantity Selector -->
                    <div class="quantity-selector">
                        <label for="quantity">Quantity</label>
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="decrementQuantity()">-</button>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?= $product['quantity'] ?>">
                            <button class="quantity-btn" onclick="incrementQuantity()">+</button>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($product['quantity'] > 0): ?>
                            <a href="../cart/add.php?id=<?= $product['id'] ?>" class="btn btn-primary" id="add-to-cart-btn">
                                🛒 Add to Cart
                            </a>
                            <a href="../checkout/index.php?id=<?= $product['id'] ?>" class="btn btn-secondary">
                                ⚡ Buy Now
                            </a>
                        <?php else: ?>
                            <button class="btn" disabled>Out of Stock</button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Additional Info -->
                    <div class="additional-info">
                        <div class="info-item">📦 Free shipping on orders over $50</div>
                        <div class="info-item">🔄 30-day return policy</div>
                        <div class="info-item">📞 24/7 customer support</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
        <div class="related-section">
            <h2 class="section-title">Related Products</h2>
            <div class="related-grid">
                <?php foreach ($related_products as $related): ?>
                <a href="detail.php?id=<?= $related['id'] ?>" class="related-product">
                    <div class="related-product-image">
                        <?php if (!empty($related['image'])): ?>
                            <img src="../assets/uploads/products/<?= htmlspecialchars($related['image']) ?>" 
                                 alt="<?= htmlspecialchars($related['name']) ?>">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f8f9fa; color: #ccc; font-size: 48px;">📦</div>
                        <?php endif; ?>
                    </div>
                    <div class="related-product-info">
                        <div class="related-product-name"><?= htmlspecialchars($related['name']) ?></div>
                        <div class="related-product-price">$<?= number_format($related['price'], 2) ?></div>
                        <div style="text-align: center; padding: 8px 0;">
                            <span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                                View Details
                            </span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Reviews Section -->
        <div class="reviews-section">
            <div class="reviews-header">
                <div class="average-rating">
                    <div class="rating-large"><?= number_format($avg_rating, 1) ?></div>
                    <div class="stars-large">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= floor($avg_rating)): ?>
                                ★
                            <?php else: ?>
                                ☆
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div style="color: #666; font-size: 14px;">Based on <?= count($reviews) ?> reviews</div>
                </div>
                
                <?php if (isLoggedIn() && !isset($_SESSION['admin_id'])): ?>
                    <button class="write-review-btn" onclick="openReviewModal()">Write a Review</button>
                <?php endif; ?>
            </div>
            
            <div class="review-list">
                <?php if (empty($reviews)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📝</div>
                        <h3>No reviews yet</h3>
                        <p>Be the first to review this product!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="reviewer-avatar">
                                <?= strtoupper(substr($review['username'], 0, 1)) ?>
                            </div>
                            <div class="reviewer-info">
                                <div class="reviewer-name"><?= htmlspecialchars($review['username']) ?></div>
                                <div class="review-date"><?= date('F j, Y', strtotime($review['created_at'])) ?></div>
                            </div>
                            <div class="review-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $review['rating']): ?>
                                        ★
                                    <?php else: ?>
                                        ☆
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="review-comment">
                            <?= nl2br(htmlspecialchars($review['comment'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div class="modal" id="reviewModal">
        <div class="modal-content">
            <h3>Write a Review</h3>
            <form id="reviewForm" action="../reviews/submit.php" method="POST">
                <input type="hidden" name="product_id" value="<?= $product_id ?>">
                
                <div class="form-group">
                    <label>Rating</label>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5">
                        <label for="star5">★</label>
                        <input type="radio" id="star4" name="rating" value="4">
                        <label for="star4">★</label>
                        <input type="radio" id="star3" name="rating" value="3">
                        <label for="star3">★</label>
                        <input type="radio" id="star2" name="rating" value="2">
                        <label for="star2">★</label>
                        <input type="radio" id="star1" name="rating" value="1">
                        <label for="star1">★</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="comment">Your Review</label>
                    <textarea name="comment" id="comment" rows="4" placeholder="Share your experience with this product..." required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Footer -->
    <footer style="background: #333; color: white; text-align: center; padding: 30px 20px; margin-top: 60px;">
        <p>&copy; 2024 <?= SITE_NAME ?>. All rights reserved.</p>
    </footer>
    
    <script>
        // Quantity controls
        function incrementQuantity() {
            const input = document.getElementById('quantity');
            const max = parseInt(input.max);
            if (input.value < max) {
                input.value = parseInt(input.value) + 1;
            }
        }
        
        function decrementQuantity() {
            const input = document.getElementById('quantity');
            if (input.value > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }
        
        // Update add to cart URL with quantity
        document.getElementById('quantity').addEventListener('change', function() {
            const quantity = this.value;
            const cartBtn = document.getElementById('add-to-cart-btn');
            if (cartBtn) {
                const baseUrl = cartBtn.href.split('?')[0];
                cartBtn.href = baseUrl + '?id=<?= $product_id ?>&quantity=' + quantity;
            }
        });
        
        // Review modal
        function openReviewModal() {
            document.getElementById('reviewModal').style.display = 'flex';
        }
        
        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reviewModal');
            if (event.target == modal) {
                closeReviewModal();
            }
        }
    </script>
</body>
</html>