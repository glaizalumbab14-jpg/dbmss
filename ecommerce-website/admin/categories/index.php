<?php

require_once '../../config.php';

if (!isAdminLoggedIn()) {
    redirect('../login.php');
}
// Get all categories with product count
$stmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
$categories = $stmt->fetchAll();

// Handle form submission for adding new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $error = 'Category name is required';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $status]);
            $_SESSION['success'] = 'Category added successfully';
            redirect('index.php');
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Failed to add category: ' . $e->getMessage();
            redirect('index.php');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin</title>
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
            display: inline-block;
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
        
        .product-count {
            background: #e9ecef;
            color: #495057;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .add-category-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📂 Manage Categories</h1>
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
        $total_categories = count($categories);
        $active_categories = 0;
        $total_products = 0;
        
        foreach ($categories as $category) {
            if ($category['status'] == 'active') $active_categories++;
            $total_products += $category['product_count'];
        }
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon">📂</div>
                <div class="stat-info">
                    <h3><?= $total_categories ?></h3>
                    <p>Total Categories</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3><?= $active_categories ?></h3>
                    <p>Active Categories</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <h3><?= $total_products ?></h3>
                    <p>Total Products</p>
                </div>
            </div>
        </div>
        
        <!-- Add Category Form -->
        <div class="add-category-form">
            <h2 style="margin-bottom: 20px;">Add New Category</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Category Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Optional description for this category..."></textarea>
                </div>
                
                <button type="submit" name="add_category" class="btn btn-success">Add Category</button>
            </form>
        </div>
        
        <div class="header">
            <h2>Category List</h2>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                <tr>
                    <td>#<?= $category['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($category['name']) ?></strong>
                        <?php if ($category['description']): ?>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                <?= substr(htmlspecialchars($category['description']), 0, 50) ?>...
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($category['description'] ?? 'No description') ?></td>
                    <td>
                        <span class="product-count"><?= $category['product_count'] ?> product<?= $category['product_count'] != 1 ? 's' : '' ?></span>
                        <?php if ($category['product_count'] > 0): ?>
                            <div style="font-size: 11px; color: #666; margin-top: 5px;">
                                <a href="../products/index.php?category=<?= $category['id'] ?>" style="color: #667eea;">
                                    View products →
                                </a>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $category['status'] ?>">
                            <?= ucfirst($category['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="edit.php?id=<?= $category['id'] ?>" class="btn" title="Edit Category">
                                Edit
                            </a>
                            <a href="delete.php?id=<?= $category['id'] ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Are you sure you want to delete category: <?= addslashes($category['name']) ?>?')"
                               title="Delete Category">
                                Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <div style="font-size: 18px; color: #666; margin-bottom: 15px;">
                            📂 No categories found
                        </div>
                        <p style="color: #999;">Add your first category using the form above</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if (!empty($categories)): ?>
        <div style="margin-top: 20px; text-align: center; color: #666; font-size: 14px;">
            Showing <?= count($categories) ?> categor<?= count($categories) != 1 ? 'ies' : 'y' ?>
            • <?= $total_products ?> total products • Last updated: <?= date('Y-m-d H:i:s') ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>