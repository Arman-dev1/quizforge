import Sortable from 'sortablejs';

/**
 * x-sortable — drag-and-drop lists wired to Livewire.
 *
 * Container attributes:
 *   data-sort-method  Livewire method: (itemId, target, newIndex)
 *   data-sort-group   optional group name to allow dragging between lists
 *   data-sort-target  optional target id passed to the method (e.g. page id)
 * Item attributes:
 *   data-sort-id      the model id
 * Handle: any child with data-sort-handle.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.directive('sortable', (el) => {
        Sortable.create(el, {
            group: el.dataset.sortGroup || undefined,
            handle: '[data-sort-handle]',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd(event) {
                const destination = event.to;
                const itemId = event.item?.dataset.sortId;
                const method = destination.dataset.sortMethod || el.dataset.sortMethod;
                const root = destination.closest('[wire\\:id]');

                if (!itemId || !method || !root) {
                    return;
                }

                window.Livewire.find(root.getAttribute('wire:id'))
                    ?.call(method, itemId, destination.dataset.sortTarget ?? null, event.newIndex);
            },
        });
    });
});
