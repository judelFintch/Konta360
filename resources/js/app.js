import { Collapse } from '@flexilla/collapse';

function initializeFlexilla() {
    Collapse.autoInit('[data-fx-collapse]');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFlexilla, { once: true });
} else {
    initializeFlexilla();
}

document.addEventListener('livewire:navigated', initializeFlexilla);
