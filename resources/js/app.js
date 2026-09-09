import './bootstrap';
import richTextEditor from './rich-text-editor';

// Registered before Alpine starts, which Livewire does for us once this module has run.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('richTextEditor', richTextEditor);
});

window.richTextEditor = richTextEditor;
