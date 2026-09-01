(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const wrapper = document.getElementById('review_form_wrapper');
        const title = document.getElementById('reply-title');
        const form = document.getElementById('commentform');

        // Ensure all elements exist
        if (!wrapper || !title || !form) {
            return;
        }

        // Initialize state
        wrapper.classList.add('wcri-accordion-wrapper');
        title.classList.add('wcri-accordion-title');

        // Hide form initially
        wrapper.classList.add('wcri-closed');

        title.addEventListener('click', function () {
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
        });
    });
})();
