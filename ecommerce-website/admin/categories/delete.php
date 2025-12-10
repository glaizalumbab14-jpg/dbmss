<?php

require_once '../../config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        // Check if category has products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$id]);
        $product_count = $stmt->fetchColumn();
        
        if ($product_count > 0) {
            // Update products to remove category assignment instead of deleting
            $stmt = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?");
            $stmt->execute([$id]);
        }
        
        // Delete the category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success'] = 'Category deleted successfully';
        if ($product_count > 0) {
            $_SESSION['success'] .= ". $product_count product(s) are now uncategorized.";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Failed to delete category: ' . $e->getMessage();
    }
}

redirect('index.php');
?>