

// Cart Functionality
class CartManager {
    constructor() {
        this.cartItems = [];
        this.init();
    }

    init() {
        this.loadCart();
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Quantity buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quantity-btn')) {
                this.handleQuantityButton(e.target);
            }
        });

        // Quantity input changes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('quantity-input')) {
                this.handleQuantityInput(e.target);
            }
        });
    }

    handleQuantityButton(button) {
        const input = button.closest('.quantity-control').querySelector('.quantity-input');
        const productId = input.name.match(/\[(\d+)\]/)[1];
        const change = button.textContent === '+' ? 1 : -1;
        
        this.updateQuantity(productId, change, input);
    }

    handleQuantityInput(input) {
        const productId = input.name.match(/\[(\d+)\]/)[1];
        const value = parseInt(input.value);
        const max = parseInt(input.max);
        
        this.validateQuantity(productId, value, max, input);
    }

    updateQuantity(productId, change, inputElement) {
        let newValue = parseInt(inputElement.value) + change;
        
        // Ensure minimum of 0 and maximum of stock
        const max = parseInt(inputElement.max);
        newValue = Math.max(0, newValue);
        newValue = Math.min(newValue, max);
        
        inputElement.value = newValue;
        
        // If quantity becomes 0, show confirm to remove
        if (newValue === 0) {
            if (confirm('Remove this item from cart?')) {
                this.removeItem(productId);
            } else {
                inputElement.value = 1;
            }
        } else {
            // Auto-update cart via AJAX
            this.ajaxUpdateCart(productId, newValue);
        }
    }

    validateQuantity(productId, value, max, inputElement) {
        if (isNaN(value) || value < 0) {
            inputElement.value = 0;
        } else if (value > max) {
            alert(`Only ${max} items available in stock!`);
            inputElement.value = max;
            value = max;
        }
        
        if (value === 0) {
            if (confirm('Remove this item from cart?')) {
                this.removeItem(productId);
            } else {
                inputElement.value = 1;
            }
        } else if (value > 0) {
            // Auto-update cart
            this.ajaxUpdateCart(productId, value);
        }
    }

    ajaxUpdateCart(productId, quantity) {
        // You can implement AJAX call here to update cart without page reload
        console.log(`Updating product ${productId} to quantity ${quantity}`);
        
        // Example AJAX implementation:
        /*
        fetch('update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateCartDisplay(data.cart);
            }
        });
        */
    }

    removeItem(productId) {
        if (confirm('Remove this item from cart?')) {
            // Redirect to remove page
            window.location.href = `remove.php?id=${productId}`;
        }
    }

    loadCart() {
        // Load cart items from localStorage or server
        const cartData = localStorage.getItem('cart');
        if (cartData) {
            this.cartItems = JSON.parse(cartData);
        }
    }

    saveCart() {
        localStorage.setItem('cart', JSON.stringify(this.cartItems));
    }

    updateCartDisplay(cartData) {
        // Update cart count badge
        const totalItems = cartData.total_items || 0;
        const badge = document.querySelector('.badge');
        if (badge) {
            badge.textContent = totalItems;
            badge.style.display = totalItems > 0 ? 'block' : 'none';
        }

        // Update totals
        const subtotalElement = document.querySelector('.summary-row .summary-value');
        if (subtotalElement) {
            subtotalElement.textContent = `$${(cartData.subtotal || 0).toFixed(2)}`;
        }

        const totalElement = document.querySelector('.summary-row.total .summary-value');
        if (totalElement) {
            totalElement.textContent = `$${(cartData.total || 0).toFixed(2)}`;
        }
    }

    // Add item to cart
    addToCart(productId, quantity = 1) {
        // Find existing item
        const existingItem = this.cartItems.find(item => item.id === productId);
        
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            this.cartItems.push({
                id: productId,
                quantity: quantity
            });
        }
        
        this.saveCart();
        this.updateCartCount();
    }

    updateCartCount() {
        const total = this.cartItems.reduce((sum, item) => sum + item.quantity, 0);
        const badge = document.querySelector('.cart-badge');
        if (badge) {
            badge.textContent = total;
            badge.style.display = total > 0 ? 'block' : 'none';
        }
    }
}

// Initialize cart manager
let cartManager = null;

document.addEventListener('DOMContentLoaded', function() {
    cartManager = new CartManager();
    
    // Add to cart buttons (if any on page)
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const quantity = parseInt(this.getAttribute('data-quantity') || 1);
            
            if (cartManager) {
                cartManager.addToCart(productId, quantity);
                
                // Show success message
                alert('Product added to cart!');
            }
        });
    });
});