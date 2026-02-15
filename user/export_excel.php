<?php

// Excel Export for Bulk Requests
// Generates downloadable Excel file with request details and signature fields


require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is authorized
if ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'User') {
    header("Location: ../auth/login.php");
    exit();
}

$request_id = $_GET['id'] ?? 0;

if (!$request_id) {
    die('Invalid request ID');
}

global $db;

// Get request details
try {
    $stmt = $db->prepare("
        SELECT r.*, o.office_name, u.full_name, u.user_type
        FROM requests r
        JOIN offices o ON r.office_id = o.id
        JOIN users u ON r.user_id = u.id
        WHERE r.id = :id
    ");
    $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $request = $stmt->fetch();
    
    if (!$request) {
        die('Request not found');
    }
    
    // Check permissions
    if ($_SESSION['role'] == 'User') {
        // Users can only export their own office's pending requests
        if ($request['office_id'] != $_SESSION['office_id'] || $request['status'] != 'pending') {
            die('Access denied or request is not pending');
        }
    }
    
    // Get request items
    $stmt = $db->prepare("
        SELECT 
            ri.*,
            COALESCE(i.item_name, ri.custom_item_name) as item_name
        FROM request_items ri
        LEFT JOIN items i ON ri.item_id = i.id
        WHERE ri.request_id = :id
        ORDER BY item_name
    ");
    $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        die('No items found in this request');
    }
    
} catch(PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Get signature settings
$sig1_label = getSystemSetting('signature_1_label', 'Requested by');
$sig1_name = getSystemSetting('signature_1_name', '');
$sig2_label = getSystemSetting('signature_2_label', 'Approved by');
$sig2_name = getSystemSetting('signature_2_name', '');
$sig3_label = getSystemSetting('signature_3_label', 'Verified by');
$sig3_name = getSystemSetting('signature_3_name', '');
$sig4_label = getSystemSetting('signature_4_label', 'Received by');
$sig4_name = getSystemSetting('signature_4_name', '');

// Calculate total
$total = 0;
foreach ($items as $item) {
    $total += $item['quantity'] * $item['price_per_unit'];
}

// Set headers for Excel download
$filename = 'Purchase_Request_' . str_pad($request_id, 5, '0', STR_PAD_LEFT) . '_' . date('Y-m-d') . '.xls';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output Excel content
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }
        .header {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .info {
            margin-bottom: 20px;
        }
        .info-row {
            margin: 5px 0;
        }
        .total-row {
            font-weight: bold;
            background-color: #E7E6E6;
        }
        .signature-section {
            margin-top: 40px;
            width: 100%;
        }
        .signature-box {
            display: inline-block;
            width: 22%;
            text-align: center;
            margin: 10px 1%;
            vertical-align: top;
        }
        .signature-line {
            border-top: 1px solid black;
            margin-top: 50px;
            padding-top: 5px;
        }
        .signature-label {
            font-size: 10px;
            margin-top: 5px;
        }
        .signature-name {
            font-weight: bold;
            margin-top: 2px;
        }
        .right-align {
            text-align: right;
        }
        .center-align {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        PURCHASE REQUEST SUMMARY
    </div>
    
    <div class="info">
        <div class="info-row"><strong>Request ID:</strong> #<?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?></div>
        <div class="info-row"><strong>Office:</strong> <?php echo htmlspecialchars($request['office_name']); ?></div>
        <div class="info-row"><strong>Requested By:</strong> <?php echo htmlspecialchars($request['full_name']); ?> (<?php echo ucfirst($request['user_type']); ?>)</div>
        <div class="info-row"><strong>Date:</strong> <?php echo date('F j, Y, g:i A', strtotime($request['date_requested'])); ?></div>
        <div class="info-row"><strong>Status:</strong> <?php echo ucfirst($request['status']); ?></div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="center-align" width="5%">No.</th>
                <th width="40%">Item Name</th>
                <th class="center-align" width="15%">Unit Type</th>
                <th class="center-align" width="10%">Quantity</th>
                <th class="right-align" width="15%">Price/Unit</th>
                <th class="right-align" width="15%">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($items as $item): 
                $itemTotal = $item['quantity'] * $item['price_per_unit'];
            ?>
                <tr>
                    <td class="center-align"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td class="center-align"><?php echo htmlspecialchars($item['unit_type']); ?></td>
                    <td class="center-align"><?php echo number_format($item['quantity']); ?></td>
                    <td class="right-align"><?php echo formatCurrency($item['price_per_unit']); ?></td>
                    <td class="right-align"><?php echo formatCurrency($itemTotal); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="5" class="right-align"><strong>GRAND TOTAL:</strong></td>
                <td class="right-align"><strong><?php echo formatCurrency($total); ?></strong></td>
            </tr>
        </tbody>
    </table>
    
    <div class="signature-section">
        <table border="0" width="100%">
            <tr>
                <td width="25%" class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label"><?php echo htmlspecialchars($sig1_label); ?></div>
                    <?php if (!empty($sig1_name)): ?>
                        <div class="signature-name"><?php echo htmlspecialchars($sig1_name); ?></div>
                    <?php endif; ?>
                </td>
                <td width="25%" class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label"><?php echo htmlspecialchars($sig2_label); ?></div>
                    <?php if (!empty($sig2_name)): ?>
                        <div class="signature-name"><?php echo htmlspecialchars($sig2_name); ?></div>
                    <?php endif; ?>
                </td>
                <td width="25%" class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label"><?php echo htmlspecialchars($sig3_label); ?></div>
                    <?php if (!empty($sig3_name)): ?>
                        <div class="signature-name"><?php echo htmlspecialchars($sig3_name); ?></div>
                    <?php endif; ?>
                </td>
                <td width="25%" class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label"><?php echo htmlspecialchars($sig4_label); ?></div>
                    <?php if (!empty($sig4_name)): ?>
                        <div class="signature-name"><?php echo htmlspecialchars($sig4_name); ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div style="margin-top: 30px; font-size: 10px; color: #666;">
        <p>Generated on: <?php echo date('F j, Y, g:i A'); ?></p>
        <p>This is a system-generated document from the Purchase Request System.</p>
    </div>
</body>
</html>