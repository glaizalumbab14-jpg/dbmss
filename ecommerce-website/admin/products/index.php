<?php

require_once '../../config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

// Get all products with category name
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
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
        }
        
        .btn:hover { background: #5568d3; }
        
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        
        table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            font-weight: 600;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid #eee;
        }
        
        .no-image {
            width: 60px;
            height: 60px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            font-size: 20px;
            color: #666;
        }
        
        .featured-badge {
            background: #ffc107;
            color: #212529;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active { 
            background: #d4edda; 
            color: #155724; 
        }
        .status-inactive { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .status-out_of_stock { 
            background: #fff3cd; 
            color: #856404; 
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            font-size: 24px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-info h3 {
            font-size: 24px;
            margin: 0;
            color: #333;
        }
        
        .stat-info p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .featured-product {
            background: #fff3cd;
        }
        
        .out-of-stock {
            opacity: 0.7;
        }
        
        .sku-code {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📦 Manage Products</h1>
        <div>
            <a href="../index.php">← Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <span>✓</span>
                <span><?= $_SESSION['success'] ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <span>⚠️</span>
                <span><?= $_SESSION['error'] ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Statistics -->
        <?php
        $total_products = count($products);
        $active_products = 0;
        $out_of_stock = 0;
        $featured_products = 0;
        
        foreach ($products as $product) {
            if ($product['status'] == 'active') $active_products++;
            if ($product['status'] == 'out_of_stock') $out_of_stock++;
            if ($product['featured'] == 1) $featured_products++;
        }
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <h3><?= $total_products ?></h3>
                    <p>Total Products</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3><?= $active_products ?></h3>
                    <p>Active Products</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <h3><?= $out_of_stock ?></h3>
                    <p>Out of Stock</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-info">
                    <h3><?= $featured_products ?></h3>
                    <p>Featured Products</p>
                </div>
            </div>
        </div>
        
        <div class="header">
            <h2>Product List</h2>
            <a href="add.php" class="btn btn-success">+ Add New Product</a>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr class="<?= $product['status'] == 'out_of_stock' ? 'out-of-stock' : '' ?> <?= $product['featured'] ? 'featured-product' : '' ?>">
                  <td>
    <?php if ($product['image']): ?>
        <?php 
        $image_path = '../../assets/uploads/products/' . htmlspecialchars($product['image']);
        $image_exists = file_exists($image_path);
        ?>
        <?php if ($image_exists): ?>
            <img src="<?= $image_path ?>" 
                 alt="<?= htmlspecialchars($product['name']) ?>" 
                 class="product-image">
        <?php else: ?>
            <div class="no-image" title="Image file not found">📦</div>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-image" title="No image uploaded">📦</div>
    <?php endif; ?>
</td>
                    <td>
                        <strong>#<?= $product['id'] ?></strong>
                        <?php if ($product['sku']): ?>
                            <div class="sku-code"><?= htmlspecialchars($product['sku']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                        <?php if ($product['featured']): ?>
                            <span class="featured-badge">FEATURED</span>
                        <?php endif; ?>
                        <?php if ($product['description']): ?>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                <?= substr(htmlspecialchars($product['description']), 0, 50) ?>...
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></td>
                    <td>
                        <strong style="color: #28a745;">$<?= number_format($product['price'], 2) ?></strong>
                    </td>
                    <td>
                        <?php if ($product['quantity'] > 10): ?>
                            <span style="color: #28a745;"><?= $product['quantity'] ?> in stock</span>
                        <?php elseif ($product['quantity'] > 0): ?>
                            <span style="color: #ffc107;"><?= $product['quantity'] ?> low stock</span>
                        <?php else: ?>
                            <span style="color: #dc3545;">Out of stock</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $product['status'] ?>">
                            <?= str_replace('_', ' ', ucfirst($product['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="edit.php?id=<?= $product['id'] ?>" class="btn" title="Edit Product">
                                Edit
                            </a>
                            <a href="delete.php?id=<?= $product['id'] ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Are you sure you want to delete product: <?= addslashes($product['name']) ?>? This action cannot be undone.')"
                               title="Delete Product">
                                Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <div style="font-size: 18px; color: #666; margin-bottom: 15px;">
                            📦 No products found
                        </div>
                        <p style="color: #999; margin-bottom: 20px;">Add your first product to get started</p>
                        <a href="add.php" class="btn btn-success">+ Add Your First Product</a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if (!empty($products)): ?>
        <div style="margin-top: 20px; text-align: center; color: #666; font-size: 14px;">
            Showing <?= count($products) ?> product<?= count($products) != 1 ? 's' : '' ?>
            • Last updated: <?= date('Y-m-d H:i:s') ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Confirm deletion with product name
        document.querySelectorAll('.btn-danger').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this product?\nThis action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>