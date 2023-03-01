pimcore.registerNS("pimcore.bundle.tinymce.editor");
pimcore.bundle.tinymce.editor = Class.create({
    languageMapping: {
        fr: 'fr_FR',
        pt: 'pt_PT',
        sv: 'sv_SE',
        th: 'th_TH',
        hu: 'hu_HU'
    },

    maxChars: -1,

    initialize: function () {
        document.addEventListener(parent.pimcore.events.initializeWysiwyg, this.initializeWysiwyg.bind(this));
        document.addEventListener(parent.pimcore.events.createWysiwyg, this.createWysiwyg.bind(this));
        document.addEventListener(parent.pimcore.events.onDropWysiwyg, this.onDropWysiwyg.bind(this));
        document.addEventListener(parent.pimcore.events.beforeDestroyWysiwyg, this.beforeDestroyWysiwyg.bind(this));
    },

    initializeWysiwyg: function (e) {
        if (e.detail.context === 'object') {
            if (!isNaN(e.detail.config.maxCharacters) && e.detail.config.maxCharacters > 0) {
                this.maxChars = e.detail.config.maxCharacters;
            }
        }

        this.config = e.detail.config;
    },

    createWysiwyg: function (e) {
        this.textareaId = e.detail.textarea.id ?? e.detail.textarea;

        const userLanguage = pimcore.globalmanager.get("user").language;
        let language = this.languageMapping[userLanguage];
        if (!language) {
            language = userLanguage;
        }
        if(language !== 'en') {
            language = {language: language};
        } else {
            language = {};
        }

        const toolbar1 = 'undo redo | formatselect | ' +
            'bold italic | alignleft aligncenter ' +
            'alignright alignjustify | link';

        const toolbar2 = 'table | bullist numlist outdent indent | removeformat | code | help';
        let toolbar;
        if (e.detail.context === 'translation') {
            toolbar = {
                toolbar1: toolbar1,
                toolbar2: toolbar2
            };
        } else {
            toolbar = {
                toolbar1: `${toolbar1} | ${toolbar2}`
            };
        }

        let subSpace = '';
        if (e.detail.context === 'document') {
            subSpace = 'editables';
        } else if (e.detail.context === 'object') {
            subSpace = 'tags';
        }

        let defaultConfig = {};
        if('' !== subSpace) {
            defaultConfig = parent.pimcore[e.detail.context][subSpace].wysiwyg ? parent.pimcore[e.detail.context][subSpace].wysiwyg.defaultEditorConfig : {};
        }

        tinymce.init(Object.assign({
            selector: `#${this.textareaId}`,
            height: 500,
            menubar: false,
            plugins: [
                'autolink', 'lists', 'link', 'image', 'code',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
            inline: true,
            base_url: '/bundles/pimcoretinymce/build/tinymce',
            suffix: '.min',
            convert_urls: false,
            extended_valid_elements: 'a[name|href|target|title|pimcore_type|pimcore_id],img[style|longdesc|usemap|src|border|alt=|title|hspace|vspace|width|height|align|pimcore_type|pimcore_id]',
            init_instance_callback: function (editor) {
                editor.on('input', function (eChange) {
                    const charCount = tinymce.activeEditor.plugins.wordcount.body.getCharacterCount();
                    if (this.maxChars !== -1 && charCount > this.maxChars) {
                        pimcore.helpers.showNotification(t('error'), t('char_count_limit_reached'), 'error');
                    }
                    document.dispatchEvent(new CustomEvent(pimcore.events.changeWysiwyg, {
                        detail: {
                            e: eChange,
                            data: tinymce.activeEditor.contentAreaContainer.innerHTML,
                            context: e.detail.context
                        }
                    }));
                }.bind(this));
                editor.on('blur', function (eChange) {
                    document.dispatchEvent(new CustomEvent(pimcore.events.changeWysiwyg, {
                        detail: {
                            e: eChange,
                            data: tinymce.activeEditor.contentAreaContainer.innerHTML,
                            context: e.detail.context
                        }
                    }));
                }.bind(this));
            }.bind(this)

        }, language, toolbar, defaultConfig, this.config));

    },

    onDropWysiwyg: function (e) {
        let data = e.detail.data;

        let record = data.records[0];
        data = record.data;

        if (!tinymce.activeEditor) {
            return;
        }

        // we have to focus the editor otherwise an error is thrown in the case the editor wasn't opend before a drop element
        tinymce.activeEditor.focus();

        let wrappedText = data.text;
        let textIsSelected = false;

        let retval = tinymce.activeEditor.selection.getContent();
        if (retval.length > 0) {
            wrappedText = retval;
            textIsSelected = true;
        }

        // remove existing links out of the wrapped text
        wrappedText = wrappedText.replace(/<\/?([a-z][a-z0-9]*)\b[^>]*>/gi, function ($0, $1) {
            if ($1.toLowerCase() === "a") {
                return "";
            }
            return $0;
        });

        const id = data.id;
        let uri = data.path;
        const browserPossibleExtensions = ["jpg", "jpeg", "gif", "png"];

        if (data.elementType === "asset") {
            if (data.type === "image" && textIsSelected === false) {
                // images bigger than 600px or formats which cannot be displayed by the browser directly will be
                // converted by the pimcore thumbnailing service so that they can be displayed in the editor
                let defaultWidth = 600;
                let additionalAttributes = {};

                if (typeof data.imageWidth != "undefined") {
                    const route = 'pimcore_admin_asset_getimagethumbnail';
                    const params = {
                        id: id,
                        width: defaultWidth,
                        aspectratio: true
                    };

                    uri = Routing.generate(route, params);

                    if (data.imageWidth < defaultWidth
                        && in_arrayi(pimcore.helpers.getFileExtension(data.text),
                            browserPossibleExtensions)) {
                        uri = data.path;
                        additionalAttributes = mergeObject(additionalAttributes, {pimcore_disable_thumbnail: true});
                    }

                    if (data.imageWidth < defaultWidth) {
                        defaultWidth = data.imageWidth;
                    }

                    additionalAttributes = mergeObject(additionalAttributes, {style: `width:${defaultWidth}px;`});
                }

                additionalAttributes = mergeObject(additionalAttributes, {
                    src: uri,
                    pimcore_type: 'asset',
                    pimcore_id: id,
                    target: '_blank',
                    alt: 'asset_image'
                });
                tinymce.activeEditor.selection.setContent(tinymce.activeEditor.dom.createHTML('img', additionalAttributes));
                return true;
            } else {
                tinymce.activeEditor.selection.setContent(tinymce.activeEditor.dom.createHTML('a', {
                    href: uri,
                    pimcore_type: 'asset',
                    pimcore_id: id,
                    target: '_blank'
                }, wrappedText));
                return true;
            }
        }

        if (data.elementType === "document" && (data.type === "page"
            || data.type === "hardlink" || data.type === "link")) {
            tinymce.activeEditor.selection.setContent(tinymce.activeEditor.dom.createHTML('a', {
                href: uri,
                pimcore_type: 'document',
                pimcore_id: id
            }, wrappedText));
            return true;
        }

        if (data.elementType === "object") {
            tinymce.activeEditor.selection.setContent(tinymce.activeEditor.dom.createHTML('a', {
                href: uri,
                pimcore_type: 'object',
                pimcore_id: id
            }, wrappedText));
            return true;
        }
    },

    beforeDestroyWysiwyg: function (e) {
        tinymce.remove(`#${this.textareaId}`);
    }
})

new pimcore.bundle.tinymce.editor();
