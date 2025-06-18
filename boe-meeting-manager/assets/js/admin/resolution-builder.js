// File: assets/resolution-builder.js

(function($) {
    'use strict';
    
    let resolutionBuilderApp = {
        
        init: function() {
            this.initEventHandlers();
            this.initFiscalToggle();
        },
        
        initEventHandlers: function() {
            // Save draft
            $('#save-draft').on('click', function(e) {
                e.preventDefault();
                resolutionBuilderApp.saveResolution('draft');
            });
            
            // Submit for approval
            $('#submit-approval').on('click', function(e) {
                e.preventDefault();
                if (resolutionBuilderApp.validateRequired()) {
                    resolutionBuilderApp.submitForApproval();
                }
            });
            
            // Resubmit for approval (from denied status)
            $('#resubmit-approval').on('click', function(e) {
                e.preventDefault();
                if (resolutionBuilderApp.validateRequired()) {
                    resolutionBuilderApp.submitForApproval();
                }
            });
            
            // Publish resolution
            $('#publish-resolution').on('click', function(e) {
                e.preventDefault();
                resolutionBuilderApp.publishResolution();
            });
            
            // Update published
            $('#save-published').on('click', function(e) {
                e.preventDefault();
                resolutionBuilderApp.saveResolution('publish');
            });
            
            // Approve resolution (from pending list)
            $(document).on('click', '.approve-resolution', function(e) {
                e.preventDefault();
                let resolutionId = $(this).data('resolution-id');
                console.log('Approve button clicked for resolution:', resolutionId); // Debug
                resolutionBuilderApp.approveResolution(resolutionId);
            });
            
            // Deny resolution (from pending list)
            $(document).on('click', '.deny-resolution', function(e) {
                e.preventDefault();
                let resolutionId = $(this).data('resolution-id');
                console.log('Deny button clicked for resolution:', resolutionId); // Debug
                if (confirm('Are you sure you want to deny this resolution?')) {
                    resolutionBuilderApp.denyResolution(resolutionId);
                }
            });
            
            // Auto-save on input changes (debounced)
            let autoSaveTimeout;
            $(document).on('input change', '#resolution-title, #resolution-subject, #assigned-meeting, #fiscal-impact, #budgeted, #amount, #budget-source, #recommended-action, #resolution-text', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(function() {
                    resolutionBuilderApp.autoSave();
                }, 3000); // 3 second delay
            });
        },
        
        initFiscalToggle: function() {
            // Handle fiscal impact toggle
            $('#fiscal-impact').on('change', function() {
                let $fiscalDetails = $('.fiscal-details');
                if ($(this).is(':checked')) {
                    $fiscalDetails.slideDown(200);
                } else {
                    $fiscalDetails.slideUp(200);
                    // Clear fiscal fields when hiding
                    $('#budgeted').prop('checked', false);
                    $('#amount').val('');
                    $('#budget-source').val('');
                }
            });
        },
        
        validateRequired: function() {
            let isValid = true;
            let firstInvalidField = null;
            
            // Check required fields
            let requiredFields = [
                { id: '#resolution-subject', name: 'Subject' },
                { id: '#assigned-meeting', name: 'Assigned Meeting' }
            ];
            
            requiredFields.forEach(function(field) {
                let $field = $(field.id);
                let value = $field.val();
                
                if (!value || value.trim() === '') {
                    isValid = false;
                    $field.addClass('error');
                    
                    if (!firstInvalidField) {
                        firstInvalidField = $field;
                    }
                } else {
                    $field.removeClass('error');
                }
            });
            
            if (!isValid) {
                resolutionBuilderApp.showNotification('Please fill in all required fields.', 'error');
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
            }
            
            return isValid;
        },
        
        collectFormData: function() {
            return {
                resolution_id: $('#resolution-id').val(),
                title: $('#resolution-title').val(),
                subject: $('#resolution-subject').val(),
                assigned_meeting: $('#assigned-meeting').val(),
                fiscal_impact: $('#fiscal-impact').is(':checked') ? 1 : 0,
                budgeted: $('#budgeted').is(':checked') ? 1 : 0,
                amount: $('#amount').val() || 0,
                budget_source: $('#budget-source').val(),
                recommended_action: $('#recommended-action').val(),
                resolution_text: $('#resolution-text').val()
            };
        },
        
        saveResolution: function(status) {
            let $saveBtn = status === 'publish' ? $('#save-published') : $('#save-draft');
            let originalText = $saveBtn.text();
            
            $saveBtn.text('Saving...').prop('disabled', true);
            
            let formData = this.collectFormData();
            formData.status = status;
            formData.action = 'save_resolution';
            formData.nonce = resolutionBuilder.nonce;
            
            $.ajax({
                url: resolutionBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        resolutionBuilderApp.showNotification('Resolution saved successfully!', 'success');
                        
                        // Update URL if this was a new post
                        if (response.data.resolution_id && !resolutionBuilder.resolutionId) {
                            let newUrl = window.location.href + '&resolution_id=' + response.data.resolution_id;
                            window.history.replaceState({}, '', newUrl);
                            $('#resolution-id').val(response.data.resolution_id);
                            resolutionBuilder.resolutionId = response.data.resolution_id;
                        }
                        
                    } else {
                        resolutionBuilderApp.showNotification('Error saving resolution: ' + response.data, 'error');
                    }
                },
                error: function() {
                    resolutionBuilderApp.showNotification('Network error while saving', 'error');
                },
                complete: function() {
                    $saveBtn.text(originalText).prop('disabled', false);
                }
            });
        },
        
        submitForApproval: function() {
            let $submitBtn = $('#submit-approval, #resubmit-approval');
            let originalText = $submitBtn.text();
            
            $submitBtn.text('Submitting...').prop('disabled', true);
            
            // First save the current data
            let formData = this.collectFormData();
            formData.status = 'draft'; // Save as draft first
            formData.action = 'save_resolution';
            formData.nonce = resolutionBuilder.nonce;
            
            $.ajax({
                url: resolutionBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Then submit for approval
                        $.ajax({
                            url: resolutionBuilder.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'submit_for_approval',
                                resolution_id: $('#resolution-id').val(),
                                nonce: resolutionBuilder.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    resolutionBuilderApp.showNotification('Resolution submitted for approval!', 'success');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 1500);
                                } else {
                                    resolutionBuilderApp.showNotification('Error submitting resolution: ' + response.data, 'error');
                                }
                            },
                            complete: function() {
                                $submitBtn.text(originalText).prop('disabled', false);
                            }
                        });
                    } else {
                        resolutionBuilderApp.showNotification('Error saving resolution: ' + response.data, 'error');
                        $submitBtn.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    resolutionBuilderApp.showNotification('Network error while saving', 'error');
                    $submitBtn.text(originalText).prop('disabled', false);
                }
            });
        },
        
        publishResolution: function() {
            let $publishBtn = $('#publish-resolution');
            let originalText = $publishBtn.text();
            
            $publishBtn.text('Publishing...').prop('disabled', true);
            
            let formData = this.collectFormData();
            formData.status = 'publish';
            formData.action = 'save_resolution';
            formData.nonce = resolutionBuilder.nonce;
            
            $.ajax({
                url: resolutionBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        resolutionBuilderApp.showNotification('Resolution published successfully!', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        resolutionBuilderApp.showNotification('Error publishing resolution: ' + response.data, 'error');
                    }
                },
                error: function() {
                    resolutionBuilderApp.showNotification('Network error while publishing', 'error');
                },
                complete: function() {
                    $publishBtn.text(originalText).prop('disabled', false);
                }
            });
        },
        
        approveResolution: function(resolutionId) {
            console.log('Starting approval for resolution:', resolutionId); // Debug
            console.log('Ajax URL:', resolutionBuilder.ajaxUrl); // Debug
            console.log('Nonce:', resolutionBuilder.nonce); // Debug
            
            $.ajax({
                url: resolutionBuilder.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'approve_resolution',
                    resolution_id: resolutionId,
                    nonce: resolutionBuilder.nonce
                },
                beforeSend: function() {
                    console.log('Sending approval request...'); // Debug
                },
                success: function(response) {
                    console.log('Approval response:', response); // Debug
                    if (response.success) {
                        resolutionBuilderApp.showNotification('Resolution approved!', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        resolutionBuilderApp.showNotification('Error approving resolution: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Approval error:', xhr.responseText); // Debug
                    resolutionBuilderApp.showNotification('Network error while approving: ' + error, 'error');
                }
            });
        },
        
        denyResolution: function(resolutionId) {
            console.log('Starting denial for resolution:', resolutionId); // Debug
            
            $.ajax({
                url: resolutionBuilder.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'deny_resolution',
                    resolution_id: resolutionId,
                    nonce: resolutionBuilder.nonce
                },
                beforeSend: function() {
                    console.log('Sending denial request...'); // Debug
                },
                success: function(response) {
                    console.log('Denial response:', response); // Debug
                    if (response.success) {
                        resolutionBuilderApp.showNotification('Resolution denied.', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        resolutionBuilderApp.showNotification('Error denying resolution: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Denial error:', xhr.responseText); // Debug
                    resolutionBuilderApp.showNotification('Network error while denying: ' + error, 'error');
                }
            });
        },
        
        autoSave: function() {
            // Only auto-save if we have a resolution ID and it's still a draft
            if (!resolutionBuilder.resolutionId) return;
            
            let formData = this.collectFormData();
            formData.status = 'draft';
            formData.action = 'save_resolution';
            formData.nonce = resolutionBuilder.nonce;
            
            $.ajax({
                url: resolutionBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Subtle auto-save indication
                        $('.resolution-details-column').addClass('saved');
                        setTimeout(function() {
                            $('.resolution-details-column').removeClass('saved');
                        }, 1000);
                    }
                }
            });
        },
        
        showNotification: function(message, type) {
            let className = type === 'success' ? 'notice-success' : 'notice-error';
            let $notice = $(`
                <div class="notice ${className} is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 350px;">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss"></button>
                </div>
            `);
            
            $('body').append($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual dismiss
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Add error styling for validation
    $('<style>').prop('type', 'text/css').html(`
        .resolution-field-group input.error,
        .resolution-field-group select.error {
            border-color: #d63638 !important;
            box-shadow: 0 0 0 2px rgba(214, 54, 56, 0.2) !important;
        }
        
        .resolution-details-column.saved {
            border-color: #00a32a;
            background: #f0f8ff;
        }
    `).appendTo('head');
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.resolution-builder-wrap').length > 0 || $('.pending-resolutions-list').length > 0) {
            console.log('Initializing Resolution Builder...'); // Debug
            console.log('resolutionBuilder object:', typeof resolutionBuilder !== 'undefined' ? resolutionBuilder : 'undefined'); // Debug
            resolutionBuilderApp.init();
        }
    });
    
    // Expose for external use
    window.resolutionBuilderApp = resolutionBuilderApp;
    
})(jQuery);