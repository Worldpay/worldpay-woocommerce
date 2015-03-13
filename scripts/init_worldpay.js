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
    Worldpay.setClientKey(WorldpayConfig.ClientKey);
    Worldpay.reusable = true;
    return {
        setupNewCardForm: function(){
            form = document.getElementsByName('checkout')[0];
            Worldpay.useForm(form, function(status, response) {
                $("input#place_order").prop("disabled", false);
                if (response.error) {
                    $('#worldpay-payment-errors').addClass('woocommerce-error');
                    Worldpay.handleError(form, document.getElementById('worldpay-payment-errors'), response.error);
                    return false;
                } else {
                    var errorMessage = $('#worldpay-payment-errors');
                    errorMessage.removeClass('woocommerce-error');
                    errorMessage.empty();
                    var token = response.token;
                    Worldpay.formBuilder(form, 'input', 'hidden', 'worldpay_token', token);
                    temporarilyDetatchHandlers();
                    $(form).submit();
                    return true;
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
        }
    };
}(jQuery);