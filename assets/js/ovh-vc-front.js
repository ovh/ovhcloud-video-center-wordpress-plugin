/**
 * OVHcloud Video Center – Front-end JavaScript
 *
 * Handles the modal player for [ovhcloud_vc_gallery] shortcode thumbnails.
 *
 * @package OVH_VideoCenter
 * @since   0.0.1
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        bindVideoGridClicks();
    });

    /**
     * Bind click events on video thumbnails to open the modal player.
     */
    function bindVideoGridClicks() {
        // Delegate clicks on video thumbnails.
        document.addEventListener('click', function (e) {
            var thumb = e.target.closest('.ovh-vc-video-thumb');
            if (thumb) {
                e.preventDefault();
                var embedUrl = thumb.getAttribute('data-embed-url');
                if (embedUrl) {
                    openModal(thumb, embedUrl);
                }
            }

            // Close modal on overlay or close button click.
            if (e.target.closest('.ovh-vc-front-modal-close') || e.target.classList.contains('ovh-vc-front-modal-overlay')) {
                closeAllModals();
            }
        });

        // Close modal on Escape key.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAllModals();
            }
        });
    }

    /**
     * Open the modal player near the clicked grid.
     *
     * @param {Element} thumb    The clicked thumbnail element.
     * @param {string}  embedUrl The video embed URL.
     */
    function openModal(thumb, embedUrl) {
        // Find the closest modal container (sibling of the grid).
        // After the first open the modal is re-parented to <body>, so fall back
        // to a document-wide search when it is no longer found in the grid parent.
        var grid = thumb.closest('.ovh-vc-video-gallery') || thumb.closest('.ovh-vc-video-grid');
        var modal = (grid && grid.parentElement.querySelector('.ovh-vc-front-modal'))
            || document.querySelector('.ovh-vc-front-modal');

        if (!modal) return;

        // Move modal to body to avoid parent CSS (transforms, overflow) breaking position:fixed.
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        var player = modal.querySelector('.ovh-vc-front-player');
        if (player) {
            player.innerHTML = '<iframe src="' + escAttr(embedUrl) + '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        }

        modal.style.display = 'flex';

        // Prevent body scroll.
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close all open modals.
     */
    function closeAllModals() {
        var modals = document.querySelectorAll('.ovh-vc-front-modal');
        modals.forEach(function (modal) {
            modal.style.display = 'none';
            var player = modal.querySelector('.ovh-vc-front-player');
            if (player) {
                player.innerHTML = '';
            }
        });
        document.body.style.overflow = '';
    }

    /**
     * Escape a string for use in HTML attributes.
     *
     * @param {string} str Input string.
     * @return {string} Escaped string.
     */
    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

})();
