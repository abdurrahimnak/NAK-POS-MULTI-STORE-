# UAE Multi-Business POS & Invoice System

A comprehensive Point of Sale (POS) and Invoice Management System designed specifically for UAE businesses with VAT compliance and bilingual support (English/Arabic).

## 🚀 Features

### Core Functionality
- **Multi-Business Support** - Manage multiple businesses from one system
- **UAE VAT Compliance** - Built-in 5% VAT calculation and TRN support
- **Bilingual Interface** - English and Arabic language support
- **Professional Invoicing** - Generate UAE-compliant invoices with logo and signature
- **POS System** - Complete point of sale with cart management and receipt printing
- **Product Management** - Inventory tracking with stock levels
- **Customer Management** - Customer database with TRN support

### Technical Features
- **Responsive Design** - Works on desktop, tablet, and mobile devices
- **Print-Ready** - Professional invoice and receipt printing
- **PDF Export** - Download invoices as PDF files
- **Real-time Calculations** - Automatic VAT and total calculations
- **Search & Filter** - Quick product search and category filtering
- **Status Management** - Track invoice and payment statuses

## 🛠️ Tech Stack

- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Libraries**: Select2, jsPDF, html2canvas

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/abdurrahimnak/NAK-POS-MULTI-STORE-.git
cd NAK-POS-MULTI-STORE-
```

### 2. Database Setup
1. Create a MySQL database
2. Import the database schema:
```bash
mysql -u username -p database_name < database_schema.sql
```

### 3. Configuration
1. Update database credentials in `config/database.php`:
```php
$host = 'localhost';
$dbname = 'your_database_name';
$username = 'your_username';
$password = 'your_password';
```

### 4. File Permissions
Ensure the uploads directory is writable:
```bash
mkdir -p uploads/logos uploads/signatures
chmod 755 uploads/logos uploads/signatures
```

### 5. Web Server Setup
Point your web server document root to the project directory.

## 📁 Project Structure

```
├── config/
│   └── database.php          # Database configuration
├── uploads/
│   ├── logos/               # Business logos
│   └── signatures/          # Digital signatures
├── create-invoice.php       # Invoice creation form
├── print-invoice.php        # Invoice printing and PDF export
├── pos.php                  # Point of Sale interface
├── pos-receipt.php          # POS receipt printing
├── settings.php             # Business settings management
├── database_schema.sql      # Database structure
└── README.md               # This file
```

## 🎯 Usage

### 1. Initial Setup
1. Access `settings.php` to configure your business information
2. Upload your business logo and digital signature
3. Set your TRN number and VAT rate
4. Configure business address and contact details

### 2. Product Management
1. Add products through the products interface
2. Set prices, descriptions, and stock levels
3. Organize products by categories

### 3. Creating Invoices
1. Go to `create-invoice.php`
2. Select customer (optional)
3. Add items with quantities
4. System automatically calculates VAT
5. Generate and print invoice

### 4. POS Operations
1. Access `pos.php` for point of sale
2. Search and add products to cart
3. Process payment
4. Print receipt

## 🏢 UAE Compliance Features

- **TRN Support** - 15-digit UAE Tax Registration Number validation
- **VAT Calculation** - Automatic 5% VAT calculation
- **Bilingual Invoices** - English and Arabic text support
- **Professional Formatting** - UAE business standard invoice layout
- **Receipt Printing** - Thermal printer compatible receipts

## 🔧 Configuration

### Business Settings
- Business name (English/Arabic)
- TRN number validation
- VAT rate configuration
- Logo and signature upload
- Address and contact information

### Invoice Settings
- Invoice numbering format
- Payment terms
- Currency (AED)
- Due date calculation

## 📱 Mobile Responsive

The system is fully responsive and works on:
- Desktop computers
- Tablets
- Mobile phones
- POS terminals

## 🖨️ Printing

### Invoice Printing
- Professional A4 format
- Business logo and signature
- TRN and VAT details
- Bilingual content support

### Receipt Printing
- Thermal printer compatible
- Compact format
- Essential transaction details
- Change calculation

## 🔒 Security Features

- SQL injection prevention
- Input validation and sanitization
- File upload restrictions
- Session management

## 🚀 Future Enhancements

- [ ] User authentication system
- [ ] Multi-language support
- [ ] Advanced reporting
- [ ] Inventory alerts
- [ ] Barcode scanning
- [ ] Payment gateway integration
- [ ] Mobile app
- [ ] Cloud deployment

## 📞 Support

For support and questions:
- GitHub Issues: [Create an issue](https://github.com/abdurrahimnak/NAK-POS-MULTI-STORE-/issues)
- Email: [Your email]

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📊 Database Schema

The system includes the following main tables:
- `businesses` - Business information
- `products` - Product catalog
- `customers` - Customer database
- `invoices` - Invoice records
- `invoice_items` - Invoice line items
- `pos_transactions` - POS transactions
- `pos_transaction_items` - POS transaction items

## 🎨 Customization

The system is designed to be easily customizable:
- Modify CSS for branding
- Add new fields to forms
- Extend database schema
- Add new features

---

**Built with ❤️ for UAE Businesses**