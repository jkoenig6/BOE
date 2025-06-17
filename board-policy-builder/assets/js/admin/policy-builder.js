// File: assets/js/admin/policy-builder.js
// Complete Policy Builder JavaScript for Board Policy Builder Plugin

(function($) {
    'use strict';
    
    let policyBuilderApp = {
        
        init: function() {
            console.log('Policy Builder initializing...');
            console.log('Context:', window.policyBuilderContext || 'undefined');
            this.initEventHandlers();
            this.initPermalinkHandling();
        },
        
        generateSlug: function(title) {
            return title
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '') // Remove special characters
                .replace(/\s+/g, '-') // Replace spaces with hyphens
                .replace(/-+/g, '-') // Replace multiple hyphens with single
                .replace(/^-|-$/g, ''); // Remove leading/trailing hyphens
        },
        
        initPermalinkHandling: function() {
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
        },
        
        initEventHandlers: function() {
            // Save draft
            $('#save-draft').on('click', function(e) {
                e.preventDefault();
                policyBuilderApp.savePolicy('draft');
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
            
            // Handle Visual/Text editor toggle
            $(document).on('click', '#policy-content-tmce, #policy-content-html', function() {
                // Small delay to let the editor switch
                setTimeout(function() {
                    policyBuilderApp.syncEditorContent();
                }, 100);
            });
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
        
        syncEditorContent: function() {
            // Sync content between visual and text editors
            if (typeof tinymce !== 'undefined' && tinymce.get('policy-content')) {
                let editor = tinymce.get('policy-content');
                if (editor && !editor.isHidden()) {
                    // Visual editor is active
                    let content = editor.getContent();
                    $('#policy-content').val(content);
                }
            }
        },
        
        collectFormData: function() {
            // Get editor content - try multiple methods
            let policyContent = '';
            
            // Method 1: TinyMCE visual editor
            if (typeof tinymce !== 'undefined' && tinymce.get('policy-content')) {
                let editor = tinymce.get('policy-content');
                if (editor && !editor.isHidden()) {
                    policyContent = editor.getContent();
                } else {
                    // Text editor is active
                    policyContent = $('#policy-content').val();
                }
            } else {
                // Fallback to textarea value
                policyContent = $('#policy-content').val();
            }
            
            // Debug logging
            console.log('Collecting form data...');
            console.log('Policy content length:', policyContent.length);
            
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
            console.log('Saving policy with status:', status);
            
            let $saveBtn = this.getSaveButton(status);
            let originalText = $saveBtn.text();
            
            $saveBtn.text('Saving...').prop('disabled', true);
            
            // Sync editor content before collecting form data
            this.syncEditorContent();
            
            let formData = this.collectFormData();
            formData.status = status;
            formData.action = 'save_policy';
            formData.nonce = policyBuilder.nonce;
            
            console.log('Form data being sent:', formData);
            
            $.ajax({
                url: policyBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    console.log('Sending AJAX request...');
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    
                    if (response.success) {
                        let message = status === 'archived' ? 'Policy archived successfully!' : 
                                     status === 'publish' ? 'Policy published successfully!' : 
                                     'Policy saved successfully!';
                        policyBuilderApp.showNotification(message, 'success');
                        
                        // Update URL if this was a new post
                        if (response.data.policy_id && !policyBuilder.policyId) {
                            // For new policies, redirect to edit page
                            let editUrl = window.location.origin + '/wp-admin/admin.php?page=policy-builder-edit&policy_id=' + response.data.policy_id;
                            setTimeout(function() {
                                window.location.href = editUrl;
                            }, 1500);
                            return;
                        }
                        
                        // Update context if needed
                        if (window.policyBuilderContext) {
                            window.policyBuilderContext.isEditing = true;
                            window.policyBuilderContext.context = 'edit';
                        }
                        
                        // Reload page if status changed significantly
                        if (status === 'archived' || (status === 'publish' && originalText.includes('Archive'))) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }
                        
                    } else {
                        console.error('Save error:', response.data);
                        policyBuilderApp.showNotification('Error saving policy: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', xhr.responseText);
                    policyBuilderApp.showNotification('Network error while saving: ' + error, 'error');
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
            if (!policyBuilder.policyId) {
                console.log('No policy ID, skipping auto-save');
                return;
            }
            
            console.log('Auto-saving...');
            
            // Sync editor content before auto-saving
            this.syncEditorContent();
            
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
                        console.log('Auto-save successful');
                        // Subtle auto-save indication
                        $('.policy-details-column').addClass('saved');
                        setTimeout(function() {
                            $('.policy-details-column').removeClass('saved');
                        }, 1000);
                    } else {
                        console.error('Auto-save failed:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Auto-save AJAX error:', error);
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
        },
        
        // Debug function to check editor state
        debugEditor: function() {
            console.log('=== EDITOR DEBUG ===');
            console.log('TinyMCE available:', typeof tinymce !== 'undefined');
            
            if (typeof tinymce !== 'undefined') {
                let editor = tinymce.get('policy-content');
                console.log('Editor instance:', editor);
                
                if (editor) {
                    console.log('Editor hidden:', editor.isHidden());
                    console.log('Editor content length:', editor.getContent().length);
                }
            }
            
            console.log('Textarea value length:', $('#policy-content').val().length);
            console.log('==================');
        }
    };
    
    // Add error styling for validation
    $('<style>').prop('type', 'text/css').html(`
        .policy-field-group input.error,
        .policy-field-group select.error {
            border-color: #d63638 !important;
            box-shadow: 0 0 0 2px rgba(214, 54, 56, 0.2) !important;
        }
        
        .permalink-edit.error {
            border-color: #d63638 !important;
            background: #fcebea !important;
        }
        
        .policy-details-column.saved {
            border-color: #00a32a;
            background: #f0f8ff;
            transition: all 0.3s ease;
        }
        
        .policy-field-group input:focus,
        .policy-field-group select:focus,
        .policy-field-group textarea:focus {
            border-color: #0073aa;
            box-shadow: 0 0 0 2px rgba(0,115,170,0.2);
            outline: none;
        }
    `).appendTo('head');
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.policy-builder-wrap').length > 0) {
            console.log('Initializing Policy Builder...');
            console.log('policyBuilder object:', typeof policyBuilder !== 'undefined' ? policyBuilder : 'undefined');
            
            // Check if we have the required localized data
            if (typeof policyBuilder === 'undefined') {
                console.error('policyBuilder object not found! Check if the script is properly localized.');
                return;
            }
            
            policyBuilderApp.init();
            
            // Add debug function to global scope for troubleshooting
            window.debugPolicyBuilder = function() {
                policyBuilderApp.debugEditor();
            };
            
            console.log('Policy Builder initialized successfully');
            console.log('Use debugPolicyBuilder() in console for troubleshooting');
        }
    });
    
    // Handle page unload - save draft if there are unsaved changes
    $(window).on('beforeunload', function() {
        // Only show warning if there are actual changes
        if (policyBuilder.policyId && $('.policy-details-column').hasClass('has-changes')) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    // Track changes to show unsaved warning
    $(document).on('input change', '#policy-title, #policy-slug, #policy-code, #policy-title-field, #adopted-date, #last-revised-date, #policy-sections, #policy-content', function() {
        $('.policy-details-column').addClass('has-changes');
    });
    
    // Remove change tracking when saving
    $(document).on('click', '#save-draft, #save-published, #publish-policy', function() {
        $('.policy-details-column').removeClass('has-changes');
    });
    
    // Expose for external use and debugging
    window.policyBuilderApp = policyBuilderApp;
    
})(jQuery);