(function($) {
    'use strict';

    const WPTagAdmin = {
        init: function() {
            this.bindEvents();
            this.initCodeEditor();
            this.initConditionsBuilder();
        },

        bindEvents: function() {
            $(document).on('click', '.wptag-toggle-status', this.toggleStatus);
            $(document).on('click', '.wptag-delete-snippet', this.deleteSnippet);
            $(document).on('change', '.wptag-filter', this.filterSnippets);
            $(document).on('click', '.wptag-template-use', this.useTemplate);
            $(document).on('submit', '.wptag-snippet-form', this.validateForm);
            $(document).on('click', '.wptag-add-condition', this.addCondition);
            $(document).on('click', '.wptag-remove-condition', this.removeCondition);
            $(document).on('change', '#code_type', this.updateCodeEditor);
        },

        toggleStatus: function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const snippetId = $link.data('snippet-id');
            
            $.ajax({
                url: wptagAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wptag_toggle_snippet',
                    snippet_id: snippetId,
                    nonce: wptagAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || wptagAdmin.strings.error);
                    }
                },
                error: function() {
                    alert(wptagAdmin.strings.error);
                }
            });
        },

        deleteSnippet: function(e) {
            e.preventDefault();
            
            if (!confirm(wptagAdmin.strings.confirmDelete)) {
                return;
            }
            
            const $link = $(this);
            const snippetId = $link.data('snippet-id');
            
            $.ajax({
                url: wptagAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wptag_delete_snippet',
                    snippet_id: snippetId,
                    nonce: wptagAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $link.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || wptagAdmin.strings.error);
                    }
                },
                error: function() {
                    alert(wptagAdmin.strings.error);
                }
            });
        },

        filterSnippets: function() {
            const filters = {
                search: $('#filter-search').val(),
                category: $('#filter-category').val(),
                position: $('#filter-position').val(),
                status: $('#filter-status').val()
            };
            
            const params = new URLSearchParams(filters);
            params.delete('page');
            
            Object.keys(filters).forEach(key => {
                if (!filters[key]) {
                    params.delete(key);
                }
            });
            
            window.location.href = window.location.pathname + '?page=wptag-snippets&' + params.toString();
        },

        initCodeEditor: function() {
            const $codeTextarea = $('#snippet-code');
            
            if ($codeTextarea.length && typeof wp !== 'undefined' && wp.codeEditor) {
                const editorSettings = wp.codeEditor.defaultSettings || {};
                const codeType = $('#code_type').val();
                
                editorSettings.codemirror = {
                    ...editorSettings.codemirror,
                    mode: this.getCodeMirrorMode(codeType),
                    lineNumbers: true,
                    lineWrapping: true,
                    indentUnit: 2,
                    tabSize: 2
                };
                
                this.codeEditor = wp.codeEditor.initialize($codeTextarea, editorSettings);
            }
        },

        getCodeMirrorMode: function(codeType) {
            const modes = {
                'html': 'htmlmixed',
                'javascript': 'javascript',
                'css': 'css'
            };
            
            return modes[codeType] || 'htmlmixed';
        },

        updateCodeEditor: function() {
            const codeType = $(this).val();
            
            if (WPTagAdmin.codeEditor && WPTagAdmin.codeEditor.codemirror) {
                WPTagAdmin.codeEditor.codemirror.setOption('mode', WPTagAdmin.getCodeMirrorMode(codeType));
            }
        },

        initConditionsBuilder: function() {
            const $builder = $('.wptag-conditions-builder');
            
            if (!$builder.length) {
                return;
            }
            
            this.loadConditionTypes();
        },

        loadConditionTypes: function() {
            $.ajax({
                url: wptagAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wptag_get_condition_types',
                    nonce: wptagAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPTagAdmin.conditionTypes = response.data.types;
                    }
                }
            });
        },

        addCondition: function(e) {
            e.preventDefault();
            
            const $group = $(this).closest('.wptag-condition-group');
            const $newRow = WPTagAdmin.createConditionRow();
            
            $group.find('.wptag-conditions-list').append($newRow);
        },

        removeCondition: function(e) {
            e.preventDefault();
            
            $(this).closest('.wptag-condition-row').fadeOut(300, function() {
                $(this).remove();
            });
        },

        createConditionRow: function() {
            const html = `
                <div class="wptag-condition-row">
                    <select name="conditions[rules][][type]" class="condition-type">
                        <option value="">Select Type</option>
                        <option value="page_type">Page Type</option>
                        <option value="user_status">User Status</option>
                        <option value="device_type">Device Type</option>
                    </select>
                    <select name="conditions[rules][][operator]" class="condition-operator">
                        <option value="equals">Equals</option>
                        <option value="not_equals">Not Equals</option>
                    </select>
                    <input type="text" name="conditions[rules][][value]" class="condition-value" placeholder="Value">
                    <button type="button" class="button wptag-remove-condition">Remove</button>
                </div>
            `;
            
            return $(html);
        },

        useTemplate: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const serviceType = $button.data('service-type');
            
            $.ajax({
                url: wptagAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wptag_get_template',
                    service_type: serviceType,
                    nonce: wptagAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPTagAdmin.showTemplateModal(response.data.template);
                    } else {
                        alert(response.data.message || wptagAdmin.strings.error);
                    }
                }
            });
        },

        showTemplateModal: function(template) {
            const fields = template.config_fields.map(field => {
                return `
                    <div class="wptag-form-field">
                        <label for="${field.name}">${field.label}</label>
                        <input type="${field.type || 'text'}" 
                               id="${field.name}" 
                               name="${field.name}" 
                               ${field.required ? 'required' : ''}>
                    </div>
                `;
            }).join('');
            
            const modalHtml = `
                <div class="wptag-modal" id="template-config-modal">
                    <div class="wptag-modal-content">
                        <div class="wptag-modal-header">
                            <h2>${template.service_name} Configuration</h2>
                        </div>
                        <form id="template-config-form">
                            <div class="wptag-modal-body">
                                ${fields}
                            </div>
                            <div class="wptag-modal-footer">
                                <button type="button" class="button" onclick="WPTagAdmin.closeModal()">Cancel</button>
                                <button type="submit" class="button button-primary">Create Snippet</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('#template-config-modal').fadeIn();
            
            $('#template-config-form').on('submit', function(e) {
                e.preventDefault();
                WPTagAdmin.processTemplate(template.service_type, $(this).serialize());
            });
        },

        processTemplate: function(serviceType, formData) {
            $.ajax({
                url: wptagAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wptag_process_template',
                    service_type: serviceType,
                    config: formData,
                    nonce: wptagAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = `admin.php?page=wptag-snippets&action=edit&snippet_id=${response.data.snippet_id}`;
                    } else {
                        alert(response.data.message || wptagAdmin.strings.error);
                    }
                }
            });
        },

        closeModal: function() {
            $('.wptag-modal').fadeOut(300, function() {
                $(this).remove();
            });
        },

        validateForm: function(e) {
            const $form = $(this);
            const name = $form.find('#snippet-name').val();
            const code = WPTagAdmin.codeEditor ? 
                WPTagAdmin.codeEditor.codemirror.getValue() : 
                $form.find('#snippet-code').val();
            
            if (!name.trim()) {
                e.preventDefault();
                alert('Please enter a snippet name');
                return false;
            }
            
            if (!code.trim()) {
                e.preventDefault();
                alert('Please enter some code');
                return false;
            }
            
            return true;
        }
    };

    $(document).ready(function() {
        WPTagAdmin.init();
    });

})(jQuery);
