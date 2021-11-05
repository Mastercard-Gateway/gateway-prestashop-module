<html>
<head>
    {block name='head'}
        {include file='_partials/head.tpl'}
    {/block}
</head>

<body>
{hook h='displayAfterBodyOpeningTag'}
<main>

    <p><h2>{l s='Transaction in progress, please wait.' mod='mastercard'}</h2></p>

    <script src="{$hostedcheckout_component_url nofilter}"
            data-error="errorCallback"
            data-cancel="cancelCallback"
            data-beforeRedirect="beforeRedirect"
            data-afterRedirect="afterRedirect"
            data-complete="completeCallback">
    </script>
    <script>
        var merchantId = "{$mpgs_config.merchant_id|escape:javascript}";
        var sessionId = "{$mpgs_config.session_id|escape:javascript}";
        var sessionVersion = "{$mpgs_config.session_version|escape:javascript}";
        var successIndicator = "{$mpgs_config.success_indicator|escape:javascript}";
        var orderId = "{$mpgs_config.order_id|escape:javascript}";
        var resultIndicator = null;
        var baseUrl = "{$urls.current_url nofilter}";

        // This method preserves the current state of successIndicator and orderId,
        // so they're not overwritten when we return to this page after redirect
        function beforeRedirect() {
            return {
                successIndicator: successIndicator,
                orderId: orderId,
                sessionId: sessionId,
                sessionVersion: sessionVersion,
                merchantId: merchantId
            };
        }

        // This method is specifically for the full payment page option. Because we leave this page and return to it, we need to preserve the
        // state of successIndicator and orderId using the beforeRedirect/afterRedirect option
        function afterRedirect(data) {
            // Compare with the resultIndicator saved in the completeCallback() method
            if (resultIndicator) {
                var result = (resultIndicator === data.successIndicator) ? "SUCCESS" : "ERROR";

                // alert('afterRedirect ' + baseUrl + '&order_id=' + data.orderId + '&result=' + result);
                window.location.href = baseUrl + '&order_id=' + data.orderId + '&result=' + result;

            } else {
                successIndicator = data.successIndicator;
                orderId = data.orderId;
                sessionId = data.sessionId;
                sessionVersion = data.sessionVersion;
                merchantId = data.merchantId;

                // alert('afterRedirect ' + baseUrl + '&order_id=' + data.orderId + '&result=' + data.successIndicator + '&session_id=' + data.sessionId);
                window.location.href = baseUrl + '&order_id=' + data.orderId + '&result=' + data.successIndicator + '&session_id=' + data.sessionId;
            }
        }

        function errorCallback(error) {
            console.log(JSON.stringify(error));
        }

        function cancelCallback() {
            console.log('Payment cancelled');
            // Reload the page to generate a new session ID - the old one is out of date as soon as the lightbox is invoked
            window.location.reload(true);
        }

        // This handles the response from Hosted Checkout and redirects to the appropriate endpoint
        function completeCallback(_resultIndicator) {
            // Save the resultIndicator
            resultIndicator = _resultIndicator;
            var result = (resultIndicator === successIndicator) ? "SUCCESS" : "ERROR";
            window.location.href = baseUrl + '&order_id=' + orderId + '&result=' + result;
        }

        Checkout.configure({
            merchant: merchantId,
            order: {
                amount: function () {
                    return {$mpgs_config.amount|string_format:"%.2f"};
                },
                currency: '{$mpgs_config.currency|escape:javascript}',
                description: 'Customer Order',
                id: orderId
            },
            session: {
                id: sessionId,
                version: sessionVersion
            },
            interaction: {
                locale: '{$language.locale}',
                displayControl: {
                    billingAddress: 'HIDE',
                    shipping: 'HIDE'
                },
                merchant: {
                    name: '{$shop.name}',
                    address: {
                        line1: '{$shop.address.address1|escape:javascript}',
                        line2: '{$shop.address.address2|escape:javascript}',
                        line3: '{$shop.address.city|escape:javascript} {$shop.address.postcode|escape:javascript}',
                        line4: '{$shop.address.country|escape:javascript}'
                    }
                }
            }
        });
        Checkout.showPaymentPage();
    </script>
    <!-- Footer Ends -->
    {block name='javascript_bottom'}
        {include file="_partials/javascript.tpl" javascript=$javascript.bottom}
    {/block}
    {hook h='displayBeforeBodyClosingTag'}
</main>
</body>
</html>
