// Word Document Import with Mammoth.js
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
                
                // Let mammoth.js handle the conversion naturally
                const mammothOptions = {
                    // Minimal configuration - let mammoth do its job
                    convertImage: mammoth.images.imgElement(function(image) {
                        return image.read("base64").then(function(imageBuffer) {
                            return {
                                src: "data:" + image.contentType + ";base64," + imageBuffer
                            };
                        });
                    })
                };
                
                // Convert with mammoth
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
            
            // Minimal post-processing
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
            // Minimal post-processing - let mammoth.js output speak for itself
            const $temp = $('<div>').html(html);
            
            // Only do essential cleanup
            this.cleanupTables($temp);
            this.cleanupEmptyElements($temp);
            
            return $temp.html();
        },
        
        cleanupTables: function($container) {
            // Add policy table class for styling
            $container.find('table').each(function() {
                $(this).addClass('policy-table');
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
        
        processLists: function($container) {
            // Find consecutive paragraphs that should be list items
            const $listParagraphs = $container.find('p').filter(function() {
                const text = $(this).text().trim();
                // Look for numbered lists (1., 2., a., i., etc.) or bullet points
                return /^(\d+\.|[a-z]\.|[ivx]+\.)|\u2022|\u25CF|\u25AA|\u2013|\u2014|-/.test(text);
            });
            
            if ($listParagraphs.length === 0) return;
            
            // Group consecutive list paragraphs
            let currentGroup = [];
            let groups = [];
            
            $listParagraphs.each(function(index) {
                const $this = $(this);
                const text = $this.text().trim();
                
                if (index === 0) {
                    currentGroup = [$this];
                } else {
                    const prevElement = $listParagraphs.eq(index - 1)[0];
                    const thisElement = this;
                    
                    // Check if elements are consecutive siblings
                    if ($(prevElement).next()[0] === thisElement) {
                        currentGroup.push($this);
                    } else {
                        if (currentGroup.length > 0) {
                            groups.push(currentGroup);
                        }
                        currentGroup = [$this];
                    }
                }
                
                if (index === $listParagraphs.length - 1) {
                    if (currentGroup.length > 0) {
                        groups.push(currentGroup);
                    }
                }
            });
            
            // Convert each group to proper lists
            groups.forEach(group => {
                if (group.length === 0) return;
                
                const firstText = group[0].text().trim();
                const isNumbered = /^\d+\./.test(firstText);
                const listTag = isNumbered ? 'ol' : 'ul';
                
                // Extract start number for ordered lists
                let startNumber = 1;
                if (isNumbered) {
                    const match = firstText.match(/^(\d+)\./);
                    if (match) {
                        startNumber = parseInt(match[1]);
                    }
                }
                
                // Create the list
                const $list = $(`<${listTag}>`);
                if (listTag === 'ol' && startNumber !== 1) {
                    $list.attr('start', startNumber);
                }
                
                // Convert each paragraph to list item
                group.forEach($p => {
                    let text = $p.html();
                    // Remove list markers
                    text = text.replace(/^(\d+\.|[a-z]\.|[ivx]+\.|\u2022|\u25CF|\u25AA|\u2013|\u2014|-)\s*/, '');
                    
                    const $li = $('<li>').html(text);
                    $list.append($li);
                });
                
                // Replace the first paragraph with the list and remove others
                group[0].replaceWith($list);
                for (let i = 1; i < group.length; i++) {
                    group[i].remove();
                }
            });
        },
        
        processTables: function($container) {
            $container.find('table').each(function() {
                const $table = $(this);
                
                // Add policy table class
                $table.addClass('policy-table');
                
                // Ensure proper table structure
                if ($table.find('thead').length === 0 && $table.find('tr').first().find('th').length > 0) {
                    const $firstRow = $table.find('tr').first();
                    const $thead = $('<thead>').append($firstRow.clone());
                    $table.prepend($thead);
                    $firstRow.remove();
                }
                
                // Wrap remaining rows in tbody if not already
                if ($table.find('tbody').length === 0) {
                    const $rows = $table.find('tr');
                    if ($rows.length > 0) {
                        const $tbody = $('<tbody>').append($rows);
                        $table.append($tbody);
                    }
                }
            });
        },
        
        processParagraphs: function($container) {
            // Ensure proper paragraph spacing
            $container.find('p').each(function() {
                const $p = $(this);
                
                // Remove excessive line breaks
                let html = $p.html();
                html = html.replace(/<br\s*\/?>\s*<br\s*\/?>/gi, '</p><p>');
                $p.html(html);
            });
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