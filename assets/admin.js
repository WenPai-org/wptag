jQuery(document).ready(function($) {
    WPTagAdmin.init();
});

var WPTagAdmin = {
    
    init: function() {
        var $ = jQuery;
        this.handleServiceToggle();
        this.handleCodeTypeToggle();
        this.handleValidation();
        this.handlePreview();
        this.handleExportImport();
        this.handleModal();
        this.handleFormSubmission();
        this.handleServicesManagement();
        this.handleReset();
        this.handleTabSwitching();
        this.handleAdvancedToggle();
        this.handleCodeEditor();
    },
    
    handleServiceToggle: function() {
        var $ = jQuery;
        $('.wptag-service-card input[name*="[enabled]"]').on('change', function() {
            var $card = $(this).closest('.wptag-service-card');
            var isEnabled = $(this).is(':checked');
            
            if (isEnabled) {
                $card.removeClass('disabled');
                $card.find('.wptag-service-content input, .wptag-service-content select, .wptag-service-content textarea, .wptag-service-content button').prop('disabled', false);
            } else {
                $card.addClass('disabled');
                $card.find('.wptag-service-content input, .wptag-service-content select, .wptag-service-content textarea, .wptag-service-content button').prop('disabled', true);
            }
        });
        
        $('.wptag-service-card input[name*="[enabled]"]').trigger('change');
    },
    
    handleCodeTypeToggle: function() {
        var $ = jQuery;
        $('input[name*="[use_template]"]').on('change', function() {
            var $card = $(this).closest('.wptag-service-card');
            var useTemplate = $(this).val() === '1' && $(this).is(':checked');
            
            if (useTemplate) {
                $card.find('.wptag-template-fields').show();
                $card.find('.wptag-custom-fields').hide();
            } else {
                $card.find('.wptag-template-fields').hide();
                $card.find('.wptag-custom-fields').show();
            }
        });
        
        $('input[name*="[use_template]"]:checked').trigger('change');
    },
    
    handleValidation: function() {
        var $ = jQuery;
        $('.wptag-validate-btn').on('click', function() {
            var $btn = $(this);
            var $card = $btn.closest('.wptag-service-card');
            var service = $card.data('service');
            var $result = $card.find('.wptag-validation-result');
            
            var useTemplate = $card.find('input[name*="[use_template]"]:checked').val() === '1';
            var idValue = $card.find('input[type="text"]').first().val();
            var customCode = $card.find('textarea').val();
            
            $btn.prop('disabled', true).text(wptagAdmin.strings.validating);
            $result.removeClass('valid invalid').addClass('loading').text(wptagAdmin.strings.validating);
            
            $.ajax({
                url: wptagAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wptag_validate_code',
                    nonce: wptagAdmin.nonce,
                    service: service,
                    use_template: useTemplate ? '1' : '0',
                    id_value: idValue,
                    custom_code: customCode
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('loading invalid').addClass('valid')
                               .text('✓ ' + response.data.message);
                    } else {
                        $result.removeClass('loading valid').addClass('invalid')
                               .text('✗ ' + response.data.message);
                    }
                },
                error: function() {
                    $result.removeClass('loading valid').addClass('invalid')
                           .text('✗ Validation failed');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Validate');
                }
            });
        });
        
        $('input[type="text"], textarea').on('input', function() {
            $(this).closest('.wptag-service-card').find('.wptag-validation-result').text('');
        });
    },
    
    handlePreview: function() {
        var $ = jQuery;
        $('.wptag-preview-btn').on('click', function() {
            var $btn = $(this);
            var $card = $btn.closest('.wptag-service-card');
            var service = $card.data('service');
            var idValue = $card.find('input[type="text"]').first().val();
            
            if (!idValue.trim()) {
                alert('Please enter an ID value to preview');
                return;
            }
            
            $btn.prop('disabled', true).text(wptagAdmin.strings.loading);
            
            $.ajax({
                url: wptagAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wptag_preview_code',
                    nonce: wptagAdmin.nonce,
                    service: service,
                    id_value: idValue
                },
                success: function(response) {
                    if (response.success) {
                        $('#wptag-preview-code').text(response.data.preview);
                        $('#wptag-preview-modal').show();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Failed to generate preview');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(wptagAdmin.strings.preview);
                }
            });
        });
    },
    
    handleExportImport: function() {
        var $ = jQuery;
        $('#wptag-export-btn').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.text();
            $btn.prop('disabled', true).text(wptagAdmin.strings.loading);
            
            $.ajax({
                url: wptagAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wptag_export_settings',
                    nonce: wptagAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPTagAdmin.downloadFile(response.data.data, response.data.filename);
                        WPTagAdmin.showNotice('success', wptagAdmin.strings.export_success);
                    } else {
                        alert('Export failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Export failed');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        $('#wptag-import-btn').on('click', function() {
            $('#wptag-import-file').click();
        });
        
        $('#wptag-import-file').on('change', function() {
            var file = this.files[0];
            if (!file) return;
            
            if (!confirm(wptagAdmin.strings.confirm_import)) {
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                var importData = e.target.result;
                
                $.ajax({
                    url: wptagAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wptag_import_settings',
                        nonce: wptagAdmin.nonce,
                        import_data: importData
                    },
                    success: function(response) {
                        if (response.success) {
                            WPTagAdmin.showNotice('success', wptagAdmin.strings.import_success);
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            alert('Import failed: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Import failed');
                    }
                });
            };
            reader.readAsText(file);
            
            this.value = '';
        });
    },
    
    handleReset: function() {
        var $ = jQuery;
        $('#wptag-reset-btn').on('click', function() {
            if (!confirm(wptagAdmin.strings.confirm_reset)) {
                return;
            }
            
            var $btn = $(this);
            var originalText = $btn.text();
            $btn.prop('disabled', true).text(wptagAdmin.strings.loading);
            
            $.ajax({
                url: wptagAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wptag_reset_settings',
                    nonce: wptagAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPTagAdmin.showNotice('success', wptagAdmin.strings.reset_success);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert('Reset failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Reset failed');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
    },
    
    handleServicesManagement: function() {
        var $ = jQuery;
        $('#wptag-enable-all').on('click', function() {
            $('input[name="enabled_services[]"]').prop('checked', true);
            $('.wptag-service-item').removeClass('disabled');
        });
        
        $('#wptag-disable-all').on('click', function() {
            $('input[name="enabled_services[]"]').prop('checked', false);
            $('.wptag-service-item').addClass('disabled');
        });
        
        $('.wptag-service-item input[type="checkbox"]').on('change', function() {
            var $item = $(this).closest('.wptag-service-item');
            var isChecked = $(this).is(':checked');
            
            if (isChecked) {
                $item.removeClass('disabled');
            } else {
                $item.addClass('disabled');
            }
        });
        
        $('.wptag-service-item input[type="checkbox"]').trigger('change');
    },
    
    handleModal: function() {
        var $ = jQuery;
        $('.wptag-modal-close, #wptag-preview-modal').on('click', function(e) {
            if (e.target === this) {
                $('#wptag-preview-modal').hide();
            }
        });
        
        $('.wptag-modal-content').on('click', function(e) {
            e.stopPropagation();
        });
        
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#wptag-preview-modal').is(':visible')) {
                $('#wptag-preview-modal').hide();
            }
        });
    },
    
    handleFormSubmission: function() {
        var $ = jQuery;
        $('.wptag-settings-form').on('submit', function(e) {
            var hasErrors = false;
            
            $('.wptag-service-card').each(function() {
                var $card = $(this);
                var isEnabled = $card.find('input[name*="[enabled]"]').is(':checked');
                
                if (!isEnabled) {
                    return;
                }
                
                var useTemplate = $card.find('input[name*="[use_template]"]:checked').val() === '1';
                
                if (useTemplate) {
                    var idInput = $card.find('input[type="text"]').first();
                    if (idInput.val().trim() === '') {
                        alert('Please enter an ID for enabled services or disable them.');
                        idInput.focus();
                        hasErrors = true;
                        return false;
                    }
                } else {
                    var customCodeTextarea = $card.find('textarea');
                    if (customCodeTextarea.val().trim() === '') {
                        alert('Please enter custom code for enabled services or disable them.');
                        customCodeTextarea.focus();
                        hasErrors = true;
                        return false;
                    }
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                return false;
            }
            
            $(this).find('input[type="submit"]').prop('disabled', true).val('Saving...');
        });
    },
    
    handleTabSwitching: function() {
        var $ = jQuery;
        $('.nav-tab').on('click', function(e) {
            var href = $(this).attr('href');
            if (href && href.indexOf('tab=') !== -1) {
                var tab = href.split('tab=')[1];
                var currentUrl = window.location.href.split('?')[0];
                var newUrl = currentUrl + '?page=wptag-settings&tab=' + tab;
                window.location.href = newUrl;
            }
        });
    },
    
    handleAdvancedToggle: function() {
        var $ = jQuery;
        $('.wptag-toggle-advanced').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $card = $btn.closest('.wptag-service-card');
            var $advanced = $card.find('.wptag-advanced-settings');
            var $icon = $btn.find('.dashicons');
            
            if ($advanced.is(':visible')) {
                $advanced.slideUp();
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                $btn.html('<span class="dashicons dashicons-arrow-down-alt2"></span>Advanced Settings');
            } else {
                $advanced.slideDown();
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $btn.html('<span class="dashicons dashicons-arrow-up-alt2"></span>Hide Advanced Settings');
            }
        });
    },
    
    handleCodeEditor: function() {
        var $ = jQuery;
        $('.wptag-code-editor').each(function() {
            var $editor = $(this);
            
            $editor.on('input', function() {
                WPTagAdmin.updateEditorHeight(this);
            });
            
            $editor.on('keydown', function(e) {
                if (e.keyCode === 9) {
                    e.preventDefault();
                    var start = this.selectionStart;
                    var end = this.selectionEnd;
                    var value = this.value;
                    this.value = value.substring(0, start) + "  " + value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 2;
                }
            });
            
            WPTagAdmin.updateEditorHeight(this);
        });
        
        $('.wptag-format-code').on('click', function() {
            var $btn = $(this);
            var $editor = $btn.closest('.wptag-code-editor-wrapper').find('.wptag-code-editor');
            var code = $editor.val();
            
            if (code.trim()) {
                try {
                    var formatted = WPTagAdmin.formatCode(code);
                    $editor.val(formatted);
                    WPTagAdmin.updateEditorHeight($editor[0]);
                } catch (e) {
                    console.log('Code formatting failed:', e);
                }
            }
        });
        
        $('.wptag-clear-code').on('click', function() {
            if (confirm('Are you sure you want to clear the code?')) {
                var $btn = $(this);
                var $editor = $btn.closest('.wptag-code-editor-wrapper').find('.wptag-code-editor');
                $editor.val('');
                WPTagAdmin.updateEditorHeight($editor[0]);
            }
        });
    },
    
    updateEditorHeight: function(editor) {
        editor.style.height = 'auto';
        editor.style.height = Math.max(editor.scrollHeight, 120) + 'px';
    },
    
    formatCode: function(code) {
        try {
            return code
                .replace(/></g, '>\n<')
                .replace(/^\s+|\s+$/g, '')
                .split('\n')
                .map(function(line) {
                    return line.trim();
                })
                .filter(function(line) {
                    return line.length > 0;
                })
                .join('\n');
        } catch (e) {
            console.log('Code formatting error:', e);
            return code;
        }
    },
    
    downloadFile: function(data, filename) {
        var blob = new Blob([data], { type: 'application/json' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    },
    
    showNotice: function(type, message) {
        var $ = jQuery;
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wptag-admin h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }
};