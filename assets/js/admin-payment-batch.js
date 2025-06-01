jQuery(document).ready(function($) {
    // Initialize select2 for referral selection
    $('.gsc-referral-select').select2({
        width: '100%',
        placeholder: 'Select referrals to process'
    });

    // Create batch modal
    $('.gsc-create-batch').on('click', function(e) {
        e.preventDefault();
        $('#gsc-create-batch-modal').dialog({
            modal: true,
            width: 500,
            closeOnEscape: true,
            draggable: false,
            resizable: false
        });
    });

    // Cancel batch creation
    $('.gsc-create-batch-cancel').on('click', function() {
        $('#gsc-create-batch-modal').dialog('close');
    });

    // Submit batch creation
    $('.gsc-create-batch-submit').on('click', function() {
        var referrals = $('.gsc-referral-select').val();
        
        if (!referrals || !referrals.length) {
            alert('Please select at least one referral');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gsc_create_payment_batch',
                nonce: gsc_admin.nonce,
                referrals: referrals
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    alert(response.data || 'Error creating batch');
                }
            },
            error: function() {
                alert('Error creating batch');
            }
        });
    });

    // Process batch
    $('.gsc-process-batch').on('click', function() {
        var $button = $(this);
        var batchId = $button.data('batch-id');
        
        if (!confirm('Are you sure you want to process this batch now?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gsc_process_batch',
                nonce: gsc_admin.nonce,
                batch_id: batchId
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data || 'Error processing batch');
                    $button.prop('disabled', false).text('Process Now');
                }
            },
            error: function() {
                alert('Error processing batch');
                $button.prop('disabled', false).text('Process Now');
            }
        });
    });
});
