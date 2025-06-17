// Word Document Import with Mammoth.js - COMPLETE ENHANCED VERSION
// File: assets/js/admin/word-import.js

(function($) {
    'use strict';
    
    let wordImportApp = {
        
        init: function() {
            console.log('Word Import initializing...');
            this.initFileUpload();
            this.bindEvents();
        },
        
        initFileUpload: function() {
            // Create file input and button elements
            const fileInputHtml = `
                <div class="word-import-container">
                    <div class="word-import-header">
                        <h3>Import from Word</h3>
                        <p class="description">Upload a .docx file to convert and import into the policy content editor.</p>
                    </div>
                    <div class="word-import-controls">
                        <div class="file-upload-wrapper">
                            <input type="file" id="word-file-input" accept=".docx" style="display: none;">
                            <button type="button" id="select-word-file" class="button button-secondary">
                                <span class="dashicons dashicons-upload"></span>
                                Select Document
                            </button>
                            <span class="file-name-display"></span>
                        </div>
                        <button type="button" id="import-word-file" class="button button-primary" disabled>
                            <span class="dashicons dashicons-download"></span>
                            Import
                        </button>
                    </div>
                    <div class="word-import-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <p class="progress-text">Converting...</p>
                    </div>
                    <div class="word-import-messages"></div>
                </div>
            `;
            
            // Insert in the right sidebar after Actions box
            const actionsBox = $('.policy-actions-box');
            if (actionsBox.length) {
                actionsBox.after(fileInputHtml);
            } else {
                // Fallback: insert at the beginning of the actions column
                const actionsColumn = $('.policy-actions-column');
                if (actionsColumn.length) {
                    actionsColumn.prepend(fileInputHtml);
                }
            }
        },
        
        bindEvents: function() {
            const self = this;
            
            // File selection
            $(document).on('click', '#select-word-file', function() {
                $('#word-file-input').click();
            });
            
            // File input change
            $(document).on('change', '#word-file-input', function() {
                const file = this.files[0];
                if (file) {
                    self.handleFileSelection(file);
                }
            });
            
            // Import button
            $(document).on('click', '#import-word-file', function() {
                const file = $('#word-file-input')[0].files[0];
                if (file) {
                    self.importDocument(file);
                }
            });
        },
        
        handleFileSelection: function(file) {
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            // Validate file
            if (!file.name.toLowerCase().endsWith('.docx')) {
                this.showMessage('Please select a .docx file.', 'error');
                return;
            }
            
            if (file.size > maxSize) {
                this.showMessage('File size must be less than 10MB.', 'error');
                return;
            }
            
            // Update UI
            $('.file-name-display').text(file.name);
            $('#import-word-file').prop('disabled', false);
            this.clearMessages();
        },
        
        importDocument: function(file) {
            const self = this;
            
            // Show progress
            this.showProgress(true);
            this.clearMessages();
            
            // Create FileReader
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const arrayBuffer = e.target.result;
                
                // Enhanced mammoth.js configuration for better list and table handling
                const mammothOptions = {
                    // Enhanced style mapping for better list preservation
                    styleMap: [
                        // Preserve numbered lists with different numbering styles
                        "p[style-name='List Number'] => ol > li:fresh",
                        "p[style-name='List Number 2'] => ol > li:fresh", 
                        "p[style-name='List Number 3'] => ol > li:fresh",
                        "p[style-name='Numbered List'] => ol > li:fresh",
                        // Preserve bullet lists
                        "p[style-name='List Bullet'] => ul > li:fresh",
                        "p[style-name='List Bullet 2'] => ul > li:fresh",
                        // Handle legal reference formatting
                        "p[style-name='Legal Reference'] => p.legal-reference",
                        // Preserve table styles
                        "table => table.policy-table"
                    ],
                    
                    // Better image handling
                    convertImage: mammoth.images.imgElement(function(image) {
                        return image.read("base64").then(function(imageBuffer) {
                            return {
                                src: "data:" + image.contentType + ";base64," + imageBuffer
                            };
                        });
                    }),
                    
                    // Include all document elements
                    includeEmbeddedStyleMap: false,
                    includeDefaultStyleMap: true,
                    
                    // Transform functions for better handling
                    transformDocument: function(document) {
                        // This helps preserve more of the original structure
                        return document;
                    }
                };
                
                // Convert with enhanced mammoth configuration
                mammoth.convertToHtml({arrayBuffer: arrayBuffer}, mammothOptions)
                    .then(function(result) {
                        self.handleConversionSuccess(result);
                    })
                    .catch(function(error) {
                        self.handleConversionError(error);
                    });
            };
            
            reader.onerror = function() {
                self.handleConversionError(new Error('Failed to read file'));
            };
            
            reader.readAsArrayBuffer(file);
        },
        
        handleConversionSuccess: function(result) {
            let html = result.value;
            
            // Debug: Log what mammoth.js actually produced
            console.log('Raw mammoth.js output:', html);
            
            // Enhanced post-processing to fix the issues
            html = this.postProcessHTML(html);
            
            // Insert into TinyMCE editor
            this.insertIntoEditor(html);
            
            // Show success message with any warnings
            let message = 'Document imported successfully!';
            if (result.messages && result.messages.length > 0) {
                console.log('Mammoth conversion messages:', result.messages);
                const warnings = result.messages.filter(msg => msg.type === 'warning');
                if (warnings.length > 0) {
                    message += ` (${warnings.length} formatting warning${warnings.length > 1 ? 's' : ''} - check console for details)`;
                }
            }
            
            this.showMessage(message, 'success');
            this.showProgress(false);
        },
        
        handleConversionError: function(error) {
            console.error('Document conversion failed:', error);
            this.showMessage('Failed to convert document: ' + error.message, 'error');
            this.showProgress(false);
        },
        
        postProcessHTML: function(html) {
            // Enhanced post-processing to fix mammoth.js issues
            const $temp = $('<div>').html(html);
            
            // 1. Remove anchor symbols and weird artifacts
            this.removeArtifacts($temp);
            
            // 2. Fix numbered lists that lost their formatting
            this.fixNumberedLists($temp);
            
            // 3. Process and preserve tables
            this.preserveTables($temp);
            
            // 4. Clean up empty elements
            this.cleanupEmptyElements($temp);
            
            return $temp.html();
        },
        
        removeArtifacts: function($container) {
            // Remove anchor symbols and other weird artifacts
            $container.find('a').each(function() {
                const $link = $(this);
                const href = $link.attr('href');
                const text = $link.text().trim();
                
                // Remove anchors that are just symbols or empty
                if (!href || href.startsWith('#') || text === '' || /^[\u2693\u2600-\u26FF\u2700-\u27BF]$/.test(text)) {
                    $link.remove();
                } else if (!href.startsWith('http') && !href.startsWith('mailto:')) {
                    // Convert internal links to plain text
                    $link.replaceWith($link.text());
                }
            });
            
            // Remove other common artifacts
            $container.find('*').each(function() {
                const $el = $(this);
                const text = $el.text().trim();
                
                // Remove elements that are just symbols
                if (/^[\u2693\u2600-\u26FF\u2700-\u27BF]+$/.test(text) && text.length < 3) {
                    $el.remove();
                }
            });
        },
        
        fixNumberedLists: function($container) {
            // Find paragraphs that should be numbered list items
            const $paragraphs = $container.find('p');
            let currentList = null;
            let listItems = [];
            
            $paragraphs.each(function() {
                const $p = $(this);
                const text = $p.text().trim();
                
                // Check if this looks like a numbered list item
                const numberMatch = text.match(/^(\d+)\.\s*(.+)/);
                const legalRefMatch = text.match(/^([\d\.]+\s+U\.S\.C\.|C\.R\.S\.|C\.F\.R\.)\s/);
                
                if (numberMatch || legalRefMatch) {
                    // This is a numbered item
                    if (!currentList) {
                        currentList = $('<ol>');
                        if (numberMatch) {
                            const startNum = parseInt(numberMatch[1]);
                            if (startNum !== 1) {
                                currentList.attr('start', startNum);
                            }
                        }
                        listItems = [];
                    }
                    
                    // Create list item
                    let itemText = numberMatch ? numberMatch[2] : text;
                    const $li = $('<li>').html(itemText);
                    listItems.push({ $p: $p, $li: $li });
                    
                } else {
                    // Not a numbered item - finalize current list if exists
                    if (currentList && listItems.length > 0) {
                        // Add all items to the list
                        listItems.forEach(item => currentList.append(item.$li));
                        
                        // Replace the first paragraph with the list
                        listItems[0].$p.replaceWith(currentList);
                        
                        // Remove the other paragraphs
                        for (let i = 1; i < listItems.length; i++) {
                            listItems[i].$p.remove();
                        }
                        
                        currentList = null;
                        listItems = [];
                    }
                }
            });
            
            // Handle any remaining list at the end
            if (currentList && listItems.length > 0) {
                listItems.forEach(item => currentList.append(item.$li));
                listItems[0].$p.replaceWith(currentList);
                for (let i = 1; i < listItems.length; i++) {
                    listItems[i].$p.remove();
                }
            }
        },
        
        preserveTables: function($container) {
            // Ensure tables are properly structured and styled
            $container.find('table').each(function() {
                const $table = $(this);
                
                // Add policy table class for consistent styling
                $table.addClass('policy-table');
                
                // Ensure table has proper borders for policy documents
                $table.attr('border', '1');
                
                // If table doesn't have thead but first row has th elements
                if ($table.find('thead').length === 0 && $table.find('tr').first().find('th').length > 0) {
                    const $firstRow = $table.find('tr').first();
                    const $thead = $('<thead>').append($firstRow.clone());
                    $table.prepend($thead);
                    $firstRow.remove();
                }
                
                // Ensure tbody exists
                if ($table.find('tbody').length === 0) {
                    const $rows = $table.find('tr');
                    if ($rows.length > 0) {
                        const $tbody = $('<tbody>').append($rows);
                        $table.append($tbody);
                    }
                }
                
                // Convert th to td in tbody (mammoth sometimes misses this)
                $table.find('tbody th').each(function() {
                    const $th = $(this);
                    const $td = $('<td>').html($th.html());
                    // Copy attributes
                    $.each($th[0].attributes, function() {
                        $td.attr(this.name, this.value);
                    });
                    $th.replaceWith($td);
                });
            });
        },
        
        cleanupEmptyElements: function($container) {
            // Remove truly empty paragraphs
            $container.find('p').each(function() {
                const $p = $(this);
                if ($p.text().trim() === '' && $p.find('img, br, table').length === 0) {
                    $p.remove();
                }
            });
        },
        
        romanToDecimal: function(roman) {
            const romanNumerals = {
                'I': 1, 'V': 5, 'X': 10, 'L': 50, 'C': 100, 'D': 500, 'M': 1000,
                'i': 1, 'v': 5, 'x': 10, 'l': 50, 'c': 100, 'd': 500, 'm': 1000
            };
            
            let decimal = 0;
            for (let i = 0; i < roman.length; i++) {
                const current = romanNumerals[roman[i]];
                const next = romanNumerals[roman[i + 1]];
                
                if (next && current < next) {
                    decimal += next - current;
                    i++; // Skip next character
                } else {
                    decimal += current;
                }
            }
            
            return decimal;
        },
        
        insertIntoEditor: function(html) {
            // Insert into TinyMCE editor
            if (typeof tinymce !== 'undefined' && tinymce.get('policy-content')) {
                const editor = tinymce.get('policy-content');
                
                // Get current content
                const currentContent = editor.getContent();
                
                // Ask user if they want to replace or append
                let shouldReplace = false;
                if (currentContent.trim() !== '') {
                    shouldReplace = confirm(
                        'The editor already has content. Do you want to:\n\n' +
                        'OK = Replace all content with the imported document\n' +
                        'Cancel = Add the imported content to the end'
                    );
                }
                
                if (shouldReplace) {
                    editor.setContent(html);
                } else {
                    const separator = currentContent.trim() !== '' ? '<p>&nbsp;</p>' : '';
                    editor.setContent(currentContent + separator + html);
                }
                
                // Focus the editor
                editor.focus();
                
                // Trigger change event for auto-save
                editor.fire('change');
                
            } else {
                // Fallback to textarea
                const $textarea = $('#policy-content');
                if ($textarea.length) {
                    const currentContent = $textarea.val();
                    
                    let shouldReplace = false;
                    if (currentContent.trim() !== '') {
                        shouldReplace = confirm(
                            'The editor already has content. Do you want to replace it with the imported document?'
                        );
                    }
                    
                    if (shouldReplace) {
                        $textarea.val(html);
                    } else {
                        const separator = currentContent.trim() !== '' ? '\n\n' : '';
                        $textarea.val(currentContent + separator + html);
                    }
                    
                    $textarea.trigger('change');
                }
            }
        },
        
        showProgress: function(show) {
            const $progress = $('.word-import-progress');
            if (show) {
                $progress.show();
                // Animate progress bar
                const $fill = $('.progress-fill');
                $fill.css('width', '0%');
                setTimeout(() => $fill.css('width', '30%'), 100);
                setTimeout(() => $fill.css('width', '60%'), 500);
                setTimeout(() => $fill.css('width', '90%'), 1000);
            } else {
                $('.progress-fill').css('width', '100%');
                setTimeout(() => $progress.hide(), 300);
            }
        },
        
        showMessage: function(message, type) {
            const $messages = $('.word-import-messages');
            const alertClass = type === 'error' ? 'notice-error' : 'notice-success';
            
            const messageHtml = `
                <div class="notice ${alertClass} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;
            
            $messages.html(messageHtml);
            
            // Auto-dismiss success messages
            if (type === 'success') {
                setTimeout(() => {
                    $messages.find('.notice').fadeOut();
                }, 5000);
            }
            
            // Handle dismiss button
            $messages.find('.notice-dismiss').on('click', function() {
                $(this).closest('.notice').fadeOut();
            });
        },
        
        clearMessages: function() {
            $('.word-import-messages').empty();
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.policy-builder-wrap').length > 0) {
            console.log('Initializing Word Import...');
            
            // Check if mammoth is loaded
            if (typeof mammoth === 'undefined') {
                console.error('Mammoth.js not loaded! Word import will not work.');
                return;
            }
            
            wordImportApp.init();
            
            console.log('Word Import initialized successfully');
        }
    });
    
    // Expose for external use and debugging
    window.wordImportApp = wordImportApp;
    
})(jQuery);