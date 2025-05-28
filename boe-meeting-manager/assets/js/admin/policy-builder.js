// File: assets/policy-builder.js

(function($) {
    'use strict';
    
    let policyBuilderApp = {
        
        init: function() {
            this.initEventHandlers();
        },
        
        generateSlug: function(title) {
            return title
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '') // Remove special characters
                .replace(/\s+/g, '-') // Replace spaces with hyphens
                .replace(/-+/g, '-') // Replace multiple hyphens with single
                .replace(/^-|-$/g, ''); // Remove leading/trailing hyphens
        },
        
        initEventHandlers: function() {
            // Save draft
            $('#save-draft').on('click', function(e) {
                e.preventDefault();
                policyBuilderApp.savePolicy('draft');
            // Auto-sync slug with code field
            $('#policy-code').on('input', function() {
                let code = $(this).val();
                if (code) {
                    // Convert code to URL-friendly format
                    let slug = code.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
                    $('#policy-slug').val(slug);
                }
            });
            
            // Auto-generate slug from title (fallback if no code)
            $('#policy-title').on('input', function() {
                let title = $(this).val();
                let currentSlug = $('#policy-slug').val();
                let currentCode = $('#policy-code').val();
                
                // Only auto-generate from title if no code is set and slug is empty
                if (!currentCode && (!currentSlug || currentSlug === policyBuilderApp.previousTitleSlug)) {
                    let newSlug = policyBuilderApp.generateSlug(title);
                    $('#policy-slug').val(newSlug);
                    policyBuilderApp.previousTitleSlug = newSlug;
                }
            });
            
            // Validate slug on input
            $('#policy-slug').on('input', function() {
                let slug = $(this).val();
                let $field = $(this);
                
                if (slug && !policyBuilderApp.isValidSlug(slug)) {
                    $field.addClass('error');
                    $('.permalink-edit').addClass('error');
                } else {
                    $field.removeClass('error');
                    $('.permalink-edit').removeClass('error');
                }
            });
            
            // Save archived
            $('#save-archived').on('click', function(e) {
                e.preventDefault();
                policyBuilderApp.savePolicy('archived');
            });
            
            // Publish policy
            $('#publish-policy').on('click', function(e) {
                e.preventDefault();
                if (policyBuilderApp.validateRequired()) {
                    policyBuilderApp.savePolicy('publish');
                }
            });
            
            // Save published
            $('#save-published').on('click', function(e) {
                e.preventDefault();
                policyBuilderApp.savePolicy('publish');
            });
            
            // Archive policy
            $('#archive-policy').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to archive this policy? It will be marked as rescinded.')) {
                    policyBuilderApp.savePolicy('archived');
                }
            });
            
            // Restore policy
            $('#restore-policy').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to restore this policy from archived status?')) {
                    policyBuilderApp.savePolicy('publish');
                }
            });
            
            // Auto-save on input changes (debounced)
            let autoSaveTimeout;
            $(document).on('input change', '#policy-title, #policy-slug, #policy-code, #policy-title-field, #adopted-date, #last-revised-date, #policy-sections', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(function() {
                    policyBuilderApp.autoSave();
                }, 3000); // 3 second delay
            });
            
            // Auto-save on editor content change
            if (typeof tinymce !== 'undefined') {
                tinymce.on('AddEditor', function(e) {
                    if (e.editor.id === 'policy-content') {
                        e.editor.on('input change', function() {
                            clearTimeout(autoSaveTimeout);
                            autoSaveTimeout = setTimeout(function() {
                                policyBuilderApp.autoSave();
                            }, 3000);
                        });
                    }
                });
            }
        },
        
        validateRequired: function() {
            let isValid = true;
            let firstInvalidField = null;
            
            // Check required fields
            let requiredFields = [
                { id: '#policy-code', name: 'Code' },
                { id: '#policy-title-field', name: 'Title' }
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
            
            // Validate permalink slug
            let $slugField = $('#policy-slug');
            let slugValue = $slugField.val();
            if (slugValue && !this.isValidSlug(slugValue)) {
                isValid = false;
                $slugField.addClass('error');
                policyBuilderApp.showNotification('URL slug must contain only letters, numbers, and hyphens.', 'error');
                
                if (!firstInvalidField) {
                    firstInvalidField = $slugField;
                }
            } else {
                $slugField.removeClass('error');
            }
            
            if (!isValid) {
                if (!slugValue || !this.isValidSlug(slugValue)) {
                    // Slug error already shown above
                } else {
                    policyBuilderApp.showNotification('Please fill in all required fields.', 'error');
                }
                
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
            }
            
            return isValid;
        },
        
        isValidSlug: function(slug) {
            // Check if slug contains only valid characters (letters, numbers, hyphens)
            return /^[a-z0-9-]+$/i.test(slug);
        },
        
        collectFormData: function() {
            // Get editor content
            let policyContent = '';
            if (typeof tinymce !== 'undefined' && tinymce.get('policy-content')) {
                policyContent = tinymce.get('policy-content').getContent();
            } else {
                policyContent = $('#policy-content').val();
            }
            
            return {
                policy_id: $('#policy-id').val(),
                title: $('#policy-title').val(),
                slug: $('#policy-slug').val(),
                code: $('#policy-code').val(),
                title_field: $('#policy-title-field').val(),
                adopted_date: $('#adopted-date').val(),
                last_revised_date: $('#last-revised-date').val(),
                policy_content: policyContent,
                policy_sections: $('#policy-sections').val() || []
            };
        },
        
        savePolicy: function(status) {
            let $saveBtn = this.getSaveButton(status);
            let originalText = $saveBtn.text();
            
            $saveBtn.text('Saving...').prop('disabled', true);
            
            let formData = this.collectFormData();
            formData.status = status;
            formData.action = 'save_policy';
            formData.nonce = policyBuilder.nonce;
            
            $.ajax({
                url: policyBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        let message = status === 'archived' ? 'Policy archived successfully!' : 
                                     status === 'publish' ? 'Policy published successfully!' : 
                                     'Policy saved successfully!';
                        policyBuilderApp.showNotification(message, 'success');
                        
                        // Update URL if this was a new post
                        if (response.data.policy_id && !policyBuilder.policyId) {
                            let newUrl = window.location.href + '&policy_id=' + response.data.policy_id;
                            window.history.replaceState({}, '', newUrl);
                            $('#policy-id').val(response.data.policy_id);
                            policyBuilder.policyId = response.data.policy_id;
                        }
                        
                        // Reload page if status changed significantly
                        if (status === 'archived' || (status === 'publish' && originalText.includes('Archive'))) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }
                        
                    } else {
                        policyBuilderApp.showNotification('Error saving policy: ' + response.data, 'error');
                    }
                },
                error: function() {
                    policyBuilderApp.showNotification('Network error while saving', 'error');
                },
                complete: function() {
                    $saveBtn.text(originalText).prop('disabled', false);
                }
            });
        },
        
        getSaveButton: function(status) {
            switch (status) {
                case 'archived':
                    return $('#save-archived, #archive-policy');
                case 'publish':
                    return $('#save-published, #publish-policy, #restore-policy');
                default:
                    return $('#save-draft');
            }
        },
        
        autoSave: function() {
            // Only auto-save if we have a policy ID and it's still a draft
            if (!policyBuilder.policyId) return;
            
            let formData = this.collectFormData();
            formData.status = 'draft'; // Always auto-save as draft
            formData.action = 'save_policy';
            formData.nonce = policyBuilder.nonce;
            
            $.ajax({
                url: policyBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Subtle auto-save indication
                        $('.policy-details-column').addClass('saved');
                        setTimeout(function() {
                            $('.policy-details-column').removeClass('saved');
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
        .policy-field-group input.error,
        .policy-field-group select.error {
            border-color: #d63638 !important;
            box-shadow: 0 0 0 2px rgba(214, 54, 56, 0.2) !important;
        }
        
        .policy-details-column.saved {
            border-color: #00a32a;
            background: #f0f8ff;
        }
    `).appendTo('head');
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.policy-builder-wrap').length > 0) {
            console.log('Initializing Policy Builder...');
            policyBuilderApp.init();
        }
    });
    
    // Expose for external use
    window.policyBuilderApp = policyBuilderApp;
    
})(jQuery);