<?php

require_once 'config.php';

// Get featured products
$stmt = $pdo->query("SELECT * FROM products WHERE featured = 1 AND status = 'active' LIMIT 8");
$featuredProducts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Home</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        
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
        
        .nav-links .btn-primary {
            background: white;
            color: #667eea;
            font-weight: bold;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .hero-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 40px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 18px;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: white;
            color: #667eea;
        }
        
        .btn-primary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .section-title {
            font-size: 32px;
            margin-bottom: 30px;
            text-align: center;
            color: #333;
            position: relative;
            padding-bottom: 15px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .featured-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #212529;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
            line-height: 1.3;
            height: 46px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
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
            height: 42px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-stock {
            font-size: 12px;
            margin-bottom: 15px;
            padding: 5px 10px;
            border-radius: 3px;
            display: inline-block;
        }
        
        .in-stock { 
            background: #d4edda; 
            color: #155724;
        }
        
        .low-stock { 
            background: #fff3cd; 
            color: #856404;
        }
        
        .out-of-stock { 
            background: #f8d7da; 
            color: #721c24;
        }
        
        .product-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .product-btn:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653e8b 100%);
            transform: translateY(-2px);
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
        }
        
        /* User Info */
        .user-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            border-left: 4px solid #1976d2;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .user-info strong {
            color: #1976d2;
        }
        
        /* Categories Section */
        .categories-section {
            margin: 60px 0;
            padding: 40px 0;
            background: #f8f9fa;
            border-radius: 15px;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .category-card {
            background: white;
            padding: 30px 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .category-icon {
            font-size: 40px;
            margin-bottom: 15px;
            display: block;
        }
        
        /* Features Section */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin: 60px 0;
        }
        
        .feature-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .feature-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: #667eea;
        }
        
        /* No Products */
        .no-products {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            margin: 40px 0;
        }
        
        .no-products .icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        /* Footer */
        .footer {
            background: #333;
            color: white;
            padding: 50px 20px 30px;
            margin-top: 60px;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }
        
        .footer-section h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #fff;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links a {
            color: #aaa;
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: #fff;
        }
        
        .footer-bottom {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #444;
            color: #aaa;
            font-size: 14px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 36px;
            }
            
            .hero p {
                font-size: 18px;
            }
            
            .nav-links {
                gap: 10px;
            }
            
            .nav-links a {
                padding: 6px 12px;
                font-size: 14px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
        
        @media (max-width: 480px) {
            .hero {
                padding: 60px 20px;
            }
            
            .hero h1 {
                font-size: 28px;
            }
            
            .section-title {
                font-size: 24px;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">🛒 <?= SITE_NAME ?></a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="products/index.php">Products</a>
                
                <?php if (isUserLoggedIn()): ?>
                    <!-- Show user menu -->
                    <a href="cart/index.php">Cart</a>
                    <a href="user/dashboard.php">My Account</a>
                    <a href="auth/logout.php">Logout</a>
                <?php elseif (isAdminLoggedIn()): ?>
                    <!-- Show admin menu (if somehow admin visits main site) -->
                    <a href="admin/index.php">Admin Panel</a>
                    <a href="admin/logout.php">Logout</a>
                <?php else: ?>
                    <!-- Show guest menu -->
                    <a href="auth/login.php">Login</a>
                    <a href="auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero">
        <h1>Welcome to <?= SITE_NAME ?></h1>
        <p>Your one-stop shop for quality products at great prices!</p>
        <div class="hero-buttons">
            <?php if (!isLoggedIn()): ?>
                <a href="auth/register.php" class="btn btn-primary">Get Started</a>
            <?php endif; ?>
            <a href="products/index.php" class="btn btn-outline">Browse All Products</a>
        </div>
    </section>
    
    <!-- Main Content -->
    <div class="container">
        <?php if (isLoggedIn()): ?>
            <div class="user-info">
                <?php if (isAdmin()): ?>
                    ✓ Logged in as <strong>Admin</strong> (<?= $_SESSION['username'] ?>)
                <?php else: ?>
                    ✓ Welcome back, <strong><?= $_SESSION['username'] ?></strong>!
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">🌟 Featured Products</h2>
        
        <?php if (empty($featuredProducts)): ?>
            <div class="no-products">
                <div class="icon">📦</div>
                <h3>No featured products available</h3>
                <p style="color: #666; margin-bottom: 20px;">
                    Check back later for new featured products!
                </p>
                <a href="products/index.php" class="btn btn-primary" style="display: inline-block; width: auto;">Browse All Products</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <a href="products/detail.php?id=<?= $product['id'] ?>" class="product-card">
                        <div class="product-image">
                            <?php if (!empty($product['image']) && file_exists("assets/uploads/products/" . $product['image'])): ?>
                                <img src="assets/uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 48px; color: #ccc; background: #f8f9fa;">📦</div>
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 48px; color: #ccc; background: #f8f9fa;">📦</div>
                            <?php endif; ?>
                            <div class="featured-badge">Featured</div>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                            <div class="product-description">
                                <?= substr(htmlspecialchars($product['description'] ?? ''), 0, 100) ?>...
                            </div>
                            <div class="product-stock <?= 
                                ($product['quantity'] > 10) ? 'in-stock' : 
                                (($product['quantity'] > 0) ? 'low-stock' : 'out-of-stock')
                            ?>">
                                <?php if ($product['quantity'] > 10): ?>
                                    ✅ In Stock
                                <?php elseif ($product['quantity'] > 0): ?>
                                    ⚠️ Low Stock
                                <?php else: ?>
                                    ❌ Out of Stock
                                <?php endif; ?>
                            </div>
                            <div class="product-btn">View Details</div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Categories Section -->
        <?php
        // Get categories for display
        $categoriesStmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name LIMIT 6");
        $categories = $categoriesStmt->fetchAll();
        
        if (!empty($categories)):
        ?>
        <div class="categories-section">
            <h2 class="section-title">🛍️ Shop by Category</h2>
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                    <a href="products/category.php?id=<?= $category['id'] ?>" class="category-card">
                        <span class="category-icon">
                            <?php 
                            // Different icons for different categories
                            $icons = ['📱', '👕', '👟', '💻', '📚', '🏠', '🎮', '🍽️'];
                            echo $icons[array_rand($icons)] ?? '📦';
                            ?>
                        </span>
                        <h3 style="margin-bottom: 10px; font-size: 16px;"><?= htmlspecialchars($category['name']) ?></h3>
                        <p style="font-size: 14px; color: #666;">Browse products</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Features Section -->
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">🚚</div>
                <h3>Free Shipping</h3>
                <p>On orders over $50</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Payment</h3>
                <p>100% secure transactions</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔄</div>
                <h3>Easy Returns</h3>
                <p>30-day return policy</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📞</div>
                <h3>24/7 Support</h3>
                <p>Dedicated customer service</p>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><?= SITE_NAME ?></h3>
                <p style="color: #aaa; line-height: 1.6;">
                    Your trusted online marketplace for quality products at affordable prices.
                </p>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products/index.php">Products</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul class="footer-links">
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="shipping.php">Shipping Info</a></li>
                    <li><a href="returns.php">Returns Policy</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Contact Us</h3>
                <ul class="footer-links">
                    <li><a href="mailto:info@<?= strtolower(str_replace(' ', '', SITE_NAME)) ?>.com">Email</a></li>
                    <li><a href="tel:+1234567890">Phone</a></li>
                    <li><a href="#">Live Chat</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2024 <?= SITE_NAME ?>. All rights reserved.</p>
            <p style="margin-top: 5px;">
                Designed with ❤️ for eCommerce
            </p>
        </div>
    </footer>
</body>
</html>