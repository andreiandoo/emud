import './bootstrap';
import richTextEditor from './rich-text-editor';
import mountScrollIndicator from './scroll-indicator';

// Registered before Alpine starts, which Livewire does for us once this module has run.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('richTextEditor', richTextEditor);
});

window.richTextEditor = richTextEditor;

// Guards on the storefront class itself, so the back office keeps its native scrollbars.
mountScrollIndicator();
