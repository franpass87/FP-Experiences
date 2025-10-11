/* assets/js/admin/gallery-controls.js */
/**
 * Admin Gallery Controls Module
 */
(function () {
    'use strict';

    function initGalleryControls(root) {
        const galleryControls = root.querySelectorAll('[data-fp-gallery-control]');
        if (!galleryControls.length) return;

        galleryControls.forEach(control => {
            const button = control.querySelector('[data-fp-gallery-button]');
            const input = control.querySelector('[data-fp-gallery-input]');
            const list = control.querySelector('[data-fp-gallery-list]');
            const remove = control.querySelector('[data-fp-gallery-remove]');

            if (!button || !input) return;

            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (window.wp && window.wp.media) {
                    const frame = window.wp.media({
                        title: window.fpExpAdmin.getString('Select Gallery Images'),
                        button: {
                            text: window.fpExpAdmin.getString('Add to Gallery')
                        },
                        multiple: true
                    });

                    frame.on('select', function() {
                        const attachments = frame.state().get('selection').toJSON();
                        addToGallery(attachments, input, list);
                    });

                    frame.open();
                }
            });

            // Gestione rimozione elementi
            if (list) {
                list.addEventListener('click', function(e) {
                    if (e.target.matches('[data-fp-gallery-remove-item]')) {
                        e.preventDefault();
                        const item = e.target.closest('[data-fp-gallery-item]');
                        if (item) {
                            removeFromGallery(item, input, list);
                        }
                    }
                });
            }
        });
    }

    function addToGallery(attachments, input, list) {
        const currentIds = input.value ? input.value.split(',').map(id => parseInt(id)) : [];
        
        attachments.forEach(attachment => {
            if (!currentIds.includes(attachment.id)) {
                currentIds.push(attachment.id);
                addGalleryItem(attachment, list);
            }
        });
        
        input.value = currentIds.join(',');
    }

    function addGalleryItem(attachment, list) {
        const item = document.createElement('div');
        item.className = 'fp-exp-gallery-control__item';
        item.setAttribute('data-fp-gallery-item', attachment.id);
        
        item.innerHTML = `
            <div class="fp-exp-gallery-control__thumb">
                <img src="${attachment.sizes?.thumbnail?.url || attachment.url}" alt="${attachment.alt || ''}">
            </div>
            <div class="fp-exp-gallery-control__toolbar">
                <button type="button" class="fp-exp-gallery-control__remove" data-fp-gallery-remove-item>
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        `;
        
        list.appendChild(item);
    }

    function removeFromGallery(item, input, list) {
        const itemId = parseInt(item.getAttribute('data-fp-gallery-item'));
        const currentIds = input.value ? input.value.split(',').map(id => parseInt(id)) : [];
        const newIds = currentIds.filter(id => id !== itemId);
        
        input.value = newIds.join(',');
        item.remove();
    }

    // Esporta funzioni
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initGalleryControls = initGalleryControls;

})();


