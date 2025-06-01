// Policy Page Cleanup Script
// File: assets/js/frontend/policy-cleanup.js

document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a policy page
    if (document.body.classList.contains('single-policy') || 
        document.querySelector('.policy-metadata')) {
        
        console.log('Policy page detected, cleaning up author/date elements...');
        
        // List of selectors that commonly contain author/date info
        const authorDateSelectors = [
            '.entry-meta',
            '.posted-on', 
            '.byline',
            '.author',
            '.post-author',
            '.entry-date',
            '.posted-by',
            '.entry-footer .cat-links',
            '.entry-footer .tags-links', 
            '.entry-footer .edit-link',
            '.entry-footer .comments-link',
            '.meta-sep',
            '.entry-meta-separator',
            '.sep'
        ];
        
        // Hide elements using the selectors
        authorDateSelectors.forEach(function(selector) {
            const elements = document.querySelectorAll(selector);
            elements.forEach(function(element) {
                element.style.display = 'none';
                console.log('Hidden element:', selector);
            });
        });
        
        // More aggressive cleanup - look for text patterns
        cleanupByTextContent();
        
        // Clean up any remaining "/" separators
        cleanupSeparators();
        
        // Clean up any author links (like "By Jason Koenig")
        cleanupAuthorLinks();
    }
});

function cleanupByTextContent() {
    // Find all text nodes and check for author/date patterns
    const walker = document.createTreeWalker(
        document.querySelector('.entry-content') || document.body,
        NodeFilter.SHOW_TEXT,
        null,
        false
    );
    
    const textNodes = [];
    let node;
    
    while (node = walker.nextNode()) {
        textNodes.push(node);
    }
    
    textNodes.forEach(function(textNode) {
        const text = textNode.textContent.trim();
        
        // Check for common author/date patterns
        if (text.match(/^(By\s+|Posted\s+by\s+|Author:\s*|Written\s+by\s+)/i) ||
            text.match(/^\d{1,2}\/\d{1,2}\/\d{4}/) ||
            text.match(/^(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}/i) ||
            text === '/' ||
            text === '|') {
            
            // Hide the parent element
            let parent = textNode.parentElement;
            if (parent) {
                parent.style.display = 'none';
                console.log('Hidden by text pattern:', text);
            }
        }
    });
}

function cleanupSeparators() {
    // Find standalone "/" or "|" characters that might be separators
    const allElements = document.querySelectorAll('*');
    
    allElements.forEach(function(element) {
        const text = element.textContent.trim();
        
        // If element only contains a separator character
        if ((text === '/' || text === '|' || text === '•') && 
            element.children.length === 0) {
            element.style.display = 'none';
            console.log('Hidden separator:', text);
        }
    });
}

function cleanupAuthorLinks() {
    // Look for links that might be author links
    const links = document.querySelectorAll('a');
    
    links.forEach(function(link) {
        const href = link.getAttribute('href') || '';
        const text = link.textContent.trim();
        
        // Check if link looks like an author link
        if (href.includes('/author/') || 
            text.match(/^(Jason Koenig|Admin|Administrator)$/i)) {
            
            // Check if this link is in a context that suggests it's author info
            const parent = link.closest('.entry-meta, .posted-by, .byline, .author-info');
            if (parent) {
                parent.style.display = 'none';
                console.log('Hidden author link context:', text);
            } else {
                // Just hide the link itself if no clear parent context
                link.style.display = 'none';
                console.log('Hidden author link:', text);
            }
        }
    });
}

// Also clean up on dynamic content loads (if theme uses AJAX)
if (typeof jQuery !== 'undefined') {
    jQuery(document).on('post-load', function() {
        console.log('Content dynamically loaded, re-cleaning...');
        // Re-run cleanup functions
        setTimeout(function() {
            cleanupByTextContent();
            cleanupSeparators(); 
            cleanupAuthorLinks();
        }, 100);
    });
}