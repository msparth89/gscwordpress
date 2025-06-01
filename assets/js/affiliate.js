jQuery(document).ready(function($) {
    console.log('GSC Affiliate JS: Document ready, starting script.');
    // Immediately disable buttons before any logic runs
    $('#verify-upi').prop('disabled', true);
    $('#save-profile').prop('disabled', true);
    
    // Store initial UPI ID value
    var initialUpiId = $('#upi_id').val();
    var originalUpiId = $('#upi_id').val();
    var isUpiVerified = false;
    var isUpiConfirmed = false;
    var hasProfileDataChanged = false; // Flag to track changes in non-UPI profile fields
    
    // If there's already a verified UPI ID (from existing data)
    if ($('#existing-verification').length > 0) {
        isUpiVerified = true;
        isUpiConfirmed = true;
    }
    
    // Function to check if verify button should be enabled
    function updateVerifyButton() {
        var currentUpiId = $('#upi_id').val().trim();
        var hasUpiChanged = currentUpiId !== originalUpiId;
        var hasValue = currentUpiId.length > 0;
        
        // Only enable verify button if UPI has changed from original value and has content
        $('#verify-upi').prop('disabled', !hasUpiChanged || !hasValue);
    }
    
    // Function to check if save should be enabled
    function updateSaveButton() {
        console.log('GSC Affiliate JS: updateSaveButton called.');
        var currentUpiId = $('#upi_id').val().trim();
        var hasUpiChanged = currentUpiId !== originalUpiId;
        console.log('  currentUpiId:', currentUpiId, 'originalUpiId:', originalUpiId, 'hasUpiChanged:', hasUpiChanged);
        console.log('  isUpiVerified:', isUpiVerified, 'isUpiConfirmed:', isUpiConfirmed);

        var shouldEnable = false;
        // currentUpiId and hasUpiChanged already defined above for logging
        
        if (hasUpiChanged) {
            // UPI ID has changed, requires verification and confirmation
            if (isUpiVerified && isUpiConfirmed) {
                shouldEnable = true;
            }
        } else {
            // UPI ID has NOT changed from original
            // Enable if other profile form fields have been modified
            if (hasProfileDataChanged) {
                shouldEnable = true;
            }
        }
        
        console.log('  Setting save-profile disabled to:', !shouldEnable);
        $('#save-profile').prop('disabled', !shouldEnable);
    }
    
    // Initialize button states
    updateVerifyButton();
    updateSaveButton();

    // Monitor changes in other profile fields
    $('#first_name, #last_name, #email').on('input', function() {
        hasProfileDataChanged = true;
        updateSaveButton();
    });
    
    // Monitor UPI ID changes
    $('#upi_id').on('input', function() {
        hasProfileDataChanged = true; // Changing UPI also means profile data changed
        var currentUpiId = $(this).val().trim();
        var hasUpiChanged = currentUpiId !== originalUpiId;
        
        if (hasUpiChanged) {
            // UPI changed from original value
            isUpiVerified = false;
            isUpiConfirmed = false;
            $('#upi-confirm-row').hide();
            $('#existing-verification').hide();
            $('#confirm_upi').prop('checked', false);
            $('#verified_account_name').val('');
            $('#upi-verification-status').removeClass('success error').empty();
        } else {
            // UPI restored to original value
            if ($('#existing-verification').length > 0) {
                isUpiVerified = true;
                isUpiConfirmed = true;
                $('#existing-verification').show();
            }
        }
        
        updateVerifyButton();
        updateSaveButton();
    });
    
    // Handle confirmation checkbox
    $('#confirm_upi').on('change', function() {
        isUpiConfirmed = $(this).is(':checked');
        updateSaveButton();
    });

        // --- NEW TAB LOGIC START ---
    function activateTab(tabId) {
        console.log('GSC Affiliate JS: activateTab called with tabId:', tabId);
        if (!tabId) return; // Do nothing if tabId is undefined

        // Update button active states
        $('.gsc-tab-button').removeClass('active');
        $(`.gsc-tab-button[data-tab="${tabId}"]`).addClass('active');

        // Hide all tab content then show the selected one
        console.log('GSC Affiliate JS: Hiding all .gsc-tab-content elements.');
        $('.gsc-tab-content').removeClass('active').hide();
        
        const $targetContent = $(`#gsc-tab-${tabId}`);
        console.log('GSC Affiliate JS: Target content selector:', `#gsc-tab-${tabId}`);
        console.log('GSC Affiliate JS: Target content element found:', $targetContent.length);
        
        if ($targetContent.length) {
            console.log('GSC Affiliate JS: Showing target content:', `#gsc-tab-${tabId}`);
            $targetContent.addClass('active').show();
        } else {
            console.error('GSC Affiliate JS: Target content not found for tabId:', tabId);
        }
 

        // Store active tab in session
        sessionStorage.setItem('gscActiveTab', tabId);
    }

    // Tab switching functionality
    $('.gsc-tab-button').on('click', function() {
        const tabId = $(this).data('tab');
        activateTab(tabId);
    });

    // Initialize tabs on page load
    // Hide all tab content initially to prevent flash of all content if CSS isn't hiding them by default
    $('.gsc-tab-content').hide(); 
    
    let initialTabId = sessionStorage.getItem('gscActiveTab');
    if (!initialTabId) {
        // If no tab stored, default to the one marked 'active' in HTML, or the first tab button.
        initialTabId = $('.gsc-tab-button.active').data('tab');
        if (!initialTabId) {
            initialTabId = $('.gsc-tab-button:first').data('tab');
        }
    }
    
    // Activate the determined initial tab
    if (initialTabId) {
        console.log('GSC Affiliate JS: Initializing with tabId:', initialTabId);
        activateTab(initialTabId);
    } else {
        // Fallback: if no tabs are defined in HTML, ensure all content remains hidden.
        // console.warn('GSC Affiliate: No initial tab found to activate.');
    }
    // --- NEW TAB LOGIC END ---

    // UPI Verification
    $('#verify-upi').on('click', function() {
        const upiId = $('#upi_id').val().trim();
        const $status = $('#upi-verification-status');
        const $button = $(this);

        if (!upiId) {
            $status.removeClass('success').addClass('error').text('Please enter a UPI ID');
            return;
        }

        $button.prop('disabled', true).text('Verifying...');
        $status.text('');

        $.ajax({
            url: gscAffiliateData.ajaxurl,
            type: 'POST',
            data: {
                action: 'gsc_verify_upi',
                upi_id: upiId,
                nonce: gscAffiliateData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Get account name from response
                    var accountName = '';
                    if (response.data && response.data.account_name) {
                        accountName = response.data.account_name;
                    }
                    
                    // Show verification success
                    $status.removeClass('error').addClass('success')
                           .html('✓ UPI ID verified successfully');
                    
                    // Store account name in hidden field
                    $('#verified_account_name').val(accountName);
                    
                    // Show confirmation message with account name
                    if (accountName) {
                        $('#upi-confirm-row').find('label').html(
                            'I confirm this is my correct UPI ID linked to <strong>' + 
                            accountName + '</strong>'
                        );
                    }
                    
                    // Show confirmation checkbox
                    $('#existing-verification').hide();
                    $('#upi-confirm-row').show();
                    $('#confirm_upi').prop('checked', false);
                    
                    // Update states
                    isUpiVerified = true;
                    isUpiConfirmed = false;
                    updateSaveButton();
                } else {
                    // Show error
                    var errorMsg = 'Verification failed';
                    if (response.data && response.data.error) {
                        errorMsg += ': ' + response.data.error;
                    }
                    
                    $status.removeClass('success').addClass('error')
                           .html('✗ ' + errorMsg);
                    
                    // Hide confirmation and update states
                    $('#upi-confirm-row').hide();
                    $('#confirm_upi').prop('checked', false);
                    $('#verified_account_name').val('');
                    isUpiVerified = false;
                    isUpiConfirmed = false;
                    updateSaveButton();
                }
            },
            error: function() {
                $status.removeClass('success').addClass('error')
                       .text('Error connecting to server. Please try again.');
                isUpiVerified = false;
                isUpiConfirmed = false;
                updateSaveButton();
            },
            complete: function() {
                $button.prop('disabled', false).text('Verify UPI');
            }
        });
    });

    // Profile Form Submission (using click handler on button instead of form submit)
    $('#save-profile').on('click', function() {
        var $saveStatus = $('.save-status');
        var $saveBtn = $(this);
        
        // Validate form fields
        var $form = $('#gsc-profile-form');
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return false;
        }
        
        // Check UPI confirmation if changed
        var currentUpiId = $('#upi_id').val().trim();
        if (currentUpiId !== originalUpiId && (!isUpiVerified || !isUpiConfirmed)) {
            $saveStatus.text('Please verify and confirm your UPI ID').addClass('show');
            setTimeout(function() {
                $saveStatus.removeClass('show');
            }, 3000);
            return false;
        }
        
        // Disable button during save
        $saveBtn.prop('disabled', true).text('Saving...');
        $saveStatus.removeClass('show');
        
        $.ajax({
            url: gscAffiliateData.ajaxurl,
            type: 'POST',
            data: {
                action: 'gsc_save_profile',
                nonce: gscAffiliateData.nonce,
                first_name: $('#first_name').val(),
                last_name: $('#last_name').val(),
                email: $('#email').val(),
                upi_id: currentUpiId,
                confirm_upi: $('#confirm_upi').is(':checked'),
                verified_account_name: $('#verified_account_name').val()
            },
            success: function(response) {
                if (response.success) {
                    $saveStatus.text('Profile updated successfully').addClass('show');
                    
                    // Update stored values after successful save
                    originalUpiId = currentUpiId;
                    
                    // If UPI was verified, show it as verified
                    if (isUpiVerified && $('#verified_account_name').val()) {
                        // Update the existing verification display
                        if ($('#existing-verification').length === 0) {
                            // Create verification display if it doesn't exist
                            var verificationHtml = '<div class="upi-confirmation" id="existing-verification">' +
                                '<p class="account-name">Verified Account: <strong>' + 
                                $('#verified_account_name').val() + '</strong></p></div>';
                            
                            $('.upi-field-group').after(verificationHtml);
                        } else {
                            $('#existing-verification').find('.account-name strong')
                                .text($('#verified_account_name').val());
                            $('#existing-verification').show();
                        }
                        
                        // Hide confirmation row
                        $('#upi-confirm-row').hide();
                    }
                    
                    // Reset status
                    isUpiVerified = true;
                    isUpiConfirmed = true;
                    
                    // Reset the verify button and update save button
                    updateVerifyButton();
                    updateSaveButton();
                    
                    // Hide status after 3 seconds
                    setTimeout(function() {
                        $saveStatus.removeClass('show');
                    }, 3000);
                } else {
                    $saveStatus.text(response.data || 'Error updating profile').addClass('show');
                }
            },
            error: function() {
                $saveStatus.text('Server error while saving profile').addClass('show');
            },
            complete: function() {
                $saveBtn.prop('disabled', false).text('Save Changes');
            }
        });
    });

    // Function to copy affiliate URL to clipboard
    window.copyAffiliateUrl = function() {
        var copyText = document.getElementById("affiliate-url");
        var shortcodeCopyText = document.getElementById("affiliate-url-shortcode"); // For shortcode

        if (copyText) {
            copyText.select();
            copyText.setSelectionRange(0, 99999); // For mobile devices
            try {
                var successful = document.execCommand('copy');
                var msg = successful ? 'Copied to clipboard!' : 'Copy failed.';
                // Optionally, provide feedback to the user, e.g., change button text or show a tooltip.
                console.log('GSC Affiliate JS: ' + msg + ' (Profile)');
            } catch (err) {
                console.error('GSC Affiliate JS: Error copying (Profile): ', err);
            }
        } else if (shortcodeCopyText) {
            shortcodeCopyText.select();
            shortcodeCopyText.setSelectionRange(0, 99999);
            try {
                var successful = document.execCommand('copy');
                var msg = successful ? 'Copied to clipboard!' : 'Copy failed.';
                console.log('GSC Affiliate JS: ' + msg + ' (Shortcode)');
            } catch (err) {
                console.error('GSC Affiliate JS: Error copying (Shortcode): ', err);
            }
        } else {
            console.error('GSC Affiliate JS: affiliate-url element not found for copying.');
        }
    }
});
