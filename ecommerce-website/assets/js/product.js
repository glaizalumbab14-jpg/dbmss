// Product Modal Functionality
class ProductModal {
    constructor() {
        this.modal = document.getElementById('imageModal');
        this.init();
    }

    init() {
        // Create modal if it doesn't exist
        if (!this.modal) {
            this.createModal();
        }
        
        this.setupEventListeners();
    }

    createModal() {
        const modalHTML = `
            <div id="imageModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Product Details</h3>
                        <button class="close-btn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-image-container">
                            <img id="modalImage" class="modal-image" src="" alt="">
                            <div id="modalEmptyImage" class="empty-image">📦</div>
                        </div>
                        <div class="modal-details">
                            <h2 id="modalProductName" class="modal-product-name"></h2>
                            <div class="modal-product-info">
                                <div><strong>Price:</strong> $<span id="modalProductPrice"></span></div>
                                <div><strong>Quantity:</strong> <span id="modalProductQuantity"></span></div>
                                <div><strong>Total:</strong> $<span id="modalProductTotal"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('imageModal');
        
        // Add modal styles if not already present
        this.addModalStyles();
    }

    addModalStyles() {
        if (document.getElementById('modal-styles')) return;

        const styles = `
            <style id="modal-styles">
                .modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    z-index: 1000;
                    justify-content: center;
                    align-items: center;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }
                
                .modal.active {
                    display: flex;
                    opacity: 1;
                }
                
                .modal-content {
                    background: white;
                    border-radius: 15px;
                    max-width: 500px;
                    width: 90%;
                    max-height: 90vh;
                    overflow: hidden;
                    transform: scale(0.9);
                    transition: transform 0.3s ease;
                }
                
                .modal.active .modal-content {
                    transform: scale(1);
                }
                
                .modal-header {
                    padding: 20px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .modal-header h3 {
                    margin: 0;
                    font-size: 18px;
                }
                
                .close-btn {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 28px;
                    cursor: pointer;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: transform 0.3s;
                }
                
                .close-btn:hover {
                    transform: rotate(90deg);
                }
                
                .modal-body {
                    padding: 30px;
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }
                
                .modal-image-container {
                    width: 100%;
                    height: 250px;
                    background: #f8f9fa;
                    border-radius: 10px;
                    overflow: hidden;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .modal-image {
                    max-width: 100%;
                    max-height: 100%;
                    object-fit: contain;
                }
                
                .modal-details {
                    text-align: center;
                }
                
                .modal-product-name {
                    font-size: 20px;
                    color: #333;
                    margin-bottom: 15px;
                }
                
                .modal-product-info {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                }
                
                .modal-product-info div {
                    font-size: 16px;
                    color: #555;
                }
                
                .empty-image {
                    font-size: 48px;
                    color: #ccc;
                }
            </style>
        `;

        document.head.insertAdjacentHTML('beforeend', styles);
    }

    setupEventListeners() {
        // Close modal on close button click
        this.modal.querySelector('.close-btn').addEventListener('click', () => {
            this.close();
        });

        // Close modal when clicking outside
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });

        // Close modal with ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.close();
            }
        });
    }

    open(productData) {
        // Update modal content
        const modalImage = this.modal.querySelector('#modalImage');
        const modalEmptyImage = this.modal.querySelector('#modalEmptyImage');
        const modalProductName = this.modal.querySelector('#modalProductName');
        const modalProductPrice = this.modal.querySelector('#modalProductPrice');
        const modalProductQuantity = this.modal.querySelector('#modalProductQuantity');
        const modalProductTotal = this.modal.querySelector('#modalProductTotal');

        // Set product details
        modalProductName.textContent = productData.name || 'Product';
        modalProductPrice.textContent = productData.price ? productData.price.toFixed(2) : '0.00';
        modalProductQuantity.textContent = productData.quantity || 1;
        
        // Calculate total
        const total = (productData.price || 0) * (productData.quantity || 1);
        modalProductTotal.textContent = total.toFixed(2);

        // Set image
        if (productData.image) {
            modalImage.src = productData.image;
            modalImage.style.display = 'block';
            modalEmptyImage.style.display = 'none';
            
            // Handle image loading errors
            modalImage.onerror = () => {
                modalImage.style.display = 'none';
                modalEmptyImage.style.display = 'flex';
            };
        } else {
            modalImage.style.display = 'none';
            modalEmptyImage.style.display = 'flex';
        }

        // Show modal
        this.modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    close() {
        this.modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Initialize modal
let productModal = null;

// Global function to open product modal
function openProductModal(productData) {
    if (!productModal) {
        productModal = new ProductModal();
    }
    productModal.open(productData);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    productModal = new ProductModal();
});