// Enhanced TinyMCE plugin with better visual icon
// File: assets/js/admin/tinymce/olstart-plugin.js

(function() {
    tinymce.PluginManager.add('olstart', function(editor) {
        
        // The main function to handle OL start setting
        function setOLStart() {
            var node = editor.selection.getNode();
            var currentOl = null;
            var tempNode = node;
            
            // Find the nearest <ol> if we're inside one
            while (tempNode && tempNode.nodeName !== 'OL') {
                tempNode = tempNode.parentNode;
            }
            currentOl = tempNode;
            
            // Get current start value if in an existing list
            var currentStart = currentOl ? (currentOl.getAttribute('start') || '1') : '1';
            
            // Show prompt with current value
            var start = prompt("Enter the starting number for this ordered list:", currentStart);
            
            // Validate input
            if (start === null) {
                return; // User cancelled
            }
            
            start = start.trim();
            if (!start || isNaN(start) || parseInt(start) < 1) {
                alert("Please enter a valid positive number (1 or greater).");
                return;
            }

            start = parseInt(start);

            if (currentOl) {
                // We're inside an existing ordered list
                editor.dom.setAttrib(currentOl, 'start', start);
                
                // Visual feedback
                editor.selection.select(currentOl);
                editor.selection.collapse(false);
                
                console.log('List start number set to ' + start);
            } else {
                // Not inside a list, create a new one
                var listHtml = '<ol start="' + start + '"><li>List item 1</li><li>List item 2</li></ol>';
                editor.insertContent(listHtml);
                
                console.log('New ordered list created starting at ' + start);
            }
        }
        
        // Add the button with a visual icon (using existing TinyMCE icons)
        editor.addButton('olstart', {
            title: 'Set Start Number for Ordered List',
            // Use the existing "listnum" icon with a custom class for styling
            icon: 'listnum',
            onclick: setOLStart,
            classes: 'olstart-button'
        });

        // ALSO add menu item under Insert menu for easier access
        editor.addMenuItem('olstart', {
            text: 'Set List Start Number',
            icon: 'listnum',
            context: 'insert',
            onclick: setOLStart
        });
        
        // Add to Tools menu as well
        editor.addMenuItem('olstart_tools', {
            text: 'Set List Start Number',
            icon: 'listnum',
            context: 'tools',
            onclick: setOLStart
        });
        
        // Add custom CSS for a better visual indicator
        editor.on('init', function() {
            // Add custom styling to make the button more distinctive
            editor.dom.addStyle(`
                .olstart-button .mce-ico {
                    position: relative;
                }
                .olstart-button .mce-ico:after {
                    content: "1";
                    position: absolute;
                    top: 2px;
                    right: 2px;
                    font-size: 8px;
                    font-weight: bold;
                    color: #0073aa;
                    background: #fff;
                    border-radius: 2px;
                    padding: 0 1px;
                    line-height: 1;
                    box-shadow: 0 0 1px rgba(0,0,0,0.3);
                }
                .olstart-button:hover .mce-ico:after {
                    color: #005a87;
                }
            `);
        });
        
        console.log('TinyMCE OL Start plugin loaded successfully');
    });
})()