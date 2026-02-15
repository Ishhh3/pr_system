document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initMobileMenu();
    initModals();
    initForms();
    initTables();
    initNotifications();
    initExportFeatures();
    
    // Global error handler
    window.addEventListener('error', handleGlobalError);
});

/**
 * Mobile Menu Toggle - IMPROVED VERSION
 */
function initMobileMenu() {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (!navToggle || !navMenu) return;
    
    // Toggle menu on button click
    navToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        navMenu.classList.toggle('show');
        
        // Update icon
        if (navMenu.classList.contains('show')) {
            this.innerHTML = '<i class="fas fa-times"></i>';
            this.setAttribute('aria-expanded', 'true');
        } else {
            this.innerHTML = '<i class="fas fa-bars"></i>';
            this.setAttribute('aria-expanded', 'false');
        }
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (navMenu.classList.contains('show')) {
            // Check if click is outside both menu and toggle button
            if (!navMenu.contains(event.target) && !navToggle.contains(event.target)) {
                navMenu.classList.remove('show');
                navToggle.innerHTML = '<i class="fas fa-bars"></i>';
                navToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });
    
    // Close menu when clicking on a nav link (mobile only)
    const navLinks = navMenu.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                navMenu.classList.remove('show');
                navToggle.innerHTML = '<i class="fas fa-bars"></i>';
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    });
    
    // Reset menu on window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992 && navMenu.classList.contains('show')) {
                navMenu.classList.remove('show');
                navToggle.innerHTML = '<i class="fas fa-bars"></i>';
                navToggle.setAttribute('aria-expanded', 'false');
            }
        }, 250);
    });
    
    // Prevent menu from staying open after orientation change
    window.addEventListener('orientationchange', function() {
        setTimeout(function() {
            if (window.innerWidth > 992 && navMenu.classList.contains('show')) {
                navMenu.classList.remove('show');
                navToggle.innerHTML = '<i class="fas fa-bars"></i>';
                navToggle.setAttribute('aria-expanded', 'false');
            }
        }, 200);
    });
}

/**
 * Modal System
 */
function initModals() {
    const modal = document.getElementById('passwordModal');
    const modalClose = document.querySelector('.modal-close');
    const cancelBtn = document.getElementById('cancelAction');
    
    // Close modal buttons
    if (modalClose) {
        modalClose.addEventListener('click', () => hideModal());
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => hideModal());
    }
    
    // Close modal when clicking outside
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                hideModal();
            }
        });
    }
    
    // Escape key to close modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal && modal.style.display === 'flex') {
            hideModal();
        }
    });
}

/**
 * Show modal with custom action
 */
function showModal(actionCallback) {
    const modal = document.getElementById('passwordModal');
    const confirmBtn = document.getElementById('confirmAction');
    const passwordInput = document.getElementById('confirmPassword');
    const errorDiv = document.getElementById('passwordError');
    
    if (!modal) return;
    
    // Reset modal
    passwordInput.value = '';
    errorDiv.textContent = '';
    errorDiv.style.display = 'none';
    
    // Set up confirm action
    confirmBtn.onclick = function() {
        const password = passwordInput.value.trim();
        
        if (!password) {
            showError('Please enter your password.');
            return;
        }
        
        // Call the action callback with password
        if (typeof actionCallback === 'function') {
            actionCallback(password);
        }
    };
    
    // Show modal
    modal.style.display = 'flex';
    passwordInput.focus();
}

/**
 * Hide modal
 */
function hideModal() {
    const modal = document.getElementById('passwordModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Form Validation and Enhancement
 */
function initForms() {
    // Real-time validation for quantity inputs
    const quantityInputs = document.querySelectorAll('input[type="number"][name="quantity"]');
    quantityInputs.forEach(input => {
        input.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (value < 1) {
                this.setCustomValidity('Quantity must be at least 1');
            } else if (value > 1000) {
                this.setCustomValidity('Quantity cannot exceed 1000');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Confirm password match for registration forms
    const passwordForms = document.querySelectorAll('form[data-confirm-password]');
    passwordForms.forEach(form => {
        const password = form.querySelector('input[name="password"]');
        const confirmPassword = form.querySelector('input[name="confirm_password"]');
        
        if (password && confirmPassword) {
            function validatePasswordMatch() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            password.addEventListener('input', validatePasswordMatch);
            confirmPassword.addEventListener('input', validatePasswordMatch);
        }
    });
    
    // Auto-save form data (except passwords)
    const saveableForms = document.querySelectorAll('.auto-save');
    saveableForms.forEach(form => {
        const inputs = form.querySelectorAll('input:not([type="password"]), textarea, select');
        const formId = form.id || 'unsaved_form';
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                saveFormState(formId, form);
            });
        });
        
        // Load saved state
        loadFormState(formId, form);
        
        // Clear saved state on submit
        form.addEventListener('submit', function() {
            localStorage.removeItem(`form_${formId}`);
        });
    });
}

/**
 * Save form state to localStorage
 */
function saveFormState(formId, form) {
    const formData = {};
    const inputs = form.querySelectorAll('input:not([type="password"]), textarea, select');
    
    inputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
            formData[input.name] = input.checked;
        } else {
            formData[input.name] = input.value;
        }
    });
    
    localStorage.setItem(`form_${formId}`, JSON.stringify(formData));
}

/**
 * Load form state from localStorage
 */
function loadFormState(formId, form) {
    const saved = localStorage.getItem(`form_${formId}`);
    if (!saved) return;
    
    try {
        const formData = JSON.parse(saved);
        Object.keys(formData).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = formData[key];
                } else {
                    input.value = formData[key];
                }
            }
        });
    } catch (e) {
        console.error('Error loading form state:', e);
    }
}

/**
 * Table Enhancements
 */
function initTables() {
    // Add sorting functionality to data tables
    const tables = document.querySelectorAll('.data-table.sortable');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                const isAsc = !this.classList.contains('asc');
                
                // Clear other sort indicators
                headers.forEach(h => {
                    h.classList.remove('asc', 'desc');
                });
                
                // Set current sort indicator
                this.classList.add(isAsc ? 'asc' : 'desc');
                
                // Sort table
                sortTable(table, column, isAsc);
            });
        });
    });
    
    // Row selection for batch operations
    const selectableTables = document.querySelectorAll('.data-table.selectable');
    selectableTables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            row.addEventListener('click', function(event) {
                if (event.target.type !== 'checkbox' && event.target.tagName !== 'A') {
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        this.classList.toggle('selected', checkbox.checked);
                    }
                }
            });
        });
    });
}

/**
 * Sort table by column
 */
function sortTable(table, column, ascending = true) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const colIndex = Array.from(table.querySelectorAll('th')).findIndex(th => 
        th.getAttribute('data-sort') === column
    );
    
    rows.sort((a, b) => {
        const aVal = a.children[colIndex].textContent.trim();
        const bVal = b.children[colIndex].textContent.trim();
        
        // Try to compare as numbers
        const aNum = parseFloat(aVal.replace(/[^0-9.-]+/g, ''));
        const bNum = parseFloat(bVal.replace(/[^0-9.-]+/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return ascending ? aNum - bNum : bNum - aNum;
        }
        
        // Compare as strings
        return ascending 
            ? aVal.localeCompare(bVal)
            : bVal.localeCompare(aVal);
    });
    
    // Reorder rows
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Notification System
 */
function initNotifications() {
    // Auto-dismiss alerts after 5 seconds
    const autoDismissAlerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    autoDismissAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Toast notifications
    window.showToast = function(message, type = 'success', duration = 3000) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        
        toast.textContent = message;
        toast.className = 'toast';
        toast.classList.add(type);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    };
}

/**
 * Export Features
 */
function initExportFeatures() {
    // Export form handling
    const exportForms = document.querySelectorAll('form[data-export]');
    exportForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const formData = new FormData(this);
            const params = new URLSearchParams();
            
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }
            
            // Show loading indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Exporting...';
            submitBtn.disabled = true;
            
            // Trigger download
            const url = this.action + '?' + params.toString();
            window.location.href = url;
            
            // Reset button after delay
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
    });
    
    // Quick export buttons
    const quickExportBtns = document.querySelectorAll('[data-quick-export]');
    quickExportBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-quick-export');
            const dateFrom = this.getAttribute('data-date-from');
            const dateTo = this.getAttribute('data-date-to');
            
            const url = `export.php?export=1&type=${type}` +
                       (dateFrom ? `&date_from=${dateFrom}` : '') +
                       (dateTo ? `&date_to=${dateTo}` : '');
            
            window.location.href = url;
        });
    });
}

/**
 * Error Handling
 */
function showError(message, element = null) {
    const errorDiv = element || document.getElementById('passwordError');
    if (!errorDiv) return;
    
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    errorDiv.style.color = '#e74c3c';
    errorDiv.style.fontSize = '0.9rem';
    errorDiv.style.marginTop = '5px';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        errorDiv.style.display = 'none';
    }, 5000);
}

function handleGlobalError(event) {
    console.error('Global error:', event.error);
    
    // Don't show error messages for network requests
    if (event.message && event.message.includes('fetch')) {
        return;
    }
    
    // Show user-friendly error message
    showToast('An unexpected error occurred. Please try again.', 'error');
}

/**
 * AJAX Helper Functions
 */
function makeRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    };
    
    const mergedOptions = { ...defaultOptions, ...options };
    
    return fetch(url, mergedOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            return response.text();
        })
        .catch(error => {
            console.error('Request failed:', error);
            throw error;
        });
}

/**
 * Utility Functions
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

/**
 * Security Functions
 */
function validatePassword(password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    return regex.test(password);
}

function sanitizeInput(input) {
    const div = document.createElement('div');
    div.textContent = input;
    return div.innerHTML;
}

/**
 * File Upload Helpers
 */
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file && preview) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

/**
 * Print Functionality
 */
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    @media print {
                        @page { margin: 0.5in; }
                    }
                </style>
            </head>
            <body>
                ${element.innerHTML}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}

/**
 * Copy to Clipboard
 */
function copyToClipboard(text, showNotification = true) {
    navigator.clipboard.writeText(text).then(() => {
        if (showNotification) {
            showToast('Copied to clipboard!', 'success');
        }
    }).catch(err => {
        console.error('Failed to copy:', err);
        if (showNotification) {
            showToast('Failed to copy to clipboard', 'error');
        }
    });
}

/**
 * Initialize tooltips
 */
function initTooltips() {
    const elements = document.querySelectorAll('[data-tooltip]');
    
    elements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.position = 'absolute';
            tooltip.style.background = '#333';
            tooltip.style.color = 'white';
            tooltip.style.padding = '5px 10px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '0.85rem';
            tooltip.style.zIndex = '1000';
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
            tooltip.style.left = (rect.left + (rect.width - tooltip.offsetWidth) / 2) + 'px';
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

// Initialize tooltips when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTooltips);
} else {
    initTooltips();
}