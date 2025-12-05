/**
 * BCC Modal Trigger
 * Adds modal trigger functionality to the subscribe button
 */

(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        // Find the specific subscribe button
        const subscribeBtn = document.getElementById('bcc-daily-email-subscribe-btn-9324589349034');
        
        if (!subscribeBtn) {
            return; // Button not on this page, exit silently
        }
        
        // Add modal trigger attributes
        subscribeBtn.setAttribute('data-bs-toggle', 'modal');
        subscribeBtn.setAttribute('data-bs-target', '#bccSubscribeModal');
        subscribeBtn.style.cursor = 'pointer';
        
        // Make it clickable if it's a div
        if (subscribeBtn.tagName === 'DIV') {
            subscribeBtn.setAttribute('role', 'button');
            subscribeBtn.setAttribute('tabindex', '0');
        }
        
        // Add click handler
        subscribeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const modal = document.getElementById('bccSubscribeModal');
            if (!modal) return;
            
            // Try Bootstrap modal if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
                bsModal.show();
                return;
            }
            
            // Fallback: manual open (for yakstrap)
            modal.style.display = 'flex';
            requestAnimationFrame(function() {
                modal.removeAttribute('aria-hidden');
                modal.removeAttribute('inert');
                modal.classList.add('show');
                if (document.body.classList) {
                    document.body.classList.add('yak-modal-open');
                }
            });
        });
    });
})();

