<form id="payment-form" method="POST" onsubmit="return payWithHostedCheckout()" action="{$hostedcheckout_action_url nofilter}"></form>
<script src="{$mpgs_config.checkout_component_url}"
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
                amount: {$mpgs_config.amount|string_format:"%.2f"},
                currency: '{$mpgs_config.currency|escape:javascript}',
                description: 'Customer Order',
                id: '{$mpgs_config.order_id}'
            },
            interaction: {
                {if $mpgs_hc_theme}
                theme: '{$mpgs_hc_theme|escape:javascript}',
                {/if}
                locale: '{$language.locale}',
                displayControl: {
                    billingAddress: '{$mpgs_hc_show_billing|escape:javascript}',
                    customerEmail: '{$mpgs_hc_show_email|escape:javascript}',
                    shipping: 'HIDE',
                    orderSummary: 'HIDE'
                },
                merchant: {
                    name: '{$shop.name|escape:javascript}',
                    address: {
                        line1: '{$shop.address.address1|escape:javascript}',
                        line2: '{$shop.address.address2|escape:javascript}',
                        line3: '{$shop.address.city|escape:javascript} {$shop.address.postcode|escape:javascript}',
                        line4: '{$shop.address.country|escape:javascript}'
                    }
                }
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
