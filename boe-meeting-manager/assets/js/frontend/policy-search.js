// File: assets/policy-search.js
// Enhanced search highlighting with link modification

document.addEventListener("DOMContentLoaded", function () {
    // Get search term from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get("s") || urlParams.get("highlight");

    if (searchTerm) {
        // Add highlight parameter to search result links
        document.querySelectorAll('.search-results-list a[href], .policies-list a[href]').forEach(function (link) {
            if (!link.href.includes('highlight=')) {
                const linkUrl = new URL(link.href, window.location.origin);
                linkUrl.searchParams.set('highlight', searchTerm);
                link.href = linkUrl.toString();
            }
        });

        // Highlight terms in current page
        highlightTermInContent(searchTerm);
    }

    // Initialize search form enhancements
    initSearchFormEnhancements();
});

function highlightTermInContent(searchTerm) {
    const content = document.querySelector('.entry-content, .policy-content');
    if (!content || !searchTerm) return;

    // Create a regex to match the search term
    const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
    
    // Function to highlight text nodes
    function highlightTextNode(node) {
        if (node.nodeType === 3) { // Text node
            const text = node.textContent;
            if (regex.test(text)) {
                const highlightedText = text.replace(regex, '<mark class="search-highlight">$1</mark>');
                const wrapper = document.createElement('span');
                wrapper.innerHTML = highlightedText;
                node.parentNode.replaceChild(wrapper, node);
            }
        } else if (node.nodeType === 1 && node.tagName !== 'SCRIPT' && node.tagName !== 'STYLE') {
            // Element node - recursively process children
            Array.from(node.childNodes).forEach(highlightTextNode);
        }
    }

    highlightTextNode(content);
}

function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function initSearchFormEnhancements() {
    // Add autocomplete functionality if desired
    const searchInputs = document.querySelectorAll('.policy-search-input');
    
    searchInputs.forEach(input => {
        // Add search suggestions or other enhancements here
        input.addEventListener('input', function() {
            // Could implement live search suggestions
        });
    });
}

---

/* File: assets/search-highlighting.css */
/* Search highlighting styles */

.search-highlight {
    background-color: #ffff00;
    color: #000;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 600;
}

.search-results-list .search-highlight {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

/* Search form styling */
.policy-search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 2em;
    flex-wrap: wrap;
}

.search-input-wrapper {
    flex: 1;
    min-width: 250px;
}

.policy-search-input {
    width: 100%;
    padding: 12px 16px;
    font-size: 16px;
    border: 2px solid #ddd;
    border-radius: 6px;
    transition: border-color 0.3s ease;
}

.policy-search-input:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
}

.policy-search-button {
    padding: 12px 24px;
    font-size: 16px;
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    white-space: nowrap;
}

.policy-search-button:hover {
    background: #005a87;
}

/* Search results styling */
.search-results-meta {
    margin-bottom: 1.5em;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
    color: #666;
}

.search-result-item {
    margin-bottom: 2em;
    padding-bottom: 1.5em;
    border-bottom: 1px solid #f0f0f0;
}

.search-result-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.policy-code {
    background: #0073aa;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.search-result-title {
    margin: 0;
}

.search-result-title a {
    color: #0073aa;
    text-decoration: none;
    font-weight: 600;
}

.search-result-title a:hover {
    text-decoration: underline;
}

.search-result-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
    font-size: 14px;
    color: #666;
}

.search-result-excerpt {
    line-height: 1.6;
    color: #333;
}

/* No results styling */
.no-search-results {
    text-align: center;
    padding: 3em 0;
}

.search-suggestions {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 20px;
    margin-top: 20px;
    text-align: left;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.search-suggestions h3 {
    margin-top: 0;
    color: #0073aa;
}

.search-suggestions ul {
    margin-bottom: 0;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .policy-search-form {
        flex-direction: column;
    }
    
    .search-input-wrapper {
        min-width: auto;
    }
    
    .search-result-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .search-result-meta {
        flex-direction: column;
        gap: 5px;
    }
}

---

/* File: assets/policy-pdf.css */
/* PDF download link styling */

.pdf-download-link {
    text-align: right;
    margin-bottom: 12px;
}

.pdf-download-link a {
    display: inline-block;
    transition: transform 0.2s ease;
}

.pdf-download-link a:hover {
    transform: scale(1.1);
}

.pdf-download-link img {
    height: 32px;
    width: auto;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
}

/* Policy header container */
.policy-header-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    gap: 20px;
}

@media (max-width: 768px) {
    .policy-header-container {
        flex-direction: column;
        gap: 15px;
    }
    
    .pdf-download-link {
        text-align: left;
        order: -1;
    }
}

---

// File: assets/policy-accordion.js  
// Enhanced policy accordion with better event handling

document.addEventListener('DOMContentLoaded', function () {
    console.log('Policy accordion initializing...');
    initPolicyAccordion();
    initAccordionSearch();
});

function initPolicyAccordion() {
    const titles = document.querySelectorAll('.accordion-title');
    console.log('Found accordion titles:', titles.length);

    // Initialize individual toggle behavior
    titles.forEach((title, index) => {
        console.log('Initializing accordion item', index);
        
        title.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Accordion clicked:', this.textContent.trim());
            
            const content = this.nextElementSibling;
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            console.log('Current expanded state:', isExpanded);
            console.log('Content element:', content);
            
            // Toggle current item
            this.setAttribute('aria-expanded', !isExpanded);
            
            if (isExpanded) {
                content.style.display = 'none';
                this.classList.remove('expanded');
            } else {
                content.style.display = 'block';
                this.classList.add('expanded');
            }
            
            console.log('New expanded state:', !isExpanded);
        });
        
        // Set initial state
        title.setAttribute('aria-expanded', 'false');
        const content = title.nextElementSibling;
        if (content) {
            content.style.display = 'none';
        }
    });

    // Collapse All
    const collapseAllBtn = document.getElementById('collapse-all');
    if (collapseAllBtn) {
        console.log('Setting up collapse all button');
        collapseAllBtn.addEventListener('click', () => {
            console.log('Collapse all clicked');
            titles.forEach(title => {
                title.setAttribute('aria-expanded', 'false');
                const content = title.nextElementSibling;
                if (content) {
                    content.style.display = 'none';
                }
                title.classList.remove('expanded');
            });
        });
    }

    // Expand All
    const expandAllBtn = document.getElementById('expand-all');
    if (expandAllBtn) {
        console.log('Setting up expand all button');
        expandAllBtn.addEventListener('click', () => {
            console.log('Expand all clicked');
            titles.forEach(title => {
                title.setAttribute('aria-expanded', 'true');
                const content = title.nextElementSibling;
                if (content) {
                    content.style.display = 'block';
                }
                title.classList.add('expanded');
            });
        });
    }
}

function initAccordionSearch() {
    // Add search functionality to accordion
    const searchInput = document.querySelector('.policy-accordion-search');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        filterAccordionItems(searchTerm);
    });
}

function filterAccordionItems(searchTerm) {
    const accordionItems = document.querySelectorAll('.accordion-item');
    
    accordionItems.forEach(item => {
        const title = item.querySelector('.accordion-title').textContent.toLowerCase();
        const content = item.querySelector('.accordion-content').textContent.toLowerCase();
        
        if (searchTerm === '' || title.includes(searchTerm) || content.includes(searchTerm)) {
            item.style.display = 'block';
            
            // Auto-expand if searching and item matches
            if (searchTerm !== '' && (title.includes(searchTerm) || content.includes(searchTerm))) {
                const titleBtn = item.querySelector('.accordion-title');
                const content = item.querySelector('.accordion-content');
                titleBtn.setAttribute('aria-expanded', 'true');
                content.style.display = 'block';
                titleBtn.classList.add('expanded');
            }
        } else {
            item.style.display = 'none';
        }
    });
}