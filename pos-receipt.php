<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['business_id'])) {
    $_SESSION['business_id'] = 1; // Default for demo
}

$business_id = $_SESSION['business_id'];
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$transaction_id) {
    die('Transaction ID is required');
}

// Get transaction data
$stmt = $pdo->prepare("
    SELECT t.*, b.*
    FROM pos_transactions t
    JOIN businesses b ON t.business_id = b.id
    WHERE t.id = ? AND t.business_id = ?
");
$stmt->execute([$transaction_id, $business_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    die('Transaction not found');
}

// Get transaction items
$stmt = $pdo->prepare("
    SELECT * FROM pos_transaction_items WHERE transaction_id = ? ORDER BY id
");
$stmt->execute([$transaction_id]);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($transaction['transaction_number']); ?> - UAE POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .receipt-container { margin: 0; padding: 0; }
        }
        
        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .business-logo {
            max-height: 60px;
            max-width: 150px;
        }
        
        .receipt-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .receipt-info {
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background: #000;
            color: white;
            padding: 8px 4px;
            text-align: left;
            font-size: 0.8rem;
        }
        
        .items-table td {
            padding: 6px 4px;
            border-bottom: 1px solid #ddd;
            font-size: 0.8rem;
        }
        
        .totals-section {
            border-top: 2px solid #000;
            padding-top: 15px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .total-row.final {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 1px solid #000;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .payment-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.8rem;
            color: #666;
        }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Action Bar (Hidden when printing) -->
        <div class="no-print bg-light p-3 mb-3">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0">
                            <i class="fas fa-receipt"></i> Receipt #<?php echo htmlspecialchars($transaction['transaction_number']); ?>
                        </h4>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <a href="pos.php" class="btn btn-outline-success">
                                <i class="fas fa-plus"></i> New Sale
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="receipt-container">
                <!-- Receipt Header -->
                <div class="receipt-header">
                    <?php if ($transaction['logo_path'] && file_exists($transaction['logo_path'])): ?>
                        <img src="<?php echo htmlspecialchars($transaction['logo_path']); ?>" 
                             class="business-logo" alt="Business Logo">
                    <?php else: ?>
                        <h3><?php echo htmlspecialchars($transaction['business_name']); ?></h3>
                    <?php endif; ?>
                    
                    <div class="receipt-title">SALES RECEIPT</div>
                    
                    <div class="receipt-info">
                        <p class="mb-1">TRN: <?php echo htmlspecialchars($transaction['trn_number']); ?></p>
                        <p class="mb-1"><?php echo htmlspecialchars($transaction['address']); ?></p>
                        <p class="mb-1"><?php echo htmlspecialchars($transaction['city']); ?>, UAE</p>
                        <p class="mb-1">Tel: <?php echo htmlspecialchars($transaction['phone']); ?></p>
                    </div>
                </div>

                <!-- Transaction Info -->
                <div class="receipt-info">
                    <div class="row">
                        <div class="col-6">
                            <strong>Receipt #:</strong><br>
                            <?php echo htmlspecialchars($transaction['transaction_number']); ?>
                        </div>
                        <div class="col-6 text-end">
                            <strong>Date:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($transaction['transaction_date'])); ?>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <strong>Cashier:</strong><br>
                            <?php echo htmlspecialchars($transaction['cashier_name']); ?>
                        </div>
                        <div class="col-6 text-end">
                            <strong>Payment:</strong><br>
                            <?php echo ucfirst(str_replace('_', ' ', $transaction['payment_method'])); ?>
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo number_format($item['quantity'], 3); ?></td>
                                <td><?php echo number_format($item['unit_price'], 2); ?></td>
                                <td><?php echo number_format($item['line_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="totals-section">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span><?php echo number_format($transaction['subtotal'], 2); ?> AED</span>
                    </div>
                    <div class="total-row">
                        <span>VAT (<?php echo $transaction['vat_rate'] ?? 5; ?>%):</span>
                        <span><?php echo number_format($transaction['vat_amount'], 2); ?> AED</span>
                    </div>
                    <div class="total-row final">
                        <span>TOTAL:</span>
                        <span><?php echo number_format($transaction['total_amount'], 2); ?> AED</span>
                    </div>
                </div>

                <!-- Payment Info -->
                <div class="payment-info">
                    <div class="row">
                        <div class="col-6">
                            <strong>Amount Paid:</strong><br>
                            <?php echo number_format($transaction['amount_paid'], 2); ?> AED
                        </div>
                        <div class="col-6 text-end">
                            <strong>Change:</strong><br>
                            <?php echo number_format($transaction['change_amount'], 2); ?> AED
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($transaction['notes']): ?>
                    <div class="mt-3">
                        <strong>Notes:</strong><br>
                        <small><?php echo nl2br(htmlspecialchars($transaction['notes'])); ?></small>
                    </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="footer">
                    <p><strong>Thank you for your business!</strong></p>
                    <p>UAE VAT Compliant Receipt</p>
                    <p>Generated on <?php echo date('d M Y H:i:s'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-print on page load (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>