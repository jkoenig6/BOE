document.addEventListener('DOMContentLoaded', function () {
    const titles = document.querySelectorAll('.accordion-title');

    // Initialize individual toggle behavior
    titles.forEach(title => {
        title.addEventListener('click', () => {
            const content = title.nextElementSibling;
            const isExpanded = title.getAttribute('aria-expanded') === 'true';
            title.setAttribute('aria-expanded', !isExpanded);
            content.style.display = isExpanded ? 'none' : 'block';
        });
    });

    // Collapse All
    const collapseAllBtn = document.getElementById('collapse-all');
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', () => {
            titles.forEach(title => {
                title.setAttribute('aria-expanded', 'false');
                title.nextElementSibling.style.display = 'none';
            });
        });
    }

    // Expand All
    const expandAllBtn = document.getElementById('expand-all');
    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', () => {
            titles.forEach(title => {
                title.setAttribute('aria-expanded', 'true');
                title.nextElementSibling.style.display = 'block';
            });
        });
    }
});
