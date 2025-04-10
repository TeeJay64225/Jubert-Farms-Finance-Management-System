// Place this in a file called assets/js/invoice.js

document.addEventListener('DOMContentLoaded', function() {
    // Toggle form visibility
    window.toggleForm = function(formId) {
        const form = document.getElementById(formId);
        if (form.style.display === 'block') {
            form.style.display = 'none';
        } else {
            form.style.display = 'block';
        }
    };
    
    // Show receipt modal
    window.showReceiptModal = function(invoiceId, invoiceNo) {
        // Set the invoice ID in the receipt form
        document.getElementById('receipt_invoice_id').value = invoiceId;
        document.getElementById('receipt_invoice_no').textContent = invoiceNo;
        
        // Generate a receipt number
        document.getElementById('receipt_no').value = 'RCT-' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '-' + Math.floor(1000 + Math.random() * 9000);
        
        // Set current date as payment date
        document.getElementById('payment_date').valueAsDate = new Date();
        
        // Show the modal
        const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        receiptModal.show();
    };

    // Calculate item totals
    function calculateItemTotal(row) {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const total = quantity * price;
        row.querySelector('.item-total').value = total.toFixed(2);
        
        calculateInvoiceTotal();
    }
    
    // Calculate overall invoice totals
    function calculateInvoiceTotal() {
        const itemTotals = document.querySelectorAll('.item-total');
        let subtotal = 0;
        
        itemTotals.forEach(function(item) {
            subtotal += parseFloat(item.value) || 0;
        });
        
        const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
        const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
        
        const taxAmount = subtotal * (taxRate / 100);
        const total = subtotal + taxAmount - discountAmount;
        
        document.getElementById('subtotal').value = subtotal.toFixed(2);
        document.getElementById('tax_amount').value = taxAmount.toFixed(2);
        document.getElementById('total_amount').value = total.toFixed(2);
    }
    
    // Add new invoice item
    const addItemBtn = document.getElementById('addItemBtn');
    if (addItemBtn) {
        addItemBtn.addEventListener('click', function() {
            const invoiceItems = document.getElementById('invoiceItems');
            const firstItem = invoiceItems.querySelector('.invoice-item');
            const newItem = firstItem.cloneNode(true);
            
            // Clear input values
            newItem.querySelectorAll('input').forEach(input => {
                if (input.classList.contains('item-quantity')) {
                    input.value = 1;
                } else {
                    input.value = '';
                }
            });
            
            // Add event listeners to new row
            addItemRowEventListeners(newItem);
            
            invoiceItems.appendChild(newItem);
        });
    }
    
    // Function to add event listeners to an item row
    function addItemRowEventListeners(row) {
        const quantityInput = row.querySelector('.item-quantity');
        const priceInput = row.querySelector('.item-price');
        const removeBtn = row.querySelector('.remove-item');
        
        quantityInput.addEventListener('input', function() {
            calculateItemTotal(row);
        });
        
        priceInput.addEventListener('input', function() {
            calculateItemTotal(row);
        });
        
        removeBtn.addEventListener('click', function() {
            // Don't remove if it's the only item
            const itemRows = document.querySelectorAll('.invoice-item');
            if (itemRows.length > 1) {
                row.remove();
                calculateInvoiceTotal();
            }
        });
    }
    
    // Initialize event listeners for existing items
    const itemRows = document.querySelectorAll('.invoice-item');
    itemRows.forEach(function(row) {
        addItemRowEventListeners(row);
    });
    
    // Add event listeners for tax and discount inputs
    const taxRateInput = document.getElementById('tax_rate');
    const discountInput = document.getElementById('discount_amount');
    
    if (taxRateInput) {
        taxRateInput.addEventListener('input', calculateInvoiceTotal);
    }
    
    if (discountInput) {
        discountInput.addEventListener('input', calculateInvoiceTotal);
    }
    
    // Function to get status badge class
    window.getStatusBadgeClass = function(status) {
        switch(status) {
            case 'Paid':
                return 'bg-success';
            case 'Partial':
                return 'bg-warning';
            case 'Unpaid':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    };
});