<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in (you can implement your own auth system)
if (!isset($_SESSION['business_id'])) {
    $_SESSION['business_id'] = 1; // Default for demo
}

$business_id = $_SESSION['business_id'];
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $business_name = trim($_POST['business_name']);
        $business_name_arabic = trim($_POST['business_name_arabic']);
        $trn_number = trim($_POST['trn_number']);
        $address = trim($_POST['address']);
        $address_arabic = trim($_POST['address_arabic']);
        $city = trim($_POST['city']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $website = trim($_POST['website']);
        $vat_rate = floatval($_POST['vat_rate']);
        
        // Validate TRN number (UAE format)
        if (!preg_match('/^\d{15}$/', $trn_number)) {
            throw new Exception('TRN number must be 15 digits');
        }
        
        // Handle logo upload
        $logo_path = '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
            $upload_dir = 'uploads/logos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $logo_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($logo_extension, $allowed_extensions)) {
                $logo_filename = 'logo_' . $business_id . '_' . time() . '.' . $logo_extension;
                $logo_path = $upload_dir . $logo_filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
                    // Update database with logo path
                    $stmt = $pdo->prepare("UPDATE businesses SET logo_path = ? WHERE id = ?");
                    $stmt->execute([$logo_path, $business_id]);
                }
            } else {
                throw new Exception('Invalid logo file format. Only JPG, PNG, GIF allowed.');
            }
        }
        
        // Handle signature upload
        $signature_path = '';
        if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
            $upload_dir = 'uploads/signatures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $signature_extension = strtolower(pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($signature_extension, $allowed_extensions)) {
                $signature_filename = 'signature_' . $business_id . '_' . time() . '.' . $signature_extension;
                $signature_path = $upload_dir . $signature_filename;
                
                if (move_uploaded_file($_FILES['signature']['tmp_name'], $signature_path)) {
                    // Update database with signature path
                    $stmt = $pdo->prepare("UPDATE businesses SET signature_path = ? WHERE id = ?");
                    $stmt->execute([$signature_path, $business_id]);
                }
            } else {
                throw new Exception('Invalid signature file format. Only JPG, PNG, GIF allowed.');
            }
        }
        
        // Update business information
        $stmt = $pdo->prepare("
            UPDATE businesses SET 
                business_name = ?, 
                business_name_arabic = ?, 
                trn_number = ?, 
                address = ?, 
                address_arabic = ?, 
                city = ?, 
                phone = ?, 
                email = ?, 
                website = ?, 
                vat_rate = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $business_name, $business_name_arabic, $trn_number, 
            $address, $address_arabic, $city, $phone, $email, 
            $website, $vat_rate, $business_id
        ]);
        
        $message = 'Business settings updated successfully!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current business data
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Settings - UAE POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .section-title {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .logo-preview, .signature-preview {
            max-width: 200px;
            max-height: 100px;
            border: 2px dashed #ddd;
            padding: 10px;
            border-radius: 5px;
        }
        .required {
            color: #e74c3c;
        }
        .arabic-text {
            direction: rtl;
            text-align: right;
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
                        <a class="nav-link text-white active" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a class="nav-link text-white" href="create-invoice.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-cog"></i> Business Settings</h2>
                    <div>
                        <span class="badge bg-success">TRN: <?php echo htmlspecialchars($business['trn_number']); ?></span>
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

                <form method="POST" enctype="multipart/form-data">
                    <!-- Business Information -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-building"></i> Business Information
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="business_name" class="form-label">
                                        Business Name (English) <span class="required">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="business_name" name="business_name" 
                                           value="<?php echo htmlspecialchars($business['business_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="business_name_arabic" class="form-label">
                                        Business Name (Arabic)
                                    </label>
                                    <input type="text" class="form-control arabic-text" id="business_name_arabic" 
                                           name="business_name_arabic" 
                                           value="<?php echo htmlspecialchars($business['business_name_arabic']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="trn_number" class="form-label">
                                        TRN Number <span class="required">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="trn_number" name="trn_number" 
                                           value="<?php echo htmlspecialchars($business['trn_number']); ?>" 
                                           pattern="[0-9]{15}" maxlength="15" required>
                                    <div class="form-text">15-digit UAE Tax Registration Number</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="vat_rate" class="form-label">
                                        VAT Rate (%) <span class="required">*</span>
                                    </label>
                                    <input type="number" class="form-control" id="vat_rate" name="vat_rate" 
                                           value="<?php echo $business['vat_rate']; ?>" 
                                           step="0.01" min="0" max="100" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-phone"></i> Contact Information
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address (English)</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($business['address']); ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="address_arabic" class="form-label">Address (Arabic)</label>
                                    <textarea class="form-control arabic-text" id="address_arabic" name="address_arabic" rows="3"><?php echo htmlspecialchars($business['address_arabic']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($business['city']); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($business['phone']); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($business['email']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="website" class="form-label">Website</label>
                                    <input type="url" class="form-control" id="website" name="website" 
                                           value="<?php echo htmlspecialchars($business['website']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logo and Signature -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-image"></i> Logo & Signature
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="logo" class="form-label">Business Logo</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                    <?php if ($business['logo_path'] && file_exists($business['logo_path'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($business['logo_path']); ?>" 
                                                 class="logo-preview" alt="Current Logo">
                                            <div class="form-text">Current logo</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="signature" class="form-label">Digital Signature</label>
                                    <input type="file" class="form-control" id="signature" name="signature" accept="image/*">
                                    <?php if ($business['signature_path'] && file_exists($business['signature_path'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($business['signature_path']); ?>" 
                                                 class="signature-preview" alt="Current Signature">
                                            <div class="form-text">Current signature</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview uploaded images
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.logo-preview');
                    if (preview) {
                        preview.src = e.target.result;
                    } else {
                        const div = document.createElement('div');
                        div.className = 'mt-2';
                        div.innerHTML = '<img src="' + e.target.result + '" class="logo-preview" alt="Logo Preview"><div class="form-text">New logo preview</div>';
                        document.getElementById('logo').parentNode.appendChild(div);
                    }
                };
                reader.readAsDataURL(file);
            }
        });

        document.getElementById('signature').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.signature-preview');
                    if (preview) {
                        preview.src = e.target.result;
                    } else {
                        const div = document.createElement('div');
                        div.className = 'mt-2';
                        div.innerHTML = '<img src="' + e.target.result + '" class="signature-preview" alt="Signature Preview"><div class="form-text">New signature preview</div>';
                        document.getElementById('signature').parentNode.appendChild(div);
                    }
                };
                reader.readAsDataURL(file);
            }
        });

        // TRN number formatting
        document.getElementById('trn_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 15) {
                value = value.substring(0, 15);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>