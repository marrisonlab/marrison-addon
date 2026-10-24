/**
 * JavaScript per il Wizard di Configurazione (Popup Modal)
 */
(function($) {
    'use strict';
    
    var MarrisonWizard = {
        currentStep: 1,
        totalSteps: 5,
        scannedCookies: [],
        
        init: function() {
            this.totalSteps = Math.max(1, parseInt(marrisonWizard.totalSteps, 10) || 5);
            this.currentStep = Math.max(1, Math.min(this.totalSteps, parseInt(marrisonWizard.currentStep, 10) || 1));
            this.bindEvents();
            
            // Apri wizard se necessario
            if (marrisonWizard.shouldOpen) {
                this.openWizard();
            }
            
            this.showStep(this.currentStep);
            this.updateProgress();
        },
        
        bindEvents: function() {
            // Apri/chiudi wizard
            $(document).on('click', '.marrison-wizard-close', function(e) {
                e.preventDefault();
                MarrisonWizard.closeWizard();
            });
            
            // Chiudi cliccando overlay
            $(document).on('click', '.marrison-wizard-overlay', function(e) {
                if (e.target === this) {
                    MarrisonWizard.closeWizard();
                }
            });
            
            // Navigazione
            $(document).on('click', '#wizard_prev', function(e) {
                e.preventDefault();
                MarrisonWizard.prevStep();
            });
            
            $(document).on('click', '#wizard_skip_privacy', function(e) {
                e.preventDefault();
                if (MarrisonWizard.currentStep !== 5) { return; }
                MarrisonWizard.currentStep = 6;
                MarrisonWizard.showStep(6); MarrisonWizard.updateProgress(); MarrisonWizard.saveCurrentStep();
            });
            $(document).on('click', '#wizard_next', function(e) {
                e.preventDefault();
                MarrisonWizard.nextStep();
            });
            
            // Scansione cookie
            $(document).on('click', '#wizard_scan_button', function(e) {
                e.preventDefault();
                MarrisonWizard.scanCookies();
            });
            
            // Color picker update
            $(document).on('input', 'input[type="color"]', function() {
                $(this).siblings('span').text($(this).val());
            });
        },
        
        openWizard: function() {
            $('.marrison-wizard-overlay')
                .css({ display: '', opacity: '', visibility: '' })
                .addClass('active');
            $('body').css('overflow', 'hidden');
        },
        
        closeWizard: function() {
            $('.marrison-wizard-overlay')
                .removeClass('active')
                .css({ display: '', opacity: '', visibility: '' });
            $('body').css('overflow', '');
            
            // Invia AJAX per dismiss
            $.ajax({
                url: marrisonWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_wizard_dismiss',
                    nonce: marrisonWizard.nonce
                }
            });
        },
        
        showStep: function(step) {
            $('.marrison-wizard-step').removeClass('active');
            $('.marrison-wizard-step[data-step="' + step + '"]').addClass('active');
            
            // Aggiorna indicatori
            $('#step_indicator').text(marrisonWizard.stepText.replace('{step}', step).replace('{total}', this.totalSteps));
            
            // Aggiorna bottoni
            if (step === 1) {
                $('#wizard_prev').hide();
            } else {
                $('#wizard_prev').show();
            }
            $('#wizard_skip_privacy').toggle(step === 5);
            $('#wizard_next').text(step === this.totalSteps ? marrisonWizard.completeText : marrisonWizard.nextText);
            
        },
        
        updateProgress: function() {
            $('.marrison-progress-step').each(function() {
                var stepNum = parseInt($(this).data('step'), 10);
                $(this).removeClass('active completed');
                
                if (stepNum < MarrisonWizard.currentStep) {
                    $(this).addClass('completed');
                } else if (stepNum == MarrisonWizard.currentStep) {
                    $(this).addClass('active');
                }
            });
        },
        
        nextStep: function() {
            if (this.currentStep < this.totalSteps) {
                // Salva dati dello step corrente
                this.saveStepData(this.currentStep);
                
                this.currentStep++;
                this.showStep(this.currentStep);
                this.updateProgress();
                
                // Salva step corrente
                this.saveCurrentStep();
            } else {
                // Completa wizard
                this.finishWizard();
            }
        },
        
        prevStep: function() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.showStep(this.currentStep);
                this.updateProgress();
                this.saveCurrentStep();
            }
        },
        
        saveCurrentStep: function() {
            $.ajax({
                url: marrisonWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_wizard_save_step',
                    nonce: marrisonWizard.nonce,
                    step: this.currentStep,
                    data: { current_step: this.currentStep }
                },
                async: false
            });
        },
        
        saveStepData: function(step) {
            var data = {};
            
            switch(step) {
                case 2:
                    data = {
                        banner_title: $('#wizard_banner_title').val(),
                        banner_description: $('#wizard_banner_description').val(),
                        accept_button_text: $('#wizard_accept_text').val(),
                        reject_button_text: $('#wizard_reject_text').val(),
                        customize_button_text: $('#wizard_customize_text').val()
                    };
                    break;
                    
                case 3:
                    data = {
                        categories: this.getCookieCategories()
                    };
                    break;
                    
                case 4:
                    data = {
                        banner_layout: $('#wizard_banner_layout').val(),
                        banner_position: $('#wizard_banner_position').val(),
                        box_position: $('#wizard_box_position').val(),
                        banner_background_color: $('#wizard_banner_bg_color').val(),
                        banner_text_color: $('#wizard_banner_text_color').val(),
                        button_background_color: $('#wizard_button_bg_color').val(),
                        button_text_color: $('#wizard_button_text_color').val()
                    };
                    break;
            }
            
            if (step === 5) {
                data.privacy_profile = {};
                $('.marrison-wizard-step[data-step="5"] [data-privacy-field]').each(function() {
                    var field = $(this);
                    data.privacy_profile[field.attr('data-privacy-field')] = field.is(':checkbox') ? (field.prop('checked') ? '1' : '0') : field.val();
                });
            }
            // Invia dati al server
            $.ajax({
                url: marrisonWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_wizard_save_step',
                    nonce: marrisonWizard.nonce,
                    step: step,
                    data: data
                },
                async: false
            });
        },
        
        getCookieCategories: function() {
            var categories = {};
            $('.marrison-cookie-table select').each(function() {
                var cookieId = $(this).data('cookie-id');
                var category = $(this).val();
                if (cookieId) {
                    categories[cookieId] = category;
                }
            });
            return categories;
        },
        
        scanCookies: function() {
            if (window.MarrisonCookieScanner) {
                window.MarrisonCookieScanner.start(true);
                return;
            }
            var $button = $('#wizard_scan_button');
            var $status = $('#wizard_scan_status');
            
            $button.prop('disabled', true);
            $status.removeClass('success error')
                  .addClass('loading')
                  .text(marrisonWizard.scanningText)
                  .show();
            
            $.ajax({
                url: marrisonWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_wizard_scan_cookies',
                    nonce: marrisonWizard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('loading')
                              .addClass('success')
                              .text(marrisonWizard.scanSuccessText);
                        
                        MarrisonWizard.scannedCookies = response.data.cookies;
                        MarrisonWizard.renderCookieTable(response.data.cookies);
                        
                        $('#wizard_cookie_results').slideDown();
                    } else {
                        $status.removeClass('loading')
                              .addClass('error')
                              .text(marrisonWizard.scanErrorText + ': ' + response.data.message);
                    }
                    
                    $button.prop('disabled', false);
                },
                error: function() {
                    $status.removeClass('loading')
                          .addClass('error')
                          .text(marrisonWizard.connectionErrorText);
                    
                    $button.prop('disabled', false);
                }
            });
        },
        
        renderCookieTable: function(cookies) {
            var $tbody = $('#wizard_cookie_table_body');
            $tbody.empty();
            
            if (!cookies || cookies.length === 0) {
                $tbody.append('<tr><td colspan="3">' + marrisonWizard.noCookiesText + '</td></tr>');
                return;
            }
            
            var categoryOptions = `
                <option value="necessary">${marrisonWizard.categoryNecessary}</option>
                <option value="functional">${marrisonWizard.categoryFunctional}</option>
                <option value="analytics">${marrisonWizard.categoryAnalytics}</option>
                <option value="marketing">${marrisonWizard.categoryMarketing}</option>
            `;
            
            $.each(cookies, function(index, cookie) {
                var row = `
                    <tr>
                        <td><strong>${MarrisonWizard.escapeHtml(cookie.cookie_name)}</strong></td>
                        <td>
                            <select class="marrison-wizard-category-select" data-cookie-id="${cookie.id}">
                                ${categoryOptions.replace('value="' + cookie.cookie_category + '"', 'value="' + cookie.cookie_category + '" selected')}
                            </select>
                        </td>
                        <td>${cookie.source === 'catalogo' ? 'Catalogo · dichiarato, non osservato' : MarrisonWizard.escapeHtml(cookie.source)}</td>
                    </tr>
                `;
                $tbody.append(row);
            });
        },
        
        finishWizard: function() {
            var fields = $('#wizard_privacy_policy_url, #wizard_cookie_policy_url').toArray();
            for (var i = 0; i < fields.length; i++) {
                if (!fields[i].reportValidity()) {
                    return;
                }
            }
            this.completeWizard();
        },
        
        completeWizard: function() {
            var $button = $('#wizard_next');
            var $status = $('#wizard_policy_status');
            $button.prop('disabled', true);
            $status.hide().removeClass('success error loading');
            $.ajax({
                url: marrisonWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'marrison_wizard_finish',
                    nonce: marrisonWizard.nonce,
                    data: {
                        privacy_policy_url: $('#wizard_privacy_policy_url').val(),
                        cookie_policy_url: $('#wizard_cookie_policy_url').val()
                    }
                },
                success: function(response) {
                    if (response.success) {
                        // Mostra messaggio di successo
                        $('.marrison-wizard-step').removeClass('active');
                        $('.marrison-wizard-step[data-step="completed"]').addClass('active');
                        
                        // Nascondi footer
                        $('.marrison-wizard-footer').hide();
                        
                        // Chiudi wizard e redirect dopo 3 secondi
                        setTimeout(function() {
                            MarrisonWizard.closeWizard();
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 500);
                        }, 3000);
                    } else {
                        $status.addClass('error').text(response.data && response.data.message ? response.data.message : marrisonWizard.finishErrorText).show();
                    }
                },
                error: function() {
                    $status.addClass('error').text(marrisonWizard.connectionErrorText).show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },
        
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    window.MarrisonWizard = MarrisonWizard;
    
    $(document).ready(function() {
        MarrisonWizard.init();
    });
    
})(jQuery);
