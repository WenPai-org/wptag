jQuery(document).ready(function($) {
    if (typeof window.gtag !== 'undefined') {
        delete window.gtag;
    }
    if (typeof window.fbq !== 'undefined') {
        delete window.fbq;
    }
    if (typeof window.dataLayer !== 'undefined') {
        delete window.dataLayer;
    }
    
    window.gtag = function() {
        console.log('WPTag: gtag() calls are disabled in admin area');
    };
    window.fbq = function() {
        console.log('WPTag: fbq() calls are disabled in admin area');
    };
    
    WPTagAdmin.init();
    
    $(window).on('load', function() {
        setTimeout(function() {
            WPTagAdmin.initializing = false;
            WPTagAdmin.changeTrackingEnabled = true;
        }, 500);
    });
});

var WPTagAdmin = {
    
    formChanged: false,
    changeTrackingEnabled: false,
    initializing: true,
    
    init: function() {
        var $ = jQuery;
        var self = this;
        
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
        this.initTooltips();
        
        setTimeout(function() {
            self.initializing = false;
            self.handleFormChangeTracking();
        }, 1000);
    },
    
    handleServiceToggle: function() {
        var $ = jQuery;
        var self = this;
        
        $('.wptag-service-card input[name*="[enabled]"]').on('change', function() {
            var $card = $(this).closest('.wptag-service-card');
            var isEnabled = $(this).is(':checked');
            
            self.changeTrackingEnabled = false;
            
            if (isEnabled) {
                $card.removeClass('disabled').addClass('enabled');
                $card.find('.wptag-service-content input, .wptag-service-content select, .wptag-service-content textarea, .wptag-service-content button').prop('disabled', false);
            } else {
                $card.removeClass('enabled').addClass('disabled');
                $card.find('.wptag-service-content input, .wptag-service-content select, .wptag-service-content textarea, .wptag-service-content button').prop('disabled', true);
                $card.find('.wptag-validation-result').hide();
            }
            
            setTimeout(function() {
                self.changeTrackingEnabled = true;
                if (!self.initializing) {
                    self.formChanged = true;
                }
            }, 100);
        });
        
        $('.wptag-service-card input[name*="[enabled]"]').each(function() {
            $(this).trigger('change');
        });
    },
    
    handleCodeTypeToggle: function() {
        var $ = jQuery;
        var self = this;
        
        $('input[name*="[use_template]"]').on('change', function() {
            var $card = $(this).closest('.wptag-service-card');
            var useTemplate = $(this).val() === '1' && $(this).is(':checked');
            
            self.changeTrackingEnabled = false;
            
            if (useTemplate) {
                $card.find('.wptag-template-fields').slideDown(300);
                $card.find('.wptag-custom-fields').slideUp(300);
            } else {
                $card.find('.wptag-template-fields').slideUp(300);
                $card.find('.wptag-custom-fields').slideDown(300);
            }
            
            $card.find('.wptag-validation-result').hide();
            
            setTimeout(function() {
                self.changeTrackingEnabled = true;
                if (!self.initializing) {
                    self.formChanged = true;
                }
            }, 100);
        });
        
        $('input[name*="[use_template]"]:checked').trigger('change');
    },
    
    handleValidation: function() {
        var $ = jQuery;
        
        $('.wptag-validate-btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $card = $btn.closest('.wptag-service-card');
            var service = $card.data('service');
            var $result = $card.find('.wptag-validation-result');
            
            var useTemplate = $card.find('input[name*="[use_template]"]:checked').val() === '1';
            var idValue = $card.find('input[type="text"]').first().val().trim();
            var customCode = $card.find('textarea').val().trim();
            
            if (useTemplate && !idValue) {
                WPTagAdmin.showValidationResult($result, 'invalid', 'Please enter an ID value first');
                return;
            }
            
            if (!useTemplate && !customCode) {
                WPTagAdmin.showValidationResult($result, 'invalid', 'Please enter custom code first');
                return;
            }
            
            $btn.addClass('wptag-button-loading').prop('disabled', true);
            WPTagAdmin.showValidationResult($result, 'loading', wptagAdmin.strings.validating);
            
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
                        WPTagAdmin.showValidationResult($result, 'valid', '✓ ' + response.data.message);
                    } else {
                        WPTagAdmin.showValidationResult($result, 'invalid', '✗ ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Validation error:', error);
                    WPTagAdmin.showValidationResult($result, 'invalid', '✗ Validation failed. Please try again.');
                },
                complete: function() {
                    $btn.removeClass('wptag-button-loading').prop('disabled', false);
                }
            });
        });
        
        $('input[type="text"], textarea').on('input', function() {
            $(this).closest('.wptag-service-card').find('.wptag-validation-result').hide();
            if (WPTagAdmin.changeTrackingEnabled && !WPTagAdmin.initializing) {
                WPTagAdmin.formChanged = true;
            }
        });
    },
    
    showValidationResult: function($result, type, message) {
        $result.removeClass('valid invalid loading')
               .addClass(type)
               .text(message)
               .show();
    },
    
    handlePreview: function() {
        var $ = jQuery;
        
        $('.wptag-preview-btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $card = $btn.closest('.wptag-service-card');
            var service = $card.data('service');
            var idValue = $card.find('input[type="text"]').first().val().trim();
            
            if (!idValue) {
                alert('Please enter an ID value to preview');
                $card.find('input[type="text"]').first().focus();
                return;
            }
            
            $btn.addClass('wptag-button-loading').prop('disabled', true);
            
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
                        var previewCode = response.data.preview;
                        $('#wptag-preview-code').text(previewCode);
                        $('#wptag-preview-modal').fadeIn(300);
                        $('body').addClass('modal-open');
                    } else {
                        WPTagAdmin.showNotice('error', 'Preview Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Preview error:', error);
                    WPTagAdmin.showNotice('error', 'Failed to generate preview. Please try again.');
                },
                complete: function() {
                    $btn.removeClass('wptag-button-loading').prop('disabled', false);
                }
            });
        });
    },
    
    handleExportImport: function() {
        var $ = jQuery;
        
        $('#wptag-export-btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            $btn.addClass('wptag-button-loading').prop('disabled', true);
            
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
                        WPTagAdmin.showNotice('error', 'Export failed: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Export error:', error);
                    WPTagAdmin.showNotice('error', 'Export failed. Please try again.');
                },
                complete: function() {
                    $btn.removeClass('wptag-button-loading').prop('disabled', false);
                }
            });
        });
        
        $('#wptag-import-btn').on('click', function(e) {
            e.preventDefault();
            $('#wptag-import-file').click();
        });
        
        $('#wptag-import-file').on('change', function() {
            var file = this.files[0];
            if (!file) return;
            
            if (!file.name.endsWith('.json')) {
                WPTagAdmin.showNotice('error', 'Please select a valid JSON file.');
                this.value = '';
                return;
            }
            
            if (!confirm(wptagAdmin.strings.confirm_import)) {
                this.value = '';
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
                            WPTagAdmin.formChanged = false;
                            WPTagAdmin.initializing = true;
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            WPTagAdmin.showNotice('error', 'Import failed: ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Import error:', error);
                        WPTagAdmin.showNotice('error', 'Import failed. Please try again.');
                    }
                });
            };
            
            reader.onerror = function() {
                WPTagAdmin.showNotice('error', 'Failed to read the file.');
            };
            
            reader.readAsText(file);
            this.value = '';
        });
    },
    
    handleReset: function() {
        var $ = jQuery;
        
        $('#wptag-reset-btn').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wptagAdmin.strings.confirm_reset)) {
                return;
            }
            
            var $btn = $(this);
            $btn.addClass('wptag-button-loading').prop('disabled', true);
            
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
                        WPTagAdmin.formChanged = false;
                        WPTagAdmin.initializing = true;
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        WPTagAdmin.showNotice('error', 'Reset failed: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Reset error:', error);
                    WPTagAdmin.showNotice('error', 'Reset failed. Please try again.');
                },
                complete: function() {
                    $btn.removeClass('wptag-button-loading').prop('disabled', false);
                }
            });
        });
    },
    
    handleServicesManagement: function() {
        var $ = jQuery;
        var self = this;
        
        $('#wptag-enable-all').on('click', function(e) {
            e.preventDefault();
            self.changeTrackingEnabled = false;
            $('input[name="enabled_services[]"]').prop('checked', true).trigger('change');
            setTimeout(function() {
                self.changeTrackingEnabled = true;
                self.formChanged = true;
            }, 100);
        });
        
        $('#wptag-disable-all').on('click', function(e) {
            e.preventDefault();
            self.changeTrackingEnabled = false;
            $('input[name="enabled_services[]"]').prop('checked', false).trigger('change');
            setTimeout(function() {
                self.changeTrackingEnabled = true;
                self.formChanged = true;
            }, 100);
        });
        
        $('.wptag-service-item input[type="checkbox"]').on('change', function() {
            var $item = $(this).closest('.wptag-service-item');
            var isChecked = $(this).is(':checked');
            
            if (isChecked) {
                $item.removeClass('disabled').addClass('enabled');
            } else {
                $item.removeClass('enabled').addClass('disabled');
            }
            
            if (self.changeTrackingEnabled && !self.initializing) {
                self.formChanged = true;
            }
        });
        
        $('.wptag-service-item input[type="checkbox"]').trigger('change');
    },
    
    handleModal: function() {
        var $ = jQuery;
        
        $('.wptag-modal-close, .wptag-modal-backdrop').on('click', function(e) {
            if (e.target === this) {
                WPTagAdmin.closeModal();
            }
        });
        
        $('.wptag-modal-content').on('click', function(e) {
            e.stopPropagation();
        });
        
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#wptag-preview-modal').is(':visible')) {
                WPTagAdmin.closeModal();
            }
        });
    },
    
    closeModal: function() {
        var $ = jQuery;
        $('#wptag-preview-modal').fadeOut(300, function() {
            $('body').removeClass('modal-open');
        });
    },
    
    handleFormSubmission: function() {
        var $ = jQuery;
        var self = this;
        
        $('.wptag-settings-form').on('submit', function(e) {
            var hasErrors = false;
            var $form = $(this);
            
            $('.wptag-service-card.enabled').each(function() {
                var $card = $(this);
                var useTemplate = $card.find('input[name*="[use_template]"]:checked').val() === '1';
                
                if (useTemplate) {
                    var $idInput = $card.find('input[type="text"]').first();
                    if ($idInput.val().trim() === '') {
                        WPTagAdmin.showNotice('error', 'Please enter an ID for all enabled services or disable them.');
                        $idInput.focus();
                        hasErrors = true;
                        return false;
                    }
                } else {
                    var $customCodeTextarea = $card.find('textarea');
                    if ($customCodeTextarea.val().trim() === '') {
                        WPTagAdmin.showNotice('error', 'Please enter custom code for all enabled services or disable them.');
                        $customCodeTextarea.focus();
                        hasErrors = true;
                        return false;
                    }
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                return false;
            }
            
            var $saveBtn = $form.find('#wptag-save-btn');
            $saveBtn.addClass('wptag-button-loading').prop('disabled', true).val('Saving...');
            self.formChanged = false;
        });
        
        $('#wptag-services-form').on('submit', function() {
            var $saveBtn = $(this).find('input[type="submit"]');
            $saveBtn.addClass('wptag-button-loading').prop('disabled', true).val('Saving...');
            WPTagAdmin.formChanged = false;
        });
    },
    
    handleTabSwitching: function() {
        var $ = jQuery;
        var self = this;
        
        $('.nav-tab').on('click', function(e) {
            if (self.formChanged && !self.initializing) {
                var confirmed = confirm('You have unsaved changes. Do you want to continue without saving?');
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                } else {
                    self.formChanged = false;
                }
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
                $advanced.slideUp(300);
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                $btn.html('<span class="dashicons dashicons-arrow-down-alt2"></span>' + wptagAdmin.strings.advanced_settings);
            } else {
                $advanced.slideDown(300);
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $btn.html('<span class="dashicons dashicons-arrow-up-alt2"></span>' + wptagAdmin.strings.hide_advanced);
            }
        });
    },
    
    handleCodeEditor: function() {
        var $ = jQuery;
        
        $('.wptag-code-editor').each(function() {
            var $editor = $(this);
            
            $editor.on('input', function() {
                WPTagAdmin.updateEditorHeight(this);
                if (WPTagAdmin.changeTrackingEnabled && !WPTagAdmin.initializing) {
                    WPTagAdmin.formChanged = true;
                }
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
        
        $('.wptag-format-code').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $editor = $btn.closest('.wptag-code-editor-wrapper').find('.wptag-code-editor');
            var code = $editor.val();
            
            if (code.trim()) {
                try {
                    var formatted = WPTagAdmin.formatCode(code);
                    $editor.val(formatted);
                    WPTagAdmin.updateEditorHeight($editor[0]);
                    if (WPTagAdmin.changeTrackingEnabled && !WPTagAdmin.initializing) {
                        WPTagAdmin.formChanged = true;
                    }
                    WPTagAdmin.showNotice('success', 'Code formatted successfully.');
                } catch (e) {
                    console.log('Code formatting failed:', e);
                    WPTagAdmin.showNotice('error', 'Code formatting failed. Please check your code syntax.');
                }
            }
        });
        
        $('.wptag-clear-code').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to clear the code?')) {
                var $btn = $(this);
                var $editor = $btn.closest('.wptag-code-editor-wrapper').find('.wptag-code-editor');
                $editor.val('').focus();
                WPTagAdmin.updateEditorHeight($editor[0]);
                if (WPTagAdmin.changeTrackingEnabled && !WPTagAdmin.initializing) {
                    WPTagAdmin.formChanged = true;
                }
            }
        });
    },
    
    handleFormChangeTracking: function() {
        var $ = jQuery;
        var self = this;
        
        self.changeTrackingEnabled = true;
        
        $(document).on('input keyup', 'input[type="text"], textarea', function() {
            if ($(this).closest('.wptag-admin').length > 0 && self.changeTrackingEnabled && !self.initializing) {
                self.formChanged = true;
            }
        });
        
        $(document).on('change', 'select, input[type="radio"], input[type="checkbox"]:not([name*="enabled_services"]):not([name*="[enabled]"]):not([name*="[use_template]"])', function() {
            if ($(this).closest('.wptag-admin').length > 0 && self.changeTrackingEnabled && !self.initializing) {
                self.formChanged = true;
            }
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (self.formChanged && !self.initializing) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    },
    
    initTooltips: function() {
        var $ = jQuery;
        
        $('[title]').each(function() {
            var $el = $(this);
            var title = $el.attr('title');
            
            $el.removeAttr('title').on('mouseenter', function() {
                $('<div class="wptag-tooltip">' + title + '</div>')
                    .appendTo('body')
                    .fadeIn(200);
            }).on('mouseleave', function() {
                $('.wptag-tooltip').remove();
            }).on('mousemove', function(e) {
                $('.wptag-tooltip').css({
                    top: e.pageY + 10,
                    left: e.pageX + 10
                });
            });
        });
    },
    
    updateEditorHeight: function(editor) {
        var $ = jQuery;
        var $editor = $(editor);
        
        editor.style.height = 'auto';
        var newHeight = Math.max(editor.scrollHeight, 120);
        editor.style.height = newHeight + 'px';
    },
    
    formatCode: function(code) {
        try {
            return code
                .replace(/></g, '>\n<')
                .replace(/^\s+|\s+$/g, '')
                .split('\n')
                .map(function(line, index, array) {
                    line = line.trim();
                    if (line.length === 0) return '';
                    
                    var indent = 0;
                    for (var i = 0; i < index; i++) {
                        var prevLine = array[i].trim();
                        if (prevLine.match(/<[^\/][^>]*[^\/]>$/)) {
                            indent += 2;
                        }
                        if (prevLine.match(/<\/[^>]+>$/)) {
                            indent -= 2;
                        }
                    }
                    
                    if (line.match(/^<\/[^>]+>$/)) {
                        indent -= 2;
                    }
                    
                    indent = Math.max(0, indent);
                    return ' '.repeat(indent) + line;
                })
                .filter(function(line) {
                    return line.trim().length > 0;
                })
                .join('\n');
        } catch (e) {
            console.log('Code formatting error:', e);
            return code;
        }
    },
    
    downloadFile: function(data, filename) {
        try {
            var blob = new Blob([data], { type: 'application/json' });
            
            if (window.navigator && window.navigator.msSaveOrOpenBlob) {
                window.navigator.msSaveOrOpenBlob(blob, filename);
                return;
            }
            
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            
            setTimeout(function() {
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            }, 100);
        } catch (e) {
            console.error('Download failed:', e);
            WPTagAdmin.showNotice('error', 'Download failed. Please try again.');
        }
    },
    
    showNotice: function(type, message) {
        var $ = jQuery;
        
        $('.wptag-admin .notice').remove();
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
        $('.wptag-admin h1').after($notice);
        
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        $('html, body').animate({
            scrollTop: $('.wptag-admin').offset().top - 50
        }, 500);
    }
};