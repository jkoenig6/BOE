// File: assets/olstart-plugin.js
// TinyMCE plugin for setting start numbers on ordered lists

(function() {
    tinymce.PluginManager.add('olstart', function(editor) {
        editor.addButton('olstart', {
            title: 'Set Start Number for Ordered List',
            icon: 'numlist',
            onclick: function() {
                var start = prompt("Enter the starting number:", "1");
                if (!start || isNaN(start) || parseInt(start) <= 0) {
                    alert("Please enter a valid positive number.");
                    return;
                }

                start = parseInt(start);

                // Get the current node selection
                var node = editor.selection.getNode();

                // Traverse upward to find the nearest <ol>
                while (node && node.nodeName !== 'OL') {
                    node = node.parentNode;
                }

                if (node && node.nodeName === 'OL') {
                    // Set the start attribute on the existing <ol>
                    editor.dom.setAttrib(node, 'start', start);

                    // Optional: force visual update by reselecting
                    editor.selection.select(node);
                    editor.selection.collapse(false);
                } else {
                    // Not inside a list: insert a new <ol>
                    editor.insertContent('<ol start="' + start + '"><li>List item</li></ol>');
                }
            }
        });
    });
})();