(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        console.log('[WCRI Toggle] DOMContentLoaded fired');

        const wrapper = document.getElementById('review_form_wrapper');
        const title = document.getElementById('reply-title');
        const form = document.getElementById('commentform');

        console.log('[WCRI Toggle] Elements:', { wrapper, title, form });

        // Ensure all elements exist
        if (!wrapper || !title || !form) {
            console.log('[WCRI Toggle] Missing elements, aborting');
            return;
        }

        // Initialize state
        wrapper.classList.add('wcri-accordion-wrapper');
        title.classList.add('wcri-accordion-title');

        // Hide form initially
        // We use a specific class for the closed state to allow CSS transitions if desired
        wrapper.classList.add('wcri-closed');
        console.log('[WCRI Toggle] Classes added, wrapper classList:', wrapper.classList.toString());

        title.addEventListener('click', function () {
            console.log('[WCRI Toggle] Title clicked');
            const isClosed = wrapper.classList.contains('wcri-closed');

            if (isClosed) {
                // Open
                wrapper.classList.remove('wcri-closed');
                wrapper.classList.add('wcri-open');
            } else {
                // Close
                wrapper.classList.remove('wcri-open');
                wrapper.classList.add('wcri-closed');
            }
            console.log('[WCRI Toggle] New state:', wrapper.classList.toString());
        });
    });
})();
