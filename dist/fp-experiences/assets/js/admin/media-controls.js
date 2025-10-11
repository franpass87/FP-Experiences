/**
 * Admin Media Controls Module
 */
(function () {
    'use strict';

    function initMediaControls(root) {
        const mediaControls = root.querySelectorAll('[data-fp-media-control]');
        if (!mediaControls.length) return;

        mediaControls.forEach(control => {
            const button = control.querySelector('[data-fp-media-button]');
            const input = control.querySelector('[data-fp-media-input]');
            const preview = control.querySelector('[data-fp-media-preview]');
            const remove = control.querySelector('[data-fp-media-remove]');

            if (!button || !input) return;

            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (window.wp && window.wp.media) {
                    const frame = window.wp.media({
                        title: window.fpExpAdmin.getString('Select Media'),
                        button: {
                            text: window.fpExpAdmin.getString('Use This Media')
                        },
                        multiple: false
                    });

                    frame.on('select', function() {
                        const attachment = frame.state().get('selection').first().toJSON();
                        updateMediaPreview(attachment, input, preview, remove);
                    });

                    frame.open();
                }
            });

            if (remove) {
                remove.addEventListener('click', function(e) {
                    e.preventDefault();
                    clearMediaPreview(input, preview, remove);
                });
            }
        });
    }

    function updateMediaPreview(attachment, input, preview, remove) {
        input.value = attachment.id;
        
        if (preview) {
            const img = preview.querySelector('img');
            if (img) {
                img.src = attachment.sizes?.thumbnail?.url || attachment.url;
                img.alt = attachment.alt || '';
            }
            preview.style.display = 'block';
        }
        
        if (remove) {
            remove.style.display = 'inline-block';
        }
    }

    function clearMediaPreview(input, preview, remove) {
        input.value = '';
        
        if (preview) {
            preview.style.display = 'none';
        }
        
        if (remove) {
            remove.style.display = 'none';
        }
    }

    // Esporta funzioni
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initMediaControls = initMediaControls;

})();
