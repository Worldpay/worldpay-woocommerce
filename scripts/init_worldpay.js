WorldpayCheckout = function( $ ) {
    var submitFunction;
    var form;

    var checkSaveCardOn = function() {
        if ($('#worldpay_save_card_details').is(':checked')) {
            Worldpay.reusable = true;
        } else {
            Worldpay.reusable = false;
        } 
        return Worldpay.reusable;
    };
    var reattachHandlers = function() {
        $(form).off( "checkout_place_order_WC_Gateway_Worldpay");
        $(form).on( "checkout_place_order_WC_Gateway_Worldpay", function(){
            if ($('#worldpay_use_saved_card_details').attr('checked')) {
                WorldpayCheckout.updateCVC();
            } else {
                checkSaveCardOn();
                Worldpay.submitTemplateForm();
            }
            return false;
        });

        $(form).off("change", '#worldpay_save_card_details');

        $(form).on("change", '#worldpay_save_card_details', function(){
            if ($(this).is(':checked')) {
                Worldpay.reusable = true;
            } else {
                Worldpay.reusable = false;
            }
            WorldpayCheckout.setupNewCardForm();
        });

        // Code for payment screen
        if (document.getElementById('order_review')) {
            $('#place_order').off().click(function(e){
                if ($('#payment_method_WC_Gateway_Worldpay').is(':checked')) {
                    e.preventDefault();
                    if ($('#worldpay_use_saved_card_details').attr('checked')) {
                        WorldpayCheckout.updateCVC();
                    } else {
                        checkSaveCardOn();
                        Worldpay.submitTemplateForm();
                    }
                    return false;
                }
                else if ($('#payment_method_WC_Gateway_Worldpay_Paypal').is(':checked')) {
                    e.preventDefault();
                    WorldpayCheckout.createAPMForm('paypal');
                    return false;
                }
                else if ($('#payment_method_WC_Gateway_Worldpay_Giropay').is(':checked')) {
                    e.preventDefault();
                    WorldpayCheckout.createAPMForm('giropay');
                    return false;
                }
            });
        }
        
    };
    var temporarilyDetatchHandlers = function() {
        $(form).off( "checkout_place_order_WC_Gateway_Worldpay");
        $(form).on( "checkout_place_order_WC_Gateway_Worldpay", reattachHandlers);
    };

    var reattachHandlersPP = function() {
        $(form).off( "checkout_place_order_WC_Gateway_Worldpay_Paypal");
        $(form).on( "checkout_place_order_WC_Gateway_Worldpay_Paypal", function() {
           WorldpayCheckout.createAPMForm('paypal');
           return false;
        });
    };
    var temporarilyDetatchHandlersPP = function() {
        $(form).off( "checkout_place_order_WC_Gateway_Worldpay_Paypal");
        $(form).on( "checkout_place_order_WC_Gateway_Worldpay_Paypal", reattachHandlersPP);
    };

    var reattachHandlersGP = function() {
        $(form).off( "checkout_place_order_WC_Gateway_Worldpay_Giropay");
        $(form).on( "checkout_place_order_WC_Gateway_Worldpay_Giropay", function() {
           WorldpayCheckout.createAPMForm('giropay');
           return false;
        });
    };
    var temporarilyDetatchHandlersGP = function() {
        $(form).off( "checkout_place_order_WC_Gateway_Worldpay_Giropay");
        $(form).on( "checkout_place_order_WC_Gateway_Worldpay_Giropay", reattachHandlersGP);
    };

    Worldpay.setClientKey(WorldpayConfig.ClientKey);
    return {
        setupNewCardForm: function(){
            form = document.getElementsByName('checkout')[0] || document.getElementById('order_review');
            Worldpay.useTemplateForm({
                'clientKey':WorldpayConfig.ClientKey,
                'form':form,
                'reusable': checkSaveCardOn(),
                'paymentSection':'worldpay-templateform',
                'display':'inline',
                'saveButton':false,
                'callback': function(response) {
                  if (response && response.token) {
                    var errorMessage = $('#worldpay-payment-errors');
                    errorMessage.removeClass('woocommerce-error');
                    errorMessage.empty();
                    var token = response.token;
                    Worldpay.formBuilder(form, 'input', 'hidden', 'worldpay_token', token);
                    temporarilyDetatchHandlers();
                    $(form).submit();
                    return true;
                  }
                }
            });
            submitFunction = form.onsubmit;
            reattachHandlers();
            form.onsubmit = null;
        },
        updateCVC: function() {
            // Create form with cvc and token
            form = document.getElementsByName('checkout')[0] || document.getElementById('order_review');
            
            Worldpay.card.reuseToken(form, function(status, response) {
                var errorMessage = $('#worldpay-payment-errors');
                if (status != 200) {
                    errorMessage.html(response.error.message).addClass('woocommerce-error');
                    return false;
                } else {
                    errorMessage.removeClass('woocommerce-error');
                    errorMessage.empty();
                    temporarilyDetatchHandlers();
                    $(form).submit();
                    return true;
                }
            });
            return false;
        },
        createAPMForm: function(apmMode) {
             form = document.getElementsByName('checkout')[0] || document.getElementById('order_review');

             if (document.getElementById('billing_country')) {
                document.getElementById('billing_country').setAttribute('data-worldpay', 'country-code');
            } else {
                var i = document.createElement("input");
                i.setAttribute('type',"hidden");
                i.setAttribute('id',"billing_country");
                i.setAttribute('data-worldpay', 'country-code');
                i.setAttribute('value', 'GB');
                form.appendChild(i);
            }
            if (apmMode == 'giropay') {
                if (!document.getElementById('worldpay_swift_code').value) {
                    alert('Please enter a swift code');
                    return false;
                }
                document.getElementById('worldpay_swift_code').setAttribute("data-worldpay-apm", "swiftCode");
            } else {
                if (document.getElementById('worldpay_swift_code')) {
                    document.getElementById('worldpay_swift_code').removeAttribute("data-worldpay-apm", "swiftCode");
                }
            }

            if (document.getElementById('wp-apm-name')) {
                document.getElementById('wp-apm-name').value = apmMode;
            } else {
                var i = document.createElement("input");
                i.setAttribute('type',"hidden");
                i.setAttribute('id',"wp-apm-name");
                i.setAttribute('data-worldpay', 'apm-name');
                i.setAttribute('value', apmMode);
                form.appendChild(i);
            }
            Worldpay.reusable = false;

            Worldpay.apm.createToken(form, function(resp, message) {
                if (resp != 200) {
                    if (message.error.message)
                        alert(message.error.message);
                    else
                        alert(JSON.stringify(message));
                    return;
                }
                var token = message.token;
                Worldpay.formBuilder(form, 'input', 'hidden', 'worldpay_token', token);
                if (apmMode == 'paypal') {
                    temporarilyDetatchHandlersPP();
                } else {
                    temporarilyDetatchHandlersGP();
                }
                $(form).submit();
                return true;
            });
            submitFunction = form.onsubmit;
            form.onsubmit = null;
        },
        setupPayPalForm: function() {
            reattachHandlersPP();
        },
        setupGiropayForm: function() {
            reattachHandlersGP();
        }
    };
}(jQuery);
