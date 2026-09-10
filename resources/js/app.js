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

// The shop's motion lives in its own chunk. The back office never downloads it, and the shop
// only asks for it once this file has run, so it never holds up the first paint.
if (document.documentElement.classList.contains('storefront')) {
    import('./storefront/index.js');
}
