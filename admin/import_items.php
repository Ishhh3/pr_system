<?php
/**
 * CSV Import Items - NEW FILE
 * Bulk import items from CSV file
 * Format: item_name, description, category, unit_types, price
 */

require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: ../user/dashboard.php");
    exit();
}

$page_title = 'Import Items from CSV';
$page_subtitle = 'Bulk import items using CSV file';

global $db;
$error = '';
$success = '';
$preview_data = null;
$import_result = null;

// Handle CSV upload and import
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['upload_csv']) && isset($_FILES['csv_file'])) {
        // Validate file
        $validation_errors = validateCSVFile($_FILES['csv_file']);
        
        if (!empty($validation_errors)) {
            $error = implode('<br>', $validation_errors);
        } else {
            // Parse CSV
            $result = parseCSVItems($_FILES['csv_file']);
            
            if (!empty($result['errors'])) {
                $error = 'CSV Parsing Errors:<br>' . implode('<br>', $result['errors']);
            } else if (empty($result['items'])) {
                $error = 'No valid items found in CSV file';
            } else {
                $preview_data = $result['items'];
                $success = 'CSV parsed successfully! Review the items below and click "Confirm Import" to add them to the database.';
            }
        }
    }
    
    if (isset($_POST['confirm_import']) && isset($_POST['items_json'])) {
        // Import items
        $items = json_decode($_POST['items_json'], true);
        
        if (!empty($items)) {
            $import_result = importCSVItems($items);
            
            if ($import_result['imported'] > 0) {
                $_SESSION['success'] = 'Successfully imported ' . $import_result['imported'] . ' items' .
                                      ($import_result['skipped'] > 0 ? ' (' . $import_result['skipped'] . ' duplicates skipped)' : '');
                header("Location: view_items.php");
                exit();
            } else {
                $error = 'No items were imported. ' . implode('<br>', $import_result['errors']);
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3><i class="fas fa-file-upload"></i> <?php echo $page_title; ?></h3>
                <span class="header-subtitle"><?php echo $page_subtitle; ?></span>
            </div>
            <div>
                <a href="view_items.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Items
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$preview_data): ?>
            <!-- Upload Form -->
            <div class="import-instructions">
                <h4><i class="fas fa-info-circle"></i> CSV Import Instructions</h4>
                <div class="instructions-content">
                    <p>Follow these steps to import items in bulk:</p>
                    <ol>
                        <li><strong>Download the template:</strong> Use the button below to get a sample CSV file</li>
                        <li><strong>Fill in your data:</strong> Add your items following the template format</li>
                        <li><strong>Upload the file:</strong> Select your completed CSV file and click "Upload & Preview"</li>
                        <li><strong>Review:</strong> Check the preview to ensure data is correct</li>
                        <li><strong>Import:</strong> Click "Confirm Import" to add items to the database</li>
                    </ol>
                    
                    <h5>CSV Format Requirements:</h5>
                    <table class="format-table">
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Description</th>
                                <th>Required</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>item_name</code></td>
                                <td>Name of the item</td>
                                <td class="text-success">Yes</td>
                                <td>Bond Paper A4</td>
                            </tr>
                            <tr>
                                <td><code>description</code></td>
                                <td>Item description</td>
                                <td class="text-muted">No</td>
                                <td>Standard white bond paper</td>
                            </tr>
                            <tr>
                                <td><code>category</code></td>
                                <td>Category name</td>
                                <td class="text-muted">No</td>
                                <td>Paper Products</td>
                            </tr>
                            <tr>
                                <td><code>unit_types</code></td>
                                <td>Comma-separated units</td>
                                <td class="text-success">Yes</td>
                                <td>reams,boxes</td>
                            </tr>
                            <tr>
                                <td><code>price</code></td>
                                <td>Price per unit</td>
                                <td class="text-success">Yes</td>
                                <td>250.00</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="alert" style="background-color: #fff3cd; border: 1px solid #ffc107; margin-top: 20px;">
                        <strong><i class="fas fa-lightbulb"></i> Tips:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <li>Categories will be created automatically if they don't exist</li>
                            <li>Duplicate items (same name) will be skipped</li>
                            <li>Unit types should be lowercase and comma-separated without spaces: <code>pcs,boxes,reams</code></li>
                            <li>Prices should be numeric values without currency symbols</li>
                            <li>Maximum file size: 5MB</li>
                        </ul>
                    </div>
                </div>
                
                <div class="template-download">
                    <button type="button" class="btn btn-info btn-lg" onclick="downloadTemplate()">
                        <i class="fas fa-download"></i> Download CSV Template
                    </button>
                </div>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-group">
                    <label for="csv_file"><i class="fas fa-file-csv"></i> Select CSV File *</label>
                    <input type="file" 
                           id="csv_file" 
                           name="csv_file" 
                           class="form-control" 
                           accept=".csv,.txt"
                           required>
                    <small class="form-text">Accepted formats: .csv, .txt (Max size: 5MB)</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="upload_csv" class="btn btn-primary btn-lg">
                        <i class="fas fa-upload"></i> Upload & Preview
                    </button>
                    <a href="view_items.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            
        <?php else: ?>
            <!-- Preview Table -->
            <div class="preview-section">
                <h4><i class="fas fa-eye"></i> Preview Items (<?php echo count($preview_data); ?> items)</h4>
                <p>Review the items below. Click "Confirm Import" to add them to the database.</p>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Unit Types</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['description'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['category'] ?: 'Uncategorized'); ?></td>
                                    <td><?php echo implode(', ', $item['unit_types']); ?></td>
                                    <td><?php echo formatCurrency($item['price']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <form method="POST" action="" id="confirmForm">
                    <input type="hidden" name="items_json" value='<?php echo htmlspecialchars(json_encode($preview_data)); ?>'>
                    
                    <div class="form-actions" style="margin-top: 30px;">
                        <button type="submit" name="confirm_import" class="btn btn-success btn-lg">
                            <i class="fas fa-check"></i> Confirm Import (<?php echo count($preview_data); ?> items)
                        </button>
                        <a href="import_items.php" class="btn btn-secondary">Cancel & Start Over</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function downloadTemplate() {
    // Create CSV content
    const csvContent = `item_name,description,category,unit_types,price
Bond Paper A4,Standard white bond paper,Paper Products,"reams,boxes",250.00
Ballpen (Blue),Blue ballpoint pen,Office Supplies,"pcs,dozens,boxes",8.00
Stapler,Standard office stapler,Office Supplies,"pcs,units",85.00
Laptop,Company laptop for employees,Technology,"pcs,units",35000.00
Office Desk,Standard office desk,Furniture,"pcs,units",8500.00`;
    
    // Create blob and download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'items_import_template.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// File validation
document.getElementById('csv_file')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    
    if (file) {
        // Check file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size exceeds 5MB limit');
            this.value = '';
            return;
        }
        
        // Check file extension
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'csv' && ext !== 'txt') {
            alert('Invalid file type. Only CSV files are allowed');
            this.value = '';
            return;
        }
    }
});
</script>

<style>
.import-instructions {
    background: #f8f9fa;
    padding: 30px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    margin-bottom: 30px;
}

.instructions-content ol {
    line-height: 2;
}

.format-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

.format-table th,
.format-table td {
    padding: 12px;
    text-align: left;
    border: 1px solid #dee2e6;
}

.format-table thead {
    background-color: #e7e9eb;
}

.format-table code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
}

.template-download {
    text-align: center;
    margin: 30px 0;
}

.preview-section {
    background: #f8f9fa;
    padding: 30px;
    border-radius: 8px;
    border: 2px solid #28a745;
}

.preview-section h4 {
    color: #28a745;
    margin-bottom: 15px;
}
</style>

<?php include '../includes/footer.php'; ?>