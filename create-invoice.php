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

// Get products for dropdown
$stmt = $pdo->prepare("SELECT * FROM products WHERE business_id = ? AND is_active = 1 ORDER BY product_name");
$stmt->execute([$business_id]);
$products = $stmt->fetchAll();

// Get customers for dropdown
$stmt = $pdo->prepare("SELECT * FROM customers WHERE business_id = ? AND is_active = 1 ORDER BY customer_name");
$stmt->execute([$business_id]);
$customers = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
        $invoice_date = $_POST['invoice_date'];
        $due_date = $_POST['due_date'];
        $payment_method = $_POST['payment_method'];
        $notes = trim($_POST['notes']);
        $notes_arabic = trim($_POST['notes_arabic']);
        
        // Generate invoice number
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE business_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$business_id]);
        $count = $stmt->fetch()['count'];
        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        // Calculate totals
        $subtotal = 0;
        $vat_amount = 0;
        $total_amount = 0;
        
        // Process invoice items
        $items = [];
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['item_name']) && $item['quantity'] > 0 && $item['unit_price'] > 0) {
                    $line_total = $item['quantity'] * $item['unit_price'];
                    $item_vat = $line_total * ($item['vat_rate'] / 100);
                    $line_total_with_vat = $line_total + $item_vat;
                    
                    $subtotal += $line_total;
                    $vat_amount += $item_vat;
                    $total_amount += $line_total_with_vat;
                    
                    $items[] = [
                        'product_id' => !empty($item['product_id']) ? $item['product_id'] : null,
                        'item_name' => $item['item_name'],
                        'item_name_arabic' => $item['item_name_arabic'],
                        'description' => $item['description'],
                        'description_arabic' => $item['description_arabic'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'vat_rate' => $item['vat_rate'],
                        'vat_amount' => $item_vat,
                        'line_total' => $line_total_with_vat
                    ];
                }
            }
        }
        
        if (empty($items)) {
            throw new Exception('Please add at least one item to the invoice');
        }
        
        // Insert invoice
        $stmt = $pdo->prepare("
            INSERT INTO invoices (
                business_id, invoice_number, customer_id, invoice_date, due_date,
                subtotal, vat_amount, total_amount, payment_method, notes, notes_arabic, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
        ");
        
        $stmt->execute([
            $business_id, $invoice_number, $customer_id, $invoice_date, $due_date,
            $subtotal, $vat_amount, $total_amount, $payment_method, $notes, $notes_arabic
        ]);
        
        $invoice_id = $pdo->lastInsertId();
        
        // Insert invoice items
        $stmt = $pdo->prepare("
            INSERT INTO invoice_items (
                invoice_id, product_id, item_name, item_name_arabic, description, description_arabic,
                quantity, unit_price, vat_rate, vat_amount, line_total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $invoice_id, $item['product_id'], $item['item_name'], $item['item_name_arabic'],
                $item['description'], $item['description_arabic'], $item['quantity'], $item['unit_price'],
                $item['vat_rate'], $item['vat_amount'], $item['line_total']
            ]);
        }
        
        $message = "Invoice created successfully! Invoice Number: $invoice_number";
        
        // Redirect to print invoice
        header("Location: print-invoice.php?id=$invoice_id");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice - UAE POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .item-row {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }
        .arabic-text {
            direction: rtl;
            text-align: right;
        }
        .total-section {
            background: #e8f5e8;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .btn-add-item {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
        }
        .btn-remove-item {
            background: #dc3545;
            border: none;
            color: white;
        }
        .select2-container {
            width: 100% !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-dark text-white min-vh-100 p-0">
                <div class="p-3">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-store"></i> UAE POS
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link text-white" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a class="nav-link text-white active" href="create-invoice.php">
                            <i class="fas fa-file-invoice"></i> Create Invoice
                        </a>
                        <a class="nav-link text-white" href="pos.php">
                            <i class="fas fa-cash-register"></i> POS
                        </a>
                        <a class="nav-link text-white" href="products.php">
                            <i class="fas fa-box"></i> Products
                        </a>
                        <a class="nav-link text-white" href="customers.php">
                            <i class="fas fa-users"></i> Customers
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="invoice-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="fas fa-file-invoice"></i> Create New Invoice</h2>
                            <p class="mb-0">Create a professional invoice with UAE VAT compliance</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="badge bg-light text-dark fs-6">
                                TRN: <?php echo htmlspecialchars($business['trn_number']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="invoiceForm">
                    <!-- Invoice Details -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Invoice Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="customer_id" class="form-label">Customer</label>
                                        <select class="form-select" id="customer_id" name="customer_id">
                                            <option value="">Select Customer (Optional)</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?php echo $customer['id']; ?>">
                                                    <?php echo htmlspecialchars($customer['customer_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="invoice_date" class="form-label">Invoice Date</label>
                                        <input type="date" class="form-control" id="invoice_date" name="invoice_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="due_date" class="form-label">Due Date</label>
                                        <input type="date" class="form-control" id="due_date" name="due_date" 
                                               value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Payment Method</label>
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="cheque">Cheque</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Items -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Invoice Items</h5>
                            <button type="button" class="btn btn-light btn-sm" id="addItemBtn">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="itemsContainer">
                                <!-- Items will be added dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-sticky-note"></i> Additional Notes</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes (English)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="Additional notes or terms..."></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="notes_arabic" class="form-label">Notes (Arabic)</label>
                                        <textarea class="form-control arabic-text" id="notes_arabic" name="notes_arabic" rows="3" 
                                                  placeholder="ملاحظات إضافية أو شروط..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="total-section">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Invoice Summary</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="row text-end">
                                    <div class="col-6">
                                        <strong>Subtotal (AED):</strong>
                                    </div>
                                    <div class="col-6">
                                        <span id="subtotal">0.00</span>
                                    </div>
                                </div>
                                <div class="row text-end">
                                    <div class="col-6">
                                        <strong>VAT (<?php echo $business['vat_rate']; ?>%):</strong>
                                    </div>
                                    <div class="col-6">
                                        <span id="vatAmount">0.00</span>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-end">
                                    <div class="col-6">
                                        <strong>Total (AED):</strong>
                                    </div>
                                    <div class="col-6">
                                        <span id="totalAmount" class="fs-4 text-primary">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-secondary btn-lg me-3" onclick="previewInvoice()">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Create Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let itemCount = 0;
        const vatRate = <?php echo $business['vat_rate']; ?>;
        const products = <?php echo json_encode($products); ?>;

        // Initialize Select2
        $(document).ready(function() {
            $('.form-select').select2({
                theme: 'bootstrap-5'
            });
        });

        // Add item function
        function addItem() {
            itemCount++;
            const itemHtml = `
                <div class="item-row" id="item-${itemCount}">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Product</label>
                            <select class="form-select product-select" name="items[${itemCount}][product_id]" onchange="selectProduct(${itemCount})">
                                <option value="">Select Product</option>
                                ${products.map(product => `
                                    <option value="${product.id}" 
                                            data-name="${product.product_name}" 
                                            data-name-arabic="${product.product_name_arabic || ''}"
                                            data-description="${product.description || ''}"
                                            data-description-arabic="${product.description_arabic || ''}"
                                            data-price="${product.price}">
                                        ${product.product_name} - ${product.price} AED
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Item Name (EN)</label>
                            <input type="text" class="form-control" name="items[${itemCount}][item_name]" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Item Name (AR)</label>
                            <input type="text" class="form-control arabic-text" name="items[${itemCount}][item_name_arabic]">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Qty</label>
                            <input type="number" class="form-control" name="items[${itemCount}][quantity]" 
                                   value="1" min="0.001" step="0.001" onchange="calculateLineTotal(${itemCount})" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Price</label>
                            <input type="number" class="form-control" name="items[${itemCount}][unit_price]" 
                                   min="0" step="0.01" onchange="calculateLineTotal(${itemCount})" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">VAT%</label>
                            <input type="number" class="form-control" name="items[${itemCount}][vat_rate]" 
                                   value="${vatRate}" min="0" max="100" step="0.01" onchange="calculateLineTotal(${itemCount})" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Total</label>
                            <input type="text" class="form-control line-total" readonly>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-remove-item btn-sm w-100" onclick="removeItem(${itemCount})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Description (EN)</label>
                            <input type="text" class="form-control" name="items[${itemCount}][description]">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description (AR)</label>
                            <input type="text" class="form-control arabic-text" name="items[${itemCount}][description_arabic]">
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', itemHtml);
            $(`#item-${itemCount} .product-select`).select2({theme: 'bootstrap-5'});
        }

        // Select product function
        function selectProduct(itemId) {
            const select = document.querySelector(`#item-${itemId} .product-select`);
            const option = select.options[select.selectedIndex];
            
            if (option.value) {
                const itemRow = document.getElementById(`item-${itemId}`);
                itemRow.querySelector('input[name*="[item_name]"]').value = option.dataset.name;
                itemRow.querySelector('input[name*="[item_name_arabic]"]').value = option.dataset.nameArabic;
                itemRow.querySelector('input[name*="[description]"]').value = option.dataset.description;
                itemRow.querySelector('input[name*="[description_arabic]"]').value = option.dataset.descriptionArabic;
                itemRow.querySelector('input[name*="[unit_price]"]').value = option.dataset.price;
                
                calculateLineTotal(itemId);
            }
        }

        // Calculate line total
        function calculateLineTotal(itemId) {
            const itemRow = document.getElementById(`item-${itemId}`);
            const quantity = parseFloat(itemRow.querySelector('input[name*="[quantity]"]').value) || 0;
            const unitPrice = parseFloat(itemRow.querySelector('input[name*="[unit_price]"]').value) || 0;
            const vatRate = parseFloat(itemRow.querySelector('input[name*="[vat_rate]"]').value) || 0;
            
            const subtotal = quantity * unitPrice;
            const vatAmount = subtotal * (vatRate / 100);
            const total = subtotal + vatAmount;
            
            itemRow.querySelector('.line-total').value = total.toFixed(2);
            
            calculateTotals();
        }

        // Calculate totals
        function calculateTotals() {
            let subtotal = 0;
            let vatAmount = 0;
            let totalAmount = 0;
            
            document.querySelectorAll('.item-row').forEach(item => {
                const quantity = parseFloat(item.querySelector('input[name*="[quantity]"]').value) || 0;
                const unitPrice = parseFloat(item.querySelector('input[name*="[unit_price]"]').value) || 0;
                const vatRate = parseFloat(item.querySelector('input[name*="[vat_rate]"]').value) || 0;
                
                const lineSubtotal = quantity * unitPrice;
                const lineVat = lineSubtotal * (vatRate / 100);
                
                subtotal += lineSubtotal;
                vatAmount += lineVat;
                totalAmount += lineSubtotal + lineVat;
            });
            
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('vatAmount').textContent = vatAmount.toFixed(2);
            document.getElementById('totalAmount').textContent = totalAmount.toFixed(2);
        }

        // Remove item function
        function removeItem(itemId) {
            document.getElementById(`item-${itemId}`).remove();
            calculateTotals();
        }

        // Preview invoice function
        function previewInvoice() {
            // This would open a preview window or modal
            alert('Preview functionality would be implemented here');
        }

        // Add first item on page load
        document.getElementById('addItemBtn').addEventListener('click', addItem);
        
        // Add first item automatically
        addItem();
    </script>
</body>
</html>