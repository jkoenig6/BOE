// File: assets/agenda-builder.js - Fixed version with working drag-and-drop

// Function to dynamically load jQuery UI if missing
function loadJQueryUI() {
    return new Promise((resolve, reject) => {
        if (typeof $.fn.sortable !== 'undefined') {
            resolve();
            return;
        }
        
        console.log('Loading jQuery UI dynamically...');
        const script = document.createElement('script');
        script.src = 'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js';
        script.onload = () => {
            console.log('jQuery UI loaded dynamically');
            resolve();
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

(function($) {
    'use strict';
    
    let agendaBuilderApp = {
        
        init: function() {
            console.log('Agenda Builder App initializing...');
            this.initSortable();
            this.initEventHandlers();
            this.updateAlphaIdentifiers();
            
            // Auto-load consent agenda data for existing meetings
            this.autoLoadConsentAgenda();
        },
        
        autoLoadConsentAgenda: function() {
            console.log('🔍 Checking for consent agenda sections that need loading...');
            
            // Find consent agenda sections with loading placeholders
            $('.consent-agenda-section').each(function() {
                const $section = $(this);
                const hasLoadingDiv = $section.find('.loading-resolutions').length > 0;
                const hasNoContent = $section.find('.sortable-resolutions').length === 0;
                
                if (hasLoadingDiv || hasNoContent) {
                    console.log('📡 Found consent section that needs loading, triggering load...');
                    
                    // Get the meeting ID
                    const postId = $('#post-id').val();
                    if (postId) {
                        console.log('📋 Loading consent agenda for meeting ID:', postId);
                        agendaBuilderApp.loadConsentAgendaData(postId, $section);
                    } else {
                        console.log('⚠️ No post ID found for loading consent agenda');
                    }
                }
            });
        },
        
        initSortable: function() {
            $('#agenda-sortable').sortable({
                handle: '.agenda-item-header',
                placeholder: 'ui-sortable-placeholder',
                tolerance: 'pointer',
                cursor: 'move',
                opacity: 0.8,
                start: function(event, ui) {
                    ui.placeholder.height(ui.item.height());
                    ui.item.addClass('ui-sortable-helper');
                },
                stop: function(event, ui) {
                    ui.item.removeClass('ui-sortable-helper');
                    agendaBuilderApp.updateAlphaIdentifiers();
                    agendaBuilderApp.autoSave();
                },
                update: function(event, ui) {
                    agendaBuilderApp.updateDataIndexes();
                }
            });
        },
        
        initEventHandlers: function() {
            // Toggle agenda item content
            $(document).on('click', '.toggle-item', function(e) {
                e.preventDefault();
                let $item = $(this).closest('.agenda-item');
                $item.toggleClass('collapsed');
                $(this).text($item.hasClass('collapsed') ? '▶' : '▼');
            });
            
            // Remove agenda item
            $(document).on('click', '.remove-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                let $item = $(this).closest('.agenda-item');
                
                try {
                    let confirmed = confirm('Are you sure you want to remove this agenda item?');
                    
                    if (confirmed) {
                        $item.fadeOut(300, function() {
                            $(this).remove();
                            agendaBuilderApp.updateAlphaIdentifiers();
                            agendaBuilderApp.updateDataIndexes();
                            agendaBuilderApp.autoSave();
                        });
                    }
                } catch (error) {
                    console.error('Error in remove handler:', error);
                }
            });
            
            // Add new agenda item
            $('.add-agenda-item').on('click', function(e) {
                e.preventDefault();
                agendaBuilderApp.showAddItemModal();
            });
            
            // Initialize sortable for consent agenda when items are added
            $(document).on('agenda-item-added', function(e, layout) {
                console.log('Agenda item added:', layout);
                if (layout === 'consent_agenda') {
                    setTimeout(() => {
                        agendaBuilderApp.initConsentAgendaSortable();
                    }, 500);
                }
            });
            
            // Initialize sortable when consent agenda content is dynamically loaded
            $(document).on('consent-agenda-loaded', function() {
                console.log('🎯 Consent agenda content loaded, initializing sortable...');
                setTimeout(() => {
                    agendaBuilderApp.initConsentAgendaSortable();
                }, 100);
            });
            
            // Refresh resolutions button
            $(document).on('click', '.refresh-resolutions', function(e) {
                e.preventDefault();
                let meetingId = $(this).data('meeting-id');
                let $consentSection = $(this).closest('.consent-agenda-section');
                console.log('Refreshing resolutions for meeting:', meetingId);
                agendaBuilderApp.loadConsentAgendaData(meetingId, $consentSection);
            });
            
            // Add topic to study session
            $(document).on('click', '.add-topic', function(e) {
                e.preventDefault();
                let $topicsContainer = $(this).siblings('.study-topics').length ? 
                    $(this).siblings('.study-topics') : $(this).parent();
                let topicIndex = $topicsContainer.find('.topic-row').length;
                let itemIndex = $(this).closest('.agenda-item').data('index');
                
                let topicHtml = `
                    <div class="topic-row">
                        <div class="topic-inputs">
                            <input type="text" placeholder="Topic Title" name="agenda_items[${itemIndex}][topics][${topicIndex}][title]" value="" />
                            <input type="text" placeholder="Presenter" name="agenda_items[${itemIndex}][topics][${topicIndex}][presenter]" value="" />
                            <input type="text" placeholder="Time Estimate" name="agenda_items[${itemIndex}][topics][${topicIndex}][time_estimate]" value="" />
                        </div>
                        <textarea placeholder="Description/Notes" name="agenda_items[${itemIndex}][topics][${topicIndex}][description]" rows="2"></textarea>
                        <button type="button" class="remove-topic">Remove Topic</button>
                    </div>
                `;
                
                $(this).before(topicHtml);
            });
            
            // Add minute item
            $(document).on('click', '.add-minute', function(e) {
                e.preventDefault();
                let $minutesList = $(this).parent();
                let minuteIndex = $minutesList.find('.minute-row').length;
                let itemIndex = $(this).closest('.agenda-item').data('index');
                
                let minuteHtml = `
                    <div class="minute-row">
                        <input type="text" placeholder="Minutes Title" name="agenda_items[${itemIndex}][minutes_list][${minuteIndex}][title]" value="" />
                        <input type="url" placeholder="Link to Minutes" name="agenda_items[${itemIndex}][minutes_list][${minuteIndex}][link]" value="" />
                        <button type="button" class="remove-minute">×</button>
                    </div>
                `;
                
                $(this).before(minuteHtml);
            });
            
            // Add communication
            $(document).on('click', '.add-communication', function(e) {
                e.preventDefault();
                let $commList = $(this).parent();
                let commIndex = $commList.find('.communication-row').length;
                let itemIndex = $(this).closest('.agenda-item').data('index');
                
                let commHtml = `
                    <div class="communication-row">
                        <input type="text" placeholder="Source" name="agenda_items[${itemIndex}][messages][${commIndex}][source]" value="" />
                        <textarea placeholder="Message/Notes" name="agenda_items[${itemIndex}][messages][${commIndex}][message]" rows="2"></textarea>
                        <button type="button" class="remove-communication">×</button>
                    </div>
                `;
                
                $(this).before(commHtml);
            });
            
            // Add future item
            $(document).on('click', '.add-future-item', function(e) {
                e.preventDefault();
                let $futureList = $(this).parent();
                let futureIndex = $futureList.find('.future-item-row').length;
                let itemIndex = $(this).closest('.agenda-item').data('index');
                
                let futureHtml = `
                    <div class="future-item-row">
                        <input type="text" placeholder="Item Title" name="agenda_items[${itemIndex}][items][${futureIndex}][title]" value="" />
                        <input type="url" placeholder="Link (optional)" name="agenda_items[${itemIndex}][items][${futureIndex}][link]" value="" />
                        <button type="button" class="remove-future-item">×</button>
                    </div>
                `;
                
                $(this).before(futureHtml);
            });
            
            // Remove items
            $(document).on('click', '.remove-topic, .remove-minute, .remove-communication, .remove-future-item', function(e) {
                e.preventDefault();
                $(this).closest('.topic-row, .minute-row, .communication-row, .future-item-row').fadeOut(200, function() {
                    $(this).remove();
                });
            });
            
            // Save draft
            $('#save-draft').on('click', function(e) {
                e.preventDefault();
                agendaBuilderApp.saveMeeting('draft');
            });
            
            // Publish/Update meeting
            $('#publish-meeting').on('click', function(e) {
                e.preventDefault();
                let status = $('#post-status').val();
                agendaBuilderApp.saveMeeting(status);
            });
            
            // Refresh consent agenda when meeting changes
            $(document).on('change', '#meeting-title, #meeting-datetime', function() {
                agendaBuilderApp.refreshConsentAgenda();
            });
            
            // Auto-save on input changes (debounced)
            let autoSaveTimeout;
            $(document).on('input change', '.agenda-item-content input, .agenda-item-content textarea, .agenda-item-content select, #meeting-title, #meeting-datetime, #meeting-duration, #meeting-location', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(function() {
                    agendaBuilderApp.autoSave();
                }, 2000); // 2 second delay
            });
            
            // Prevent form submission on Enter key
            $(document).on('keypress', 'input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                }
            });
        },
        
        updateAlphaIdentifiers: function() {
            $('#agenda-sortable .agenda-item').each(function(index) {
                let alphaId = agendaBuilderApp.numberToAlpha(index + 1);
                $(this).find('.alpha-identifier').text(alphaId);
                $(this).attr('data-index', index);
            });
        },
        
        updateDataIndexes: function() {
            $('#agenda-sortable .agenda-item').each(function(index) {
                let oldIndex = $(this).attr('data-index');
                $(this).attr('data-index', index);
                
                // Update all form field names to reflect new index
                $(this).find('input, textarea, select').each(function() {
                    let name = $(this).attr('name');
                    if (name && name.includes('agenda_items[')) {
                        // Replace the old index with the new index
                        let newName = name.replace(/agenda_items\[\d+\]/, `agenda_items[${index}]`);
                        $(this).attr('name', newName);
                    }
                });
            });
        },
        
        numberToAlpha: function(num) {
            let result = '';
            while (num > 0) {
                num--;
                result = String.fromCharCode(65 + (num % 26)) + result;
                num = Math.floor(num / 26);
            }
            return result;
        },
        
        refreshConsentAgenda: function() {
            let postId = $('#post-id').val();
            if (!postId) return;
            
            console.log('Refreshing consent agenda for post:', postId);
            
            // Find consent agenda sections and refresh them
            $('.agenda-item[data-layout="consent_agenda"]').each(function() {
                let $consentSection = $(this).find('.consent-agenda-section');
                if ($consentSection.length) {
                    agendaBuilderApp.loadConsentAgendaData(postId, $consentSection);
                }
            });
        },
        
        // Fixed consent agenda sortable initialization
        initConsentAgendaSortable: function() {
            console.log('🔧 Initializing consent agenda sortable...');
            
            // Check if jQuery UI is available
            if (typeof $.fn.sortable === 'undefined') {
                console.error('❌ jQuery UI Sortable not available!');
                return;
            }
            
            // Wait for DOM to be ready and find all sortable containers
            setTimeout(() => {
                $('.sortable-resolutions').each(function() {
                    const $container = $(this);
                    const containerId = $container.attr('id') || 'sortable-' + Date.now();
                    
                    // Set ID if missing
                    if (!$container.attr('id')) {
                        $container.attr('id', containerId);
                    }
                    
                    console.log('🔍 Processing sortable container:', containerId);
                    
                    // Check if already initialized
                    if ($container.hasClass('ui-sortable')) {
                        console.log('✅ Container already sortable, destroying and reinitializing...');
                        $container.sortable('destroy');
                    }
                    
                    // Check if container has items
                    const itemCount = $container.find('.sortable-resolution').length;
                    console.log('📊 Found', itemCount, 'sortable items');
                    
                    if (itemCount === 0) {
                        console.log('⚠️ No sortable items found, skipping...');
                        return;
                    }
                    
                    // Initialize sortable
                    try {
                        $container.sortable({
                            handle: '.resolution-drag-handle',
                            placeholder: 'resolution-sort-placeholder',
                            tolerance: 'pointer',
                            cursor: 'move',
                            opacity: 0.8,
                            axis: 'y',
                            containment: 'parent',
                            helper: 'clone',
                            start: function(event, ui) {
                                console.log('🎯 Drag started');
                                ui.placeholder.height(ui.item.outerHeight());
                                ui.item.addClass('resolution-dragging');
                                ui.helper.addClass('resolution-dragging');
                            },
                            stop: function(event, ui) {
                                console.log('🎯 Drag stopped');
                                ui.item.removeClass('resolution-dragging');
                                agendaBuilderApp.updateResolutionNumbers($container);
                                agendaBuilderApp.saveResolutionOrder($container);
                            },
                            update: function(event, ui) {
                                console.log('🔄 Order updated');
                                agendaBuilderApp.updateHiddenResolutionField($container);
                            }
                        });
                        
                        console.log('✅ Sortable initialized successfully for container:', containerId);
                        
                        // Add visual indication that items are sortable
                        $container.find('.resolution-drag-handle').css({
                            'cursor': 'move',
                            'opacity': '1'
                        });
                        
                        // Add debug class
                        $container.addClass('sortable-initialized');
                        
                    } catch (error) {
                        console.error('❌ Error initializing sortable:', error);
                    }
                });
            }, 300);
        },
        
        updateResolutionNumbers: function($container) {
            console.log('🔢 Updating resolution numbers...');
            let meetingId = $container.data('meeting-id');
            
            // Get meeting date info for proper numbering
            let meetingDate = $('#meeting-datetime').val();
            let year = '25'; // Default
            let month = '1'; // Default
            
            if (meetingDate) {
                let date = new Date(meetingDate);
                year = date.getFullYear().toString().substr(-2);
                month = date.getMonth() + 1;
            }
            
            // Update resolution numbers based on new order
            $container.find('.sortable-resolution').each(function(index) {
                let sequence = index + 1;
                let newNumber = year + '.' + month + '.' + sequence;
                $(this).find('.resolution-number').text(newNumber);
                
                // Add visual feedback
                $(this).addClass('number-updated');
                setTimeout(() => {
                    $(this).removeClass('number-updated');
                }, 1000);
            });
            
            console.log('✅ Resolution numbers updated');
        },
        
        saveResolutionOrder: function($container) {
            console.log('💾 Saving resolution order...');
            let meetingId = $container.data('meeting-id');
            let resolutionOrder = [];
            
            $container.find('.sortable-resolution').each(function() {
                resolutionOrder.push($(this).data('resolution-id'));
            });
            
            console.log('📋 New order:', resolutionOrder);
            
            // Show saving indicator
            $container.addClass('saving-order');
            
            $.ajax({
                url: agendaBuilder.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'save_resolution_order',
                    meeting_id: meetingId,
                    resolution_order: resolutionOrder,
                    nonce: agendaBuilder.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Resolution order saved successfully');
                        agendaBuilderApp.showNotification('Resolution order saved!', 'success');
                        $container.removeClass('saving-order').addClass('order-saved');
                        setTimeout(() => {
                            $container.removeClass('order-saved');
                        }, 2000);
                    } else {
                        console.error('❌ Error saving order:', response.data);
                        agendaBuilderApp.showNotification('Error saving order: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Network error saving order:', error);
                    agendaBuilderApp.showNotification('Network error saving order', 'error');
                },
                complete: function() {
                    $container.removeClass('saving-order');
                }
            });
        },
        
        updateHiddenResolutionField: function($container) {
            let resolutionIds = [];
            $container.find('.sortable-resolution').each(function() {
                resolutionIds.push($(this).data('resolution-id'));
            });
            
            // Update the hidden field with new order
            let meetingId = $container.data('meeting-id');
            $('.auto-resolutions-field[data-meeting-id="' + meetingId + '"]').val(resolutionIds.join(','));
            console.log('🔄 Updated hidden field with order:', resolutionIds);
        },
        
        loadConsentAgendaData: function(meetingId, $container) {
            console.log('📡 Loading consent agenda data for meeting:', meetingId);
            
            // Show loading state
            $container.html('<div class="loading-resolutions"><p>Loading approved resolutions...</p></div>');
            
            $.ajax({
                url: agendaBuilder.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_meeting_resolutions',
                    meeting_id: meetingId,
                    nonce: agendaBuilder.nonce
                },
                success: function(response) {
                    console.log('📡 Consent agenda data loaded:', response);
                    if (response.success) {
                        agendaBuilderApp.renderConsentAgendaData(response.data, $container, meetingId);
                    } else {
                        $container.html('<div class="error-loading"><p>Error loading resolutions: ' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Network error loading consent agenda:', error);
                    $container.html('<div class="error-loading"><p>Network error loading resolutions.</p></div>');
                }
            });
        },
        
        // Fixed render method
        renderConsentAgendaData: function(resolutions, $container, meetingId) {
            console.log('🎨 Rendering consent agenda data:', resolutions.length, 'resolutions');
            
            let html = '<label>Approved Resolutions for this Meeting:</label>';
            
            if (resolutions.length === 0) {
                html += `
                    <div class="no-resolutions-notice">
                        <p><em>No approved resolutions assigned to this meeting yet.</em></p>
                        <p>Resolutions will appear here automatically once they are:</p>
                        <ul>
                            <li>1. Created and assigned to this meeting</li>
                            <li>2. Submitted for approval</li>
                            <li>3. Approved by administrators</li>
                        </ul>
                    </div>
                `;
            } else {
                // Create unique ID for this sortable container
                const uniqueId = 'sortable-resolutions-' + meetingId + '-' + Date.now();
                console.log('🆔 Creating sortable container with ID:', uniqueId);
                
                html += `
                    <div class="resolutions-auto-list">
                        <div class="resolutions-header">
                            <p class="info-text"><strong>The following ${resolutions.length} approved resolution(s) will be included in the Consent Agenda:</strong></p>
                            <div class="resolution-controls">
                                <button type="button" class="button button-small refresh-resolutions" data-meeting-id="${meetingId}">↻ Refresh</button>
                                <span class="drag-help">💡 Drag ⋮⋮ handles to reorder • Numbers update automatically</span>
                            </div>
                        </div>
                        <div class="resolutions-preview-list sortable-resolutions" id="${uniqueId}" data-meeting-id="${meetingId}">
                `;
                
                resolutions.forEach(function(resolution, index) {
                    let resolutionNumber = resolution.title.match(/Resolution #([\d.]+)/);
                    resolutionNumber = resolutionNumber ? resolutionNumber[1] : resolution.sequence;
                    
                    console.log('📄 Adding resolution:', resolution.original_title, 'with number:', resolutionNumber);
                    
                    html += `
                        <div class="resolution-preview-item sortable-resolution" data-resolution-id="${resolution.id}">
                            <div class="resolution-drag-handle" title="Drag to reorder">⋮⋮</div>
                            <div class="resolution-content">
                                <div class="resolution-header">
                                    <span class="resolution-number">${resolutionNumber}</span>
                                    <span class="resolution-title">${resolution.original_title}</span>
                                </div>
                                <div class="resolution-meta">
                                    <span class="subject"><strong>Subject:</strong> ${resolution.subject}</span>
                                    ${resolution.fiscal_impact === 'Yes' ? '<span class="fiscal-badge">Fiscal Impact</span>' : ''}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                        <div class="consent-agenda-note">
                            <p><strong>Note:</strong> These resolutions are automatically included. Drag the ⋮⋮ handles to reorder and the resolution numbers will update automatically. When this meeting is published, all listed resolutions will be published with their assigned resolution numbers.</p>
                        </div>
                    </div>
                `;
            }
            
            // Add hidden field with resolution IDs
            let resolutionIds = resolutions.map(r => r.id).join(',');
            html += `<input type="hidden" name="agenda_items[${$container.closest('.agenda-item').data('index')}][resolutions]" value="${resolutionIds}" class="auto-resolutions-field" data-meeting-id="${meetingId}" />`;
            
            $container.html(html);
            
            // Trigger custom event to initialize sortable after content is loaded
            if (resolutions.length > 0) {
                console.log('🎨 Content rendered, triggering consent-agenda-loaded event...');
                $(document).trigger('consent-agenda-loaded');
            }
        },
        
        showAddItemModal: function() {
            let availableItems = [
                {key: 'study_session', title: 'Study Session'},
                {key: 'call_to_order', title: 'Call to Order'},
                {key: 'pledge_of_allegiance', title: 'Pledge of Allegiance'},
                {key: 'land_acknowledgment', title: 'Land Acknowledgment'},
                {key: 'approval_of_agenda', title: 'Approval of Agenda'},
                {key: 'approval_of_minutes', title: 'Approval of Minutes'},
                {key: 'communications', title: 'Communications'},
                {key: 'special_report', title: 'Special Report'},
                {key: 'audience_comments', title: 'Audience Comments'},
                {key: 'consent_agenda', title: 'Consent Agenda'},
                {key: 'future_consideration', title: 'Information for Future Consideration'},
                {key: 'other_business', title: 'Other Business'},
                {key: 'adjournment', title: 'Adjournment'}
            ];
            
            let modalHtml = `
                <div class="agenda-item-modal-overlay">
                    <div class="agenda-item-modal">
                        <div class="modal-header">
                            <h3>Add Agenda Item</h3>
                            <button type="button" class="close-modal">×</button>
                        </div>
                        <div class="modal-content">
                            <p>Select an agenda item type:</p>
                            <div class="item-type-grid">
            `;
            
            availableItems.forEach(function(item) {
                modalHtml += `
                    <button type="button" class="item-type-btn" data-layout="${item.key}">
                        ${item.title}
                    </button>
                `;
            });
            
            modalHtml += `
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Modal event handlers
            $('.close-modal, .agenda-item-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('.agenda-item-modal-overlay').fadeOut(200, function() {
                        $(this).remove();
                    });
                }
            });
            
            $('.item-type-btn').on('click', function() {
                let layout = $(this).data('layout');
                agendaBuilderApp.addAgendaItem(layout);
                $('.agenda-item-modal-overlay').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        },
        
        addAgendaItem: function(layout) {
            let itemCount = $('#agenda-sortable .agenda-item').length;
            let alphaId = this.numberToAlpha(itemCount + 1);
            let title = this.getLayoutTitle(layout);
            
            // Use the current count as index, will be updated after
            let tempIndex = itemCount;
            
            let itemHtml = `
                <div class="agenda-item" data-layout="${layout}" data-index="${tempIndex}">
                    <div class="agenda-item-header">
                        <span class="alpha-identifier">${alphaId}</span>
                        <span class="item-title">${title}</span>
                        <div class="item-controls">
                            <button type="button" class="toggle-item">▼</button>
                            <button type="button" class="remove-item">×</button>
                        </div>
                    </div>
                    <div class="agenda-item-content">
                        ${this.getLayoutFields(layout, tempIndex)}
                    </div>
                </div>
            `;
            
            let $newItem = $(itemHtml);
            $('#agenda-sortable').append($newItem);
            
            // Immediately update all indexes to ensure consistency
            this.updateDataIndexes();
            this.updateAlphaIdentifiers();
            
            // Trigger custom event for consent agenda
            if (layout === 'consent_agenda') {
                console.log('🎯 Adding consent agenda item, triggering initialization...');
                $(document).trigger('agenda-item-added', [layout]);
                
                // Load consent agenda data
                let postId = $('#post-id').val();
                if (postId) {
                    let $consentSection = $newItem.find('.consent-agenda-section');
                    this.loadConsentAgendaData(postId, $consentSection);
                }
            }
            
            // Scroll to new item
            $newItem.get(0).scrollIntoView({behavior: 'smooth', block: 'center'});
            
            // Focus first input
            setTimeout(function() {
                $newItem.find('input, textarea, select').first().focus();
            }, 500);
        },
        
        getLayoutTitle: function(layout) {
            const titles = {
                'study_session': 'Study Session',
                'call_to_order': 'Call to Order',
                'pledge_of_allegiance': 'Pledge of Allegiance',
                'land_acknowledgment': 'Land Acknowledgment',
                'approval_of_agenda': 'Approval of Agenda',
                'approval_of_minutes': 'Approval of Minutes',
                'communications': 'Communications',
                'special_report': 'Special Report',
                'audience_comments': 'Audience Comments',
                'consent_agenda': 'Consent Agenda',
                'future_consideration': 'Information for Future Consideration',
                'other_business': 'Other Business',
                'adjournment': 'Adjournment'
            };
            
            return titles[layout] || layout.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },
        
        getLayoutFields: function(layout, index) {
            switch (layout) {
                case 'study_session':
                    return `
                        <label>Start Time:</label>
                        <input type="time" name="agenda_items[${index}][start_time]" value="" />
                        <label>Topics:</label>
                        <div class="study-topics">
                            <div class="topic-row">
                                <div class="topic-inputs">
                                    <input type="text" placeholder="Topic Title" name="agenda_items[${index}][topics][0][title]" value="" />
                                    <input type="text" placeholder="Presenter" name="agenda_items[${index}][topics][0][presenter]" value="" />
                                    <input type="text" placeholder="Time Estimate" name="agenda_items[${index}][topics][0][time_estimate]" value="" />
                                </div>
                                <textarea placeholder="Description/Notes" name="agenda_items[${index}][topics][0][description]" rows="2"></textarea>
                                <button type="button" class="remove-topic">Remove Topic</button>
                            </div>
                        </div>
                        <button type="button" class="add-topic">+ Add Topic</button>
                    `;
                    
                case 'approval_of_minutes':
                    return `
                        <label>Minutes to Approve:</label>
                        <div class="minutes-list">
                            <div class="minute-row">
                                <input type="text" placeholder="Minutes Title" name="agenda_items[${index}][minutes_list][0][title]" value="" />
                                <input type="url" placeholder="Link to Minutes" name="agenda_items[${index}][minutes_list][0][link]" value="" />
                                <button type="button" class="remove-minute">×</button>
                            </div>
                        </div>
                        <button type="button" class="add-minute">+ Add Minutes</button>
                    `;
                    
                case 'communications':
                    return `
                        <label>Communications:</label>
                        <div class="communications-list">
                            <div class="communication-row">
                                <input type="text" placeholder="Source" name="agenda_items[${index}][messages][0][source]" value="" />
                                <textarea placeholder="Message/Notes" name="agenda_items[${index}][messages][0][message]" rows="2"></textarea>
                                <button type="button" class="remove-communication">×</button>
                            </div>
                        </div>
                        <button type="button" class="add-communication">+ Add Communication</button>
                    `;
                    
                case 'consent_agenda':
                    return `
                        <div class="consent-agenda-section">
                            <label>Approved Resolutions for this Meeting:</label>
                            <div class="loading-resolutions">
                                <p>Loading approved resolutions...</p>
                            </div>
                        </div>
                    `;
                    
                case 'future_consideration':
                    return `
                        <label>Items for Future Consideration:</label>
                        <div class="future-items-list">
                            <div class="future-item-row">
                                <input type="text" placeholder="Item Title" name="agenda_items[${index}][items][0][title]" value="" />
                                <input type="url" placeholder="Link (optional)" name="agenda_items[${index}][items][0][link]" value="" />
                                <button type="button" class="remove-future-item">×</button>
                            </div>
                        </div>
                        <button type="button" class="add-future-item">+ Add Item</button>
                    `;
                    
                default:
                    let fieldName = this.getFieldNameForLayout(layout);
                    return `
                        <label>Details:</label>
                        <textarea name="agenda_items[${index}][${fieldName}]" rows="3" placeholder="Enter details for this agenda item..."></textarea>
                    `;
            }
        },
        
        getFieldNameForLayout: function(layout) {
            const fieldNames = {
                'call_to_order': 'details',
                'pledge_of_allegiance': 'text',
                'land_acknowledgment': 'text',
                'approval_of_agenda': 'text',
                'special_report': 'text',
                'audience_comments': 'text',
                'other_business': 'text',
                'adjournment': 'text'
            };
            
            return fieldNames[layout] || 'text';
        },
        
        saveMeeting: function(status) {
            let $saveBtn = status === 'draft' ? $('#save-draft') : $('#publish-meeting');
            let originalText = $saveBtn.text();
            
            $saveBtn.text('Saving...').prop('disabled', true);
            
            let formData = this.collectFormData();
            formData.status = status;
            formData.action = 'save_agenda';
            formData.nonce = agendaBuilder.nonce;
            
            $.ajax({
                url: agendaBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        agendaBuilderApp.showNotification('Meeting saved successfully!', 'success');
                        
                        // Update URL if this was a new post
                        if (response.data.post_id && !agendaBuilder.postId) {
                            let newUrl = window.location.href + '&post_id=' + response.data.post_id;
                            window.history.replaceState({}, '', newUrl);
                            $('#post-id').val(response.data.post_id);
                            agendaBuilder.postId = response.data.post_id;
                        }
                        
                        // Flash saved indicators
                        $('.agenda-item').addClass('saved');
                        setTimeout(function() {
                            $('.agenda-item').removeClass('saved');
                        }, 2000);
                        
                    } else {
                        agendaBuilderApp.showNotification('Error saving meeting: ' + response.data, 'error');
                    }
                },
                error: function() {
                    agendaBuilderApp.showNotification('Network error while saving', 'error');
                },
                complete: function() {
                    $saveBtn.text(originalText).prop('disabled', false);
                }
            });
        },
        
        autoSave: function() {
            // Only auto-save if we have a post ID
            if (!agendaBuilder.postId) return;
            
            let formData = this.collectFormData();
            formData.action = 'save_agenda';
            formData.nonce = agendaBuilder.nonce;
            formData.status = $('#post-status').val() || 'draft';
            
            // Show saving indicator
            $('.agenda-item').addClass('saving');
            
            $.ajax({
                url: agendaBuilder.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $('.agenda-item').removeClass('saving').addClass('saved');
                        setTimeout(function() {
                            $('.agenda-item').removeClass('saved');
                        }, 1500);
                    }
                },
                error: function() {
                    $('.agenda-item').removeClass('saving');
                }
            });
        },
        
        collectFormData: function() {
            let formData = {
                post_id: $('#post-id').val(),
                title: $('#meeting-title').val(),
                date: $('#post-date').val(),
                meeting_type: [],
                meeting_datetime: $('#meeting-datetime').val(),
                meeting_duration: $('#meeting-duration').val(),
                meeting_location: $('#meeting-location').val()
            };
            
            // Collect meeting types
            $('select[name="meeting_type[]"] option:selected').each(function() {
                formData.meeting_type.push($(this).val());
            });
            
            // Collect agenda items
            formData.agenda_items = [];
            
            // First, add the header items (datetime and location)
            if (formData.meeting_datetime) {
                formData.agenda_items.push({
                    acf_fc_layout: 'meeting_datetime',
                    datetime: formData.meeting_datetime,
                    duration: formData.meeting_duration || '2.0 hour'
                });
            }
            
            if (formData.meeting_location) {
                formData.agenda_items.push({
                    acf_fc_layout: 'location',
                    location: formData.meeting_location
                });
            }
            
            // Then add the sortable agenda items with corrected indexing
            $('.agenda-item').each(function(sortable_index) {
                let layout = $(this).data('layout');
                let original_index = $(this).data('index');
                let itemData = {
                    acf_fc_layout: layout
                };
                
                // Special handling for consent agenda
                if (layout === 'consent_agenda') {
                    let $hiddenField = $(this).find('.auto-resolutions-field');
                    if ($hiddenField.length && $hiddenField.val()) {
                        let resolutionIds = $hiddenField.val().split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
                        itemData.resolutions = resolutionIds;
                    } else {
                        itemData.resolutions = [];
                    }
                } else {
                    // Collect all form fields within this agenda item
                    $(this).find('input, textarea, select').each(function() {
                        let name = $(this).attr('name');
                        let value = $(this).val();
                        
                        if (name && name.includes(`agenda_items[${original_index}]`)) {
                            // Parse the field name to get the structure
                            let fieldPath = name.replace(`agenda_items[${original_index}]`, '').replace(/^\[|\]$/g, '');
                            agendaBuilderApp.setNestedValue(itemData, fieldPath, value);
                        }
                    });
                }
                
                formData.agenda_items.push(itemData);
            });
            
            return formData;
        },
        
        setNestedValue: function(obj, path, value) {
            if (!path) return;
            
            let keys = path.split(/\[|\]/).filter(k => k !== '');
            let current = obj;
            
            for (let i = 0; i < keys.length - 1; i++) {
                let key = keys[i];
                if (!(key in current)) {
                    // Check if next key is numeric (array index)
                    let nextKey = keys[i + 1];
                    current[key] = /^\d+$/.test(nextKey) ? [] : {};
                }
                current = current[key];
            }
            
            let finalKey = keys[keys.length - 1];
            if (Array.isArray(current) && /^\d+$/.test(finalKey)) {
                current[parseInt(finalKey)] = value;
            } else {
                current[finalKey] = value;
            }
        },
        
        showNotification: function(message, type) {
            let className = type === 'success' ? 'notice-success' : 'notice-error';
            let $notice = $(`
                <div class="notice ${className} is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 300px;">
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
    
    // Enhanced initialization with better error handling
    $(document).ready(function() {
        if ($('.agenda-builder-wrap').length > 0) {
            console.log('🚀 Agenda Builder initializing...');
            
            // Debug current state
            console.log('📊 Environment check:');
            console.log('- jQuery version:', $.fn.jquery);
            console.log('- jQuery UI available:', typeof $.fn.sortable !== 'undefined');
            console.log('- agendaBuilder object:', typeof agendaBuilder !== 'undefined' ? 'available' : 'missing');
            
            // Check for jQuery UI
            if (typeof $.fn.sortable === 'undefined') {
                console.warn('⚠️ jQuery UI Sortable not found! Attempting to load...');
                loadJQueryUI().then(() => {
                    console.log('✅ jQuery UI loaded, initializing app...');
                    agendaBuilderApp.init();
                }).catch(error => {
                    console.error('❌ Failed to load jQuery UI:', error);
                });
            } else {
                console.log('✅ jQuery UI found, initializing app...');
                agendaBuilderApp.init();
            }
            
            // Debug: Check for existing consent agenda after 2 seconds
            setTimeout(() => {
                console.log('🔍 Consent agenda check:');
                console.log('- Consent sections found:', $('.consent-agenda-section').length);
                console.log('- Sortable containers found:', $('.sortable-resolutions').length);
                console.log('- Drag handles found:', $('.resolution-drag-handle').length);
                
                // Auto-initialize any existing consent agenda sections
                if ($('.sortable-resolutions').length > 0) {
                    console.log('🔄 Auto-initializing existing consent agenda...');
                    agendaBuilderApp.initConsentAgendaSortable();
                } else {
                    console.log('ℹ️ No existing sortable containers found - they will be initialized when content loads');
                }
            }, 2000);
            
            // Also check periodically for dynamically loaded content
            let checkCount = 0;
            const checkInterval = setInterval(() => {
                checkCount++;
                const containers = $('.sortable-resolutions').length;
                const handles = $('.resolution-drag-handle').length;
                
                if (containers > 0 && handles > 0) {
                    console.log('🎯 Found dynamically loaded consent agenda content!');
                    agendaBuilderApp.initConsentAgendaSortable();
                    clearInterval(checkInterval);
                } else if (checkCount >= 10) {
                    // Stop checking after 10 seconds
                    console.log('⏰ Stopped checking for consent agenda content');
                    clearInterval(checkInterval);
                } else if (checkCount === 3) {
                    // After 3 seconds, try to manually trigger loading
                    console.log('🔄 Manually triggering consent agenda load...');
                    agendaBuilderApp.autoLoadConsentAgenda();
                }
            }, 1000);
        }
    });
    
    // Expose for external use and debugging
    window.agendaBuilderApp = agendaBuilderApp;
    
})(jQuery);