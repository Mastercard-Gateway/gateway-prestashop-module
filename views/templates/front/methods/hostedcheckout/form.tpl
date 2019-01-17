<form id="payment-form" method="POST" onsubmit="return payWithHostedCheckout()" action="{$hostedcheckout_action_url nofilter}"></form>
<script async src="{$hostedcheckout_component_url}"
        data-error="errorCallback"
        data-complete="completeCallback"
        data-cancel="cancelCallback">
</script>
<script>
    function configureHostedCheckout(sessionData) {
        Checkout.configure({
            merchant: '{$mpgs_config.merchant_id}',
            session: {
                id: sessionData.session_id,
                version: sessionData.session_version
            },
            order: {
                description: 'Customer Order'
            }
        });
        Checkout.showLightbox();
    }

    function payWithHostedCheckout() {
        $('#payment-confirmation button').prop('disabled', true);

        var xhr = $.ajax({
            method: 'GET',
            url: '{$hostedcheckout_action_url nofilter}',
            dataType: 'json'
        });

        $.when(xhr)
            .done($.proxy(configureHostedCheckout, this))
            .fail($.proxy(errorCallback, this));

        return false;
    }
    function completeCallback(resultIndicator, sessionVersion) {
        window.location.href = '{$hostedcheckout_action_url nofilter}' + '?order_id={$mpgs_config.order_id}' + '&result=' + resultIndicator + '&sessionVersion=' + sessionVersion;
    }
    function errorCallback(error) {
        $('#payment-confirmation button').prop('disabled', false);
        console.error(JSON.stringify(error));
    }
    function cancelCallback() {
        window.location.href = '{$hostedcheckout_cancel_url nofilter}';
    }
</script>
