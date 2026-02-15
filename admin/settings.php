<?php
/**
 * System Settings - NEW FILE
 * Manage signature fields for Excel exports
 */

require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: ../user/dashboard.php");
    exit();
}

$page_title = 'System Settings';
$page_subtitle = 'Configure signature fields for Excel exports';

global $db;
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    try {
        // Update all signature settings
        $settings = [
            'signature_1_label' => trim($_POST['signature_1_label'] ?? ''),
            'signature_1_name' => trim($_POST['signature_1_name'] ?? ''),
            'signature_2_label' => trim($_POST['signature_2_label'] ?? ''),
            'signature_2_name' => trim($_POST['signature_2_name'] ?? ''),
            'signature_3_label' => trim($_POST['signature_3_label'] ?? ''),
            'signature_3_name' => trim($_POST['signature_3_name'] ?? ''),
            'signature_4_label' => trim($_POST['signature_4_label'] ?? ''),
            'signature_4_name' => trim($_POST['signature_4_name'] ?? ''),
        ];
        
        $updated = 0;
        foreach ($settings as $key => $value) {
            if (updateSystemSetting($key, $value)) {
                $updated++;
            }
        }
        
        if ($updated > 0) {
            $success = 'Settings saved successfully!';
        } else {
            $error = 'No changes were made';
        }
    } catch(Exception $e) {
        $error = 'Error saving settings: ' . $e->getMessage();
    }
}

// Load current settings
$sig1_label = getSystemSetting('signature_1_label', 'Requested by');
$sig1_name = getSystemSetting('signature_1_name', '');
$sig2_label = getSystemSetting('signature_2_label', 'Approved by');
$sig2_name = getSystemSetting('signature_2_name', '');
$sig3_label = getSystemSetting('signature_3_label', 'Verified by');
$sig3_name = getSystemSetting('signature_3_name', '');
$sig4_label = getSystemSetting('signature_4_label', 'Received by');
$sig4_name = getSystemSetting('signature_4_name', '');

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3><i class="fas fa-cog"></i> <?php echo $page_title; ?></h3>
                <span class="header-subtitle"><?php echo $page_subtitle; ?></span>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-info">
            <p><i class="fas fa-info-circle"></i> These signature fields will appear at the bottom of Excel exports for bulk requests. Configure the labels and pre-filled names for each signatory.</p>
        </div>
        
        <form method="POST" action="" id="settingsForm">
            <div class="signature-settings">
                <!-- Signature 1 -->
                <div class="signature-block">
                    <h4><i class="fas fa-signature"></i> Signature Field 1</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="signature_1_label">Label *</label>
                            <input type="text" 
                                   id="signature_1_label" 
                                   name="signature_1_label" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($sig1_label); ?>"
                                   required
                                   placeholder="e.g., Requested by">
                            <small class="form-text">The label shown below the signature line</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="signature_1_name">Name (Optional)</label>
                            <input type="text" 
                                   id="signature_1_name" 
                                   name="signature_1_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($sig1_name); ?>"
                                   placeholder="e.g., John Doe">
                            <small class="form-text">Pre-filled name (leave blank for manual entry)</small>
                        </div>
                    </div>
                </div>
                
                <!-- Signature 2 -->
                <div class="signature-block">
                    <h4><i class="fas fa-signature"></i> Signature Field 2</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="signature_2_label">Label *</label>
                            <input type="text" 
                                   id="signature_2_label" 
                                   name="signature_2_label" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($sig2_label); ?>"
                                   required
                                   placeholder="e.g., Approved by">
                        </div>
                        
                        <div class="form-group">
                            <label for="signature_2_name">Name (Optional)</label>
                            <input type="text" 
                                   id="signature_2_name" 
                                   name="signature_2_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($sig2_name); ?>"
                                   placeholder="e.g., Jane Smith">
                        </div>
                    </div>
                </div>
                
                <!-- Signature 3 -->
                <div class="signature-block">
                    <h4><i class="fas fa-signature"></i> Signature Field 3</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="signature_3_label">Label *</label>
                            <input type="text" 
                                   id="signature_3_label" 
                                   name="signature_3_label" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($sig3_label); ?>"
                                   required
                                   placeholder="e.g., Verified by">
                        </div>
                        
                        <div class="form-group">
                            <label for="signature_3_name">Name (Optional)</label>
                            <input type="text" 
                                   id="signature_3_name" 
                                   name="signature_3_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($sig3_name); ?>"
                                   placeholder="e.g., Bob Johnson">
                        </div>
                    </div>
                </div>
                
                <!-- Signature 4 -->
                <div class="signature-block">
                    <h4><i class="fas fa-signature"></i> Signature Field 4</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="signature_4_label">Label *</label>
                            <input type="text" 
                                   id="signature_4_label" 
                                   name="signature_4_label" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($sig4_label); ?>"
                                   required
                                   placeholder="e.g., Received by">
                        </div>
                        
                        <div class="form-group">
                            <label for="signature_4_name">Name (Optional)</label>
                            <input type="text" 
                                   id="signature_4_name" 
                                   name="signature_4_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($sig4_name); ?>"
                                   placeholder="e.g., Alice Brown">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_settings" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <button type="button" class="btn btn-info" onclick="togglePreview()">
                    <i class="fas fa-eye"></i> Preview Signature Section
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Card -->
<div class="card" id="previewCard" style="display: none;">
    <div class="card-header">
        <h3><i class="fas fa-eye"></i> Signature Section Preview</h3>
    </div>
    <div class="card-body">
        <p>This is how the signature section will appear in Excel exports:</p>
        
        <div class="signature-preview">
            <div class="preview-signature-box">
                <div class="preview-signature-line"></div>
                <div class="preview-signature-label" id="preview_label_1">Requested by</div>
                <div class="preview-signature-name" id="preview_name_1"></div>
            </div>
            <div class="preview-signature-box">
                <div class="preview-signature-line"></div>
                <div class="preview-signature-label" id="preview_label_2">Approved by</div>
                <div class="preview-signature-name" id="preview_name_2"></div>
            </div>
            <div class="preview-signature-box">
                <div class="preview-signature-line"></div>
                <div class="preview-signature-label" id="preview_label_3">Verified by</div>
                <div class="preview-signature-name" id="preview_name_3"></div>
            </div>
            <div class="preview-signature-box">
                <div class="preview-signature-line"></div>
                <div class="preview-signature-label" id="preview_label_4">Received by</div>
                <div class="preview-signature-name" id="preview_name_4"></div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePreview() {
    const previewCard = document.getElementById('previewCard');
    const isVisible = previewCard.style.display !== 'none';
    
    if (isVisible) {
        previewCard.style.display = 'none';
    } else {
        // Update preview with current values
        for (let i = 1; i <= 4; i++) {
            const label = document.getElementById('signature_' + i + '_label').value;
            const name = document.getElementById('signature_' + i + '_name').value;
            
            document.getElementById('preview_label_' + i).textContent = label || 'Label ' + i;
            document.getElementById('preview_name_' + i).textContent = name;
            document.getElementById('preview_name_' + i).style.display = name ? 'block' : 'none';
        }
        
        previewCard.style.display = 'block';
        previewCard.scrollIntoView({ behavior: 'smooth' });
    }
}

