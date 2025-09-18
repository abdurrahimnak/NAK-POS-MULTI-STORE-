<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['business_id'])) {
    $_SESSION['business_id'] = 1; // Default for demo
}

$business_id = $_SESSION['business_id'];
$message = '';
$error = '';

// Get business info
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

// Get products for search
$stmt = $pdo->prepare("SELECT * FROM products WHERE business_id = ? AND is_active = 1 ORDER BY product_name");
$stmt->execute([$business_id]);
$products = $stmt->fetchAll();

// Handle POS transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_sale'])) {
    try {
        $payment_method = $_POST['payment_method'];
        $amount_paid = floatval($_POST['amount_paid']);
        $cashier_name = trim($_POST['cashier_name']);
        $notes = trim($_POST['notes']);
        
        // Get cart items from session
        $cart = $_SESSION['pos_cart'] ?? [];
        
        if (empty($cart)) {
            throw new Exception('Cart is empty');
        }
        
        // Calculate totals
        $subtotal = 0;
        $vat_amount = 0;
        $total_amount = 0;
        
        foreach ($cart as $item) {
            $line_subtotal = $item['quantity'] * $item['unit_price'];
            $line_vat = $line_subtotal * ($item['vat_rate'] / 100);
            $line_total = $line_subtotal + $line_vat;
            
            $subtotal += $line_subtotal;
            $vat_amount += $line_vat;
            $total_amount += $line_total;
        }
        
        if ($amount_paid < $total_amount) {
            throw new Exception('Amount paid is less than total amount');
        }
        
        $change_amount = $amount_paid - $total_amount;
        
        // Generate transaction number
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pos_transactions WHERE business_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$business_id]);
        $count = $stmt->fetch()['count'];
        $transaction_number = 'POS-' . date('Ymd') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        // Insert transaction
        $stmt = $pdo->prepare("
            INSERT INTO pos_transactions (
                business_id, transaction_number, subtotal, vat_amount, total_amount,
                payment_method, amount_paid, change_amount, cashier_name, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $business_id, $transaction_number, $subtotal, $vat_amount, $total_amount,
            $payment_method, $amount_paid, $change_amount, $cashier_name, $notes
        ]);
        
        $transaction_id = $pdo->lastInsertId();
        
        // Insert transaction items
        $stmt = $pdo->prepare("
            INSERT INTO pos_transaction_items (
                transaction_id, product_id, item_name, quantity, unit_price, vat_rate, vat_amount, line_total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($cart as $item) {
            $line_subtotal = $item['quantity'] * $item['unit_price'];
            $line_vat = $line_subtotal * ($item['vat_rate'] / 100);
            $line_total = $line_subtotal + $line_vat;
            
            $stmt->execute([
                $transaction_id, $item['product_id'], $item['item_name'], $item['quantity'],
                $item['unit_price'], $item['vat_rate'], $line_vat, $line_total
            ]);
            
            // Update product stock
            if ($item['product_id']) {
                $stmt_update = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $stmt_update->execute([$item['quantity'], $item['product_id']]);
            }
        }
        
        // Clear cart
        $_SESSION['pos_cart'] = [];
        
        $message = "Sale completed successfully! Transaction #$transaction_number";
        
        // Redirect to receipt
        header("Location: pos-receipt.php?id=$transaction_id");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = floatval($_POST['quantity']);
    
    if ($product_id && $quantity > 0) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND business_id = ?");
        $stmt->execute([$product_id, $business_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            if (!isset($_SESSION['pos_cart'])) {
                $_SESSION['pos_cart'] = [];
            }
            
            // Check if product already in cart
            $found = false;
            foreach ($_SESSION['pos_cart'] as &$item) {
                if ($item['product_id'] == $product_id) {
                    $item['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['pos_cart'][] = [
                    'product_id' => $product['id'],
                    'item_name' => $product['product_name'],
                    'quantity' => $quantity,
                    'unit_price' => $product['price'],
                    'vat_rate' => $business['vat_rate']
                ];
            }
        }
    }
}

// Handle remove from cart
if (isset($_GET['remove_item'])) {
    $index = (int)$_GET['remove_item'];
    if (isset($_SESSION['pos_cart'][$index])) {
        unset($_SESSION['pos_cart'][$index]);
        $_SESSION['pos_cart'] = array_values($_SESSION['pos_cart']); // Re-index array
    }
}

// Handle clear cart
if (isset($_GET['clear_cart'])) {
    $_SESSION['pos_cart'] = [];
}

$cart = $_SESSION['pos_cart'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - UAE POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .pos-container {
            height: 100vh;
            overflow: hidden;
        }
        
        .product-grid {
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .product-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        
        .cart-container {
            background: #f8f9fa;
            height: 100vh;
            overflow-y: auto;
        }
        
        .cart-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .search-box {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .totals-section {
            background: #e8f5e8;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        .keypad button {
            height: 50px;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .payment-section {
            background: #fff3cd;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        
        .cart-empty {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="pos-container">
        <div class="row g-0 h-100">
            <!-- Product Selection Area -->
            <div class="col-md-8">
                <!-- Header -->
                <div class="bg-primary text-white p-3">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-0">
                                <i class="fas fa-cash-register"></i> Point of Sale
                            </h4>
                            <small>TRN: <?php echo htmlspecialchars($business['trn_number']); ?></small>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <a href="create-invoice.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-file-invoice"></i> Create Invoice
                                </a>
                                <a href="dashboard.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Box -->
                <div class="search-box">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control" id="productSearch" 
                                       placeholder="Search products by name or code...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="categoryFilter">
                                <option value="">All Categories</option>
                                <?php
                                $categories = array_unique(array_column($products, 'category'));
                                foreach ($categories as $category):
                                ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="p-3">
                    <div class="row product-grid" id="productGrid">
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-3 col-sm-6 mb-3 product-item" 
                                 data-name="<?php echo strtolower($product['product_name']); ?>"
                                 data-code="<?php echo strtolower($product['product_code']); ?>"
                                 data-category="<?php echo strtolower($product['category']); ?>"
                                 data-stock="<?php echo $product['stock_quantity']; ?>">
                                <div class="card product-card h-100" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($product['product_code']); ?></p>
                                        <h5 class="text-primary"><?php echo number_format($product['price'], 2); ?> AED</h5>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?php echo $product['stock_quantity'] > 10 ? 'success' : ($product['stock_quantity'] > 0 ? 'warning' : 'danger'); ?> status-badge">
                                                Stock: <?php echo $product['stock_quantity']; ?>
                                            </span>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['category']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Cart Area -->
            <div class="col-md-4 cart-container">
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="fas fa-shopping-cart"></i> Cart</h5>
                        <div>
                            <button class="btn btn-outline-danger btn-sm" onclick="clearCart()">
                                <i class="fas fa-trash"></i> Clear
                            </button>
                        </div>
                    </div>

                    <!-- Cart Items -->
                    <div id="cartItems">
                        <?php if (empty($cart)): ?>
                            <div class="cart-empty">
                                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                <p>Cart is empty</p>
                                <small>Click on products to add them to cart</small>
                            </div>
                        <?php else: ?>
                            <?php 
                            $subtotal = 0;
                            $vat_amount = 0;
                            foreach ($cart as $index => $item):
                                $line_subtotal = $item['quantity'] * $item['unit_price'];
                                $line_vat = $line_subtotal * ($item['vat_rate'] / 100);
                                $line_total = $line_subtotal + $line_vat;
                                $subtotal += $line_subtotal;
                                $vat_amount += $line_vat;
                            ?>
                                <div class="cart-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?php echo number_format($item['unit_price'], 2); ?> AED × <?php echo $item['quantity']; ?>
                                                </small>
                                                <strong><?php echo number_format($line_total, 2); ?> AED</strong>
                                            </div>
                                        </div>
                                        <button class="btn btn-outline-danger btn-sm ms-2" 
                                                onclick="removeFromCart(<?php echo $index; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Totals -->
                    <?php if (!empty($cart)): ?>
                        <div class="totals-section">
                            <div class="row text-end">
                                <div class="col-6">
                                    <strong>Subtotal:</strong>
                                </div>
                                <div class="col-6">
                                    <span id="subtotal"><?php echo number_format($subtotal, 2); ?> AED</span>
                                </div>
                            </div>
                            <div class="row text-end">
                                <div class="col-6">
                                    <strong>VAT (<?php echo $business['vat_rate']; ?>%):</strong>
                                </div>
                                <div class="col-6">
                                    <span id="vatAmount"><?php echo number_format($vat_amount, 2); ?> AED</span>
                                </div>
                            </div>
                            <hr>
                            <div class="row text-end">
                                <div class="col-6">
                                    <strong>Total:</strong>
                                </div>
                                <div class="col-6">
                                    <span id="totalAmount" class="fs-4 text-primary"><?php echo number_format($subtotal + $vat_amount, 2); ?> AED</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Payment Section -->
                    <?php if (!empty($cart)): ?>
                        <form method="POST" id="paymentForm">
                            <div class="payment-section">
                                <h6><i class="fas fa-credit-card"></i> Payment</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="cheque">Cheque</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Amount Paid</label>
                                    <div class="input-group">
                                        <span class="input-group-text">AED</span>
                                        <input type="number" class="form-control" name="amount_paid" 
                                               id="amountPaid" step="0.01" min="0" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Change</label>
                                    <div class="input-group">
                                        <span class="input-group-text">AED</span>
                                        <input type="text" class="form-control" id="changeAmount" readonly>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Cashier Name</label>
                                    <input type="text" class="form-control" name="cashier_name" 
                                           placeholder="Enter cashier name" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="2" 
                                              placeholder="Optional notes..."></textarea>
                                </div>

                                <!-- Quick Amount Buttons -->
                                <div class="keypad">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setAmount(<?php echo $subtotal + $vat_amount; ?>)">
                                        Exact
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="addAmount(10)">
                                        +10
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="addAmount(20)">
                                        +20
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="addAmount(50)">
                                        +50
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="addAmount(100)">
                                        +100
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="addAmount(500)">
                                        +500
                                    </button>
                                </div>

                                <div class="d-grid gap-2 mt-3">
                                    <button type="submit" name="process_sale" class="btn btn-success btn-lg">
                                        <i class="fas fa-check"></i> Complete Sale
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add to Cart Modal -->
    <div class="modal fade" id="addToCartModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add to Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="modalProductId">
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="modalQuantity" 
                                   value="1" min="0.001" step="0.001" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_to_cart" class="btn btn-primary">Add to Cart</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Product search and filter
        document.getElementById('productSearch').addEventListener('input', filterProducts);
        document.getElementById('categoryFilter').addEventListener('change', filterProducts);

        function filterProducts() {
            const searchTerm = document.getElementById('productSearch').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
            const products = document.querySelectorAll('.product-item');

            products.forEach(product => {
                const name = product.dataset.name;
                const code = product.dataset.code;
                const category = product.dataset.category;
                const stock = parseInt(product.dataset.stock);

                const matchesSearch = name.includes(searchTerm) || code.includes(searchTerm);
                const matchesCategory = !categoryFilter || category.includes(categoryFilter);
                const hasStock = stock > 0;

                if (matchesSearch && matchesCategory && hasStock) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }

        // Add to cart
        function addToCart(productId) {
            document.getElementById('modalProductId').value = productId;
            document.getElementById('modalQuantity').value = 1;
            new bootstrap.Modal(document.getElementById('addToCartModal')).show();
        }

        // Remove from cart
        function removeFromCart(index) {
            if (confirm('Remove this item from cart?')) {
                window.location.href = '?remove_item=' + index;
            }
        }

        // Clear cart
        function clearCart() {
            if (confirm('Clear entire cart?')) {
                window.location.href = '?clear_cart=1';
            }
        }

        // Payment calculations
        document.getElementById('amountPaid').addEventListener('input', calculateChange);

        function calculateChange() {
            const total = <?php echo $subtotal + $vat_amount; ?>;
            const paid = parseFloat(document.getElementById('amountPaid').value) || 0;
            const change = paid - total;
            document.getElementById('changeAmount').value = change >= 0 ? change.toFixed(2) : '0.00';
        }

        // Quick amount buttons
        function setAmount(amount) {
            document.getElementById('amountPaid').value = amount.toFixed(2);
            calculateChange();
        }

        function addAmount(amount) {
            const current = parseFloat(document.getElementById('amountPaid').value) || 0;
            document.getElementById('amountPaid').value = (current + amount).toFixed(2);
            calculateChange();
        }

        // Auto-focus search on page load
        document.getElementById('productSearch').focus();
    </script>
</body>
</html>