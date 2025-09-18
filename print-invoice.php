<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['business_id'])) {
    $_SESSION['business_id'] = 1; // Default for demo
}

$business_id = $_SESSION['business_id'];
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$invoice_id) {
    die('Invoice ID is required');
}

// Get invoice data
$stmt = $pdo->prepare("
    SELECT i.*, b.*, c.customer_name, c.customer_name_arabic, c.email as customer_email, 
           c.phone as customer_phone, c.address as customer_address, c.trn_number as customer_trn
    FROM invoices i
    JOIN businesses b ON i.business_id = b.id
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.id = ? AND i.business_id = ?
");
$stmt->execute([$invoice_id, $business_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die('Invoice not found');
}

// Get invoice items
$stmt = $pdo->prepare("
    SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id
");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll();

// Handle status update
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $invoice_id]);
    header("Location: print-invoice.php?id=$invoice_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?> - UAE POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-break { page-break-before: always; }
            body { font-size: 12px; }
            .invoice-container { margin: 0; padding: 0; }
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .business-logo {
            max-height: 80px;
            max-width: 200px;
        }
        
        .invoice-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            text-align: center;
        }
        
        .invoice-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #6c757d;
        }
        
        .business-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .customer-info {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .invoice-details {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background: #007bff;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .totals-section {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .total-row.final {
            font-size: 1.2rem;
            font-weight: bold;
            border-top: 2px solid #28a745;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .status-draft { background: #6c757d; color: white; }
        .status-sent { background: #17a2b8; color: white; }
        .status-paid { background: #28a745; color: white; }
        .status-overdue { background: #dc3545; color: white; }
        .status-cancelled { background: #6c757d; color: white; }
        
        .arabic-text {
            direction: rtl;
            text-align: right;
        }
        
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: end;
        }
        
        .signature-box {
            text-align: center;
            border-top: 1px solid #000;
            width: 200px;
            padding-top: 10px;
        }
        
        .signature-image {
            max-height: 60px;
            max-width: 150px;
        }
        
        .footer-notes {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.9rem;
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
                            <i class="fas fa-file-invoice"></i> Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?>
                        </h4>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn btn-outline-success" onclick="downloadPDF()">
                                <i class="fas fa-download"></i> Download PDF
                            </button>
                            <a href="create-invoice.php" class="btn btn-outline-secondary">
                                <i class="fas fa-plus"></i> New Invoice
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="invoice-container">
                <!-- Invoice Header -->
                <div class="invoice-header">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <?php if ($invoice['logo_path'] && file_exists($invoice['logo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($invoice['logo_path']); ?>" 
                                     class="business-logo" alt="Business Logo">
                            <?php else: ?>
                                <h2 class="text-primary"><?php echo htmlspecialchars($invoice['business_name']); ?></h2>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-center">
                            <h1 class="invoice-title">INVOICE</h1>
                            <p class="invoice-number">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="status-badge status-<?php echo $invoice['status']; ?>">
                                <?php echo strtoupper($invoice['status']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Business Information -->
                <div class="business-info">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-building"></i> From:
                            </h5>
                            <h6><?php echo htmlspecialchars($invoice['business_name']); ?></h6>
                            <?php if ($invoice['business_name_arabic']): ?>
                                <p class="arabic-text text-muted"><?php echo htmlspecialchars($invoice['business_name_arabic']); ?></p>
                            <?php endif; ?>
                            <p class="mb-1"><?php echo htmlspecialchars($invoice['address']); ?></p>
                            <?php if ($invoice['address_arabic']): ?>
                                <p class="arabic-text text-muted mb-1"><?php echo htmlspecialchars($invoice['address_arabic']); ?></p>
                            <?php endif; ?>
                            <p class="mb-1"><?php echo htmlspecialchars($invoice['city']); ?>, UAE</p>
                            <p class="mb-1">Phone: <?php echo htmlspecialchars($invoice['phone']); ?></p>
                            <p class="mb-1">Email: <?php echo htmlspecialchars($invoice['email']); ?></p>
                            <p class="mb-0"><strong>TRN: <?php echo htmlspecialchars($invoice['trn_number']); ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <h5 class="text-success mb-3">
                                <i class="fas fa-user"></i> To:
                            </h5>
                            <?php if ($invoice['customer_name']): ?>
                                <h6><?php echo htmlspecialchars($invoice['customer_name']); ?></h6>
                                <?php if ($invoice['customer_name_arabic']): ?>
                                    <p class="arabic-text text-muted"><?php echo htmlspecialchars($invoice['customer_name_arabic']); ?></p>
                                <?php endif; ?>
                                <p class="mb-1"><?php echo htmlspecialchars($invoice['customer_address']); ?></p>
                                <p class="mb-1">Phone: <?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                                <p class="mb-1">Email: <?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                                <?php if ($invoice['customer_trn']): ?>
                                    <p class="mb-0"><strong>TRN: <?php echo htmlspecialchars($invoice['customer_trn']); ?></strong></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">Walk-in Customer</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Invoice Details -->
                <div class="invoice-details">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Invoice Date:</strong><br>
                            <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Due Date:</strong><br>
                            <?php echo $invoice['due_date'] ? date('d M Y', strtotime($invoice['due_date'])) : 'N/A'; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Payment Method:</strong><br>
                            <?php echo ucfirst(str_replace('_', ' ', $invoice['payment_method'])); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Currency:</strong><br>
                            <?php echo $invoice['currency']; ?>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items -->
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 35%;">Description</th>
                            <th style="width: 10%;">Qty</th>
                            <th style="width: 15%;">Unit Price</th>
                            <th style="width: 10%;">VAT%</th>
                            <th style="width: 15%;">VAT Amount</th>
                            <th style="width: 10%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <?php if ($item['item_name_arabic']): ?>
                                        <br><span class="arabic-text text-muted"><?php echo htmlspecialchars($item['item_name_arabic']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($item['quantity'], 3); ?></td>
                                <td><?php echo number_format($item['unit_price'], 2); ?> AED</td>
                                <td><?php echo number_format($item['vat_rate'], 2); ?>%</td>
                                <td><?php echo number_format($item['vat_amount'], 2); ?> AED</td>
                                <td><strong><?php echo number_format($item['line_total'], 2); ?> AED</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="totals-section">
                    <div class="row">
                        <div class="col-md-8"></div>
                        <div class="col-md-4">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span><?php echo number_format($invoice['subtotal'], 2); ?> AED</span>
                            </div>
                            <div class="total-row">
                                <span>VAT (<?php echo $invoice['vat_rate']; ?>%):</span>
                                <span><?php echo number_format($invoice['vat_amount'], 2); ?> AED</span>
                            </div>
                            <div class="total-row final">
                                <span>Total Amount:</span>
                                <span><?php echo number_format($invoice['total_amount'], 2); ?> AED</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($invoice['notes'] || $invoice['notes_arabic']): ?>
                    <div class="footer-notes">
                        <h6><i class="fas fa-sticky-note"></i> Additional Notes:</h6>
                        <?php if ($invoice['notes']): ?>
                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                        <?php endif; ?>
                        <?php if ($invoice['notes_arabic']): ?>
                            <p class="arabic-text mb-0"><?php echo nl2br(htmlspecialchars($invoice['notes_arabic'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Signature Section -->
                <div class="signature-section">
                    <div>
                        <p class="mb-0"><strong>Thank you for your business!</strong></p>
                        <p class="text-muted">Payment terms: 30 days from invoice date</p>
                    </div>
                    <div class="signature-box">
                        <?php if ($invoice['signature_path'] && file_exists($invoice['signature_path'])): ?>
                            <img src="<?php echo htmlspecialchars($invoice['signature_path']); ?>" 
                                 class="signature-image" alt="Signature">
                        <?php endif; ?>
                        <p class="mb-0 mt-2">Authorized Signature</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-4 pt-3 border-top">
                    <p class="text-muted mb-0">
                        This invoice was generated on <?php echo date('d M Y H:i:s'); ?> | 
                        UAE VAT Compliant Invoice System
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal (Hidden when printing) -->
    <div class="modal fade no-print" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Invoice Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="draft" <?php echo $invoice['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="sent" <?php echo $invoice['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="paid" <?php echo $invoice['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="overdue" <?php echo $invoice['status'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="cancelled" <?php echo $invoice['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.querySelector('.invoice-container');
            const opt = {
                margin: 10,
                filename: 'invoice-<?php echo $invoice['invoice_number']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2canvas(element, { scale: 2 }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgWidth = 210;
                const pageHeight = 295;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;
                
                let position = 0;
                
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                pdf.save('invoice-<?php echo $invoice['invoice_number']; ?>.pdf');
            });
        }
    </script>
</body>
</html>