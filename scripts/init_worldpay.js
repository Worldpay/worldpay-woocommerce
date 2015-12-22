WorldpayCheckout = function( $ ) {
    var submitFunction;
    var form;
    var reattachHandlers = function() {
        $(form).off( "checkout_place_order_WC_Gateway_Worldpay");
        $(form).on( "checkout_place_order_WC_Gateway_Worldpay", submitFunction);
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
    Worldpay.setClientKey(WorldpayConfig.ClientKey);
    Worldpay.reusable = true;
    return {
        setupNewCardForm: function(){
            form = document.getElementsByName('checkout')[0];
            Worldpay.useTemplateForm({
                'clientKey':WorldpayConfig.ClientKey,
                'form':form,
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
        setupReusableCardForm: function(){
            form = document.getElementsByName('checkout')[0];
            Worldpay.useForm(form, function(status, response) {
                if (response.error) {
                    Worldpay.handleError(form, document.getElementById('worldpay-payment-errors'), response.error);
                    return false;
                } else {
                    temporarilyDetatchHandlers();
                    $(form).submit();
                    return true;
                }
            }, true);
            submitFunction = form.onsubmit;
            reattachHandlers();
            form.onsubmit = null;
        },
        createAPMForm: function(apmMode) {
             form = document.getElementsByName('checkout')[0];
             document.getElementById('billing_country').setAttribute('data-worldpay', 'country-code');
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
            Worldpay.apm.createToken(form, function(resp, message) {
                if (resp != 200) {
                    alert(message.error.message);
                    return;
                }
                var token = message.token;
                Worldpay.formBuilder(form, 'input', 'hidden', 'worldpay_token', token);
                temporarilyDetatchHandlersPP();
                $(form).submit();
                return true;
            });
            submitFunction = form.onsubmit;
            form.onsubmit = null;
        },
        setupPayPalForm: function() {
            reattachHandlersPP();
        }
    };
}(jQuery);
