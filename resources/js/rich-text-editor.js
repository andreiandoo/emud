/**
 * Alpine component behind the product description editor.
 *
 * Two views over one value: the rich editor and the HTML behind it. Product descriptions arrive
 * from supplier feeds as raw HTML of wildly varying quality, so dropping into the source to fix
 * one by hand is not a power-user nicety here — it is how a bad feed gets cleaned.
 *
 * Spread into x-data alongside an entangled `content`, so Livewire owns the value:
 *   x-data="{ ...richTextEditor(), content: @entangle('description') }"
 *
 * Tiptap is imported dynamically: it is about 120 kB gzipped, and app.js is loaded by the
 * storefront too, where nobody is editing anything.
 */
export default function richTextEditor() {
    return {
        editor: null,
        source: false,
        // Bumped on every change so Alpine re-evaluates the toolbar. Tiptap's selection lives
        // outside Alpine's reactivity, so without this the active-state highlighting never moves.
        version: 0,
        // Set while the editor writes back, so the watcher can tell its own echo from a genuine
        // edit made in the source textarea.
        writingBack: false,

        async init() {
            const [{ Editor }, { default: StarterKit }, { Link }] = await Promise.all([
                import('@tiptap/core'),
                import('@tiptap/starter-kit'),
                import('@tiptap/extension-link'),
            ])

            this.editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    StarterKit.configure({ heading: { levels: [2, 3, 4] } }),
                    Link.configure({ openOnClick: false }),
                ],
                content: this.content || '',
                editorProps: {
                    attributes: {
                        class: 'prose prose-sm max-w-none min-h-[16rem] p-3 focus:outline-none',
                    },
                },
                onUpdate: ({ editor }) => {
                    this.writingBack = true
                    this.content = editor.getHTML()
                    this.version++
                    this.$nextTick(() => { this.writingBack = false })
                },
                onSelectionUpdate: () => { this.version++ },
            })

            this.$watch('content', (value) => {
                if (this.writingBack || !this.editor) {
                    return
                }

                // Only when it genuinely differs: setContent resets the cursor, and doing that
                // on every keystroke would make the editor unusable.
                if (value !== this.editor.getHTML()) {
                    this.editor.commands.setContent(value || '', false)
                }
            })
        },

        destroy() {
            this.editor?.destroy()
        },

        toggleSource() {
            // Leaving the source view hands whatever was typed back to the editor, so neither
            // view can go stale behind the other.
            this.source = !this.source

            if (!this.source && this.editor) {
                this.editor.commands.setContent(this.content || '', false)
            }
        },

        run(command, options = {}) {
            this.editor?.chain().focus()[command](options).run()
            this.version++
        },

        isActive(name, options = {}) {
            this.version

            return this.editor?.isActive(name, options) ?? false
        },

        setLink() {
            const previous = this.editor?.getAttributes('link').href ?? ''
            const url = window.prompt('Adresa linkului', previous)

            if (url === null) {
                return
            }

            const chain = this.editor?.chain().focus().extendMarkRange('link')
            url === '' ? chain?.unsetLink().run() : chain?.setLink({ href: url }).run()
            this.version++
        },
    }
}