// Real-time preview update
document.querySelectorAll('input[type="text"]').forEach(input => {
    input.addEventListener('input', function() {
        if (document.getElementById('previewCard').style.display !== 'none') {
            const fieldNum = this.id.match(/\d+/)[0];
            const isLabel = this.id.includes('label');
            
            if (isLabel) {
                document.getElementById('preview_label_' + fieldNum).textContent = this.value || 'Label ' + fieldNum;
            } else {
                const nameElement = document.getElementById('preview_name_' + fieldNum);
                nameElement.textContent = this.value;
                nameElement.style.display = this.value ? 'block' : 'none';
            }
        }
    });
});
</script>

<style>
.settings-info {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.signature-settings {
    display: flex;
    flex-direction: column;
    gap: 30px;
    margin-bottom: 30px;
}

.signature-block {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.signature-block h4 {
    color: #495057;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #dee2e6;
}

.signature-preview {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 40px 20px;
    background: #fff;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    margin-top: 20px;
}

.preview-signature-box {
    flex: 1;
    text-align: center;
}

.preview-signature-line {
    border-top: 2px solid #000;
    margin-bottom: 10px;
}

.preview-signature-label {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.preview-signature-name {
    font-weight: bold;
    color: #000;
    margin-top: 5px;
    min-height: 20px;
}

@media (max-width: 768px) {
    .signature-preview {
        flex-direction: column;
    }
}
</style>

<?php include '../includes/footer.php'; ?>