(function () {
    'use strict';

    function init() {
        document.querySelectorAll('.fp-exp-repeater').forEach(initRepeater);
        initFrequencyPanels();
    }

    function initRepeater(container) {
        const itemsContainer = container.querySelector('.fp-exp-repeater-items');
        const template = container.querySelector('template[data-repeater-template]');
        const addButton = container.querySelector('[data-repeater-add]');

        if (!itemsContainer || !template || !addButton) {
            return;
        }

        let nextIndex = parseInt(container.getAttribute('data-next-index'), 10);
        if (!isFinite(nextIndex) || nextIndex < itemsContainer.children.length) {
            nextIndex = itemsContainer.children.length;
        }

        addButton.addEventListener('click', function (event) {
            event.preventDefault();

            const fragment = template.content.cloneNode(true);
            assignNames(fragment, nextIndex);
            itemsContainer.appendChild(fragment);

            nextIndex += 1;
            container.setAttribute('data-next-index', String(nextIndex));
        });

        itemsContainer.addEventListener('click', function (event) {
            const button = event.target.closest('[data-repeater-remove]');
            if (!button) {
                return;
            }

            event.preventDefault();
            const row = button.closest('[data-repeater-item]');
            if (row) {
                row.remove();
            }
        });
    }

    function assignNames(fragment, index) {
        fragment.querySelectorAll('[data-name]').forEach(function (field) {
            const templateName = field.getAttribute('data-name');
            if (!templateName) {
                return;
            }

            const fieldName = templateName.replace(/__INDEX__/g, String(index));
            field.setAttribute('name', fieldName);
            field.removeAttribute('data-name');
        });
    }

    function initFrequencyPanels() {
        const selector = document.querySelector('[data-frequency-selector]');
        if (!selector) {
            return;
        }

        const panels = document.querySelectorAll('[data-frequency-panel], [data-frequency-panel-secondary]');

        function updatePanels() {
            const value = selector.value;
            panels.forEach(function (panel) {
                const primary = panel.getAttribute('data-frequency-panel');
                const secondary = panel.getAttribute('data-frequency-panel-secondary');

                if ((primary && primary === value) || (secondary && secondary === value)) {
                    panel.style.display = '';
                } else if (primary || secondary) {
                    panel.style.display = 'none';
                }
            });
        }

        selector.addEventListener('change', updatePanels);
        updatePanels();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
