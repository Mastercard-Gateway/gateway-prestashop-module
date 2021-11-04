<section id="hostedsession_section">
    <div class="form-group row">
        <label class="col-md-3 form-control-label" for="card-number">{l s='Card Number:' mod='mastercard'}</label>
        <div class="col-md-6">
            <input class="form-control" aria-label="{l s='Card Number:' mod='mastercard'}" type="text" maxlength="24"
                   id="card-number" value="" readonly="readonly"/>
        </div>
    </div>
    <div class="form-group row">
        <label class="col-md-3 form-control-label" for="expiry-month">{l s='Expiry:' mod='mastercard'}</label>
        <div class="col-md-3">
            <input class="form-control" aria-label="{l s='Expiry Month:' mod='mastercard'}" type="text" maxlength="2"
                   id="expiry-month" value="" readonly="readonly"/>
        </div>
        <div class="col-md-3">
            <input class="form-control" aria-label="{l s='Expiry Year:' mod='mastercard'}" type="text" maxlength="2"
                   id="expiry-year" value="" readonly="readonly"/>
        </div>
    </div>
    <div class="form-group row">
        <label class="col-md-3 form-control-label" for="security-code">{l s='Security Code:' mod='mastercard'}</label>
        <div class="col-md-3">
            <input class="form-control" aria-label="{l s='Security Code:' mod='mastercard'}" type="text" maxlength="4"
                   id="security-code" value="" readonly="readonly"/>
        </div>
    </div>
</section>

<div id="redirect_html" style="display: none"></div>

<div id="hostedsession_errors" style="color: red; display: none;" class="errors"></div>

<div id="hostedsession_modal" style="display: none" class="hostedsession_modal"></div>

<script async src="{$hostedsession_component_url nofilter}"></script>
{if $hostedsession_3ds}
    <script src="{$hostedsession_3ds_url nofilter}"></script>
{/if}

<script>
    if (self !== top) {
        top.location = self.location;
    }

    var hsLoaded = false,
        hsLoading = false;

    var hsLoadingFailedMsg = "{l s='Failed loading Hosted Session payment method, please try again later.' mod='mastercard'}";
    var hsProcessingFailedMsg = "{l s='Failed processing Hosted Session payment method, please try again later.' mod='mastercard'}";

    function loadPaymentSession() {
        setTimeout(_loadPaymentSession, 200);
    }

    function hsFieldMap() {
        return {
            number: "#card-number",
            cardNumber: "#card-number",
            securityCode: "#security-code",
            expiryMonth: "#expiry-month",
            expiryYear: "#expiry-year"
        };
    }

    function hsErrorsMap() {
        return {
            cardNumber: "{l s='Invalid Card Number' mod='mastercard'}",
            securityCode: "{l s='Invalid Security Code' mod='mastercard'}",
            expiryMonth: "{l s='Invalid Expiry Month' mod='mastercard'}",
            expiryYear: "{l s='Invalid Expiry Year' mod='mastercard'}"
        };
    }

    function hsFormUpdated(response) {
        var fields = hsFieldMap();
        for (var field in fields) {
            var input = document.getElementById(fields[field].substr(1));
            input.style['border-color'] = 'inherit';
        }

        var errorsContainer = document.getElementById('hostedsession_errors');
        errorsContainer.innerText = '';
        errorsContainer.style.display = 'none';

        if (!response.status) {
            errorsContainer.innerText = hsLoadingFailedMsg + ' (invalid response)';
            errorsContainer.style.display = 'block';
            return;
        }

        if (response.status === "fields_in_error") {
            if (response.errors) {
                var errors = hsErrorsMap(),
                    message = "";

                for (var field in response.errors) {
                    if (!response.errors.hasOwnProperty(field)) {
                        continue;
                    }

                    var input = document.getElementById(fields[field].substr(1));
                    input.style['border-color'] = 'red';
                    message += errors[field] + "\n";
                }

                errorsContainer.innerText = message;
                errorsContainer.style.display = 'block';
                document.querySelector('#payment-confirmation button').disabled = false;
            }
        } else if (response.status === "ok") {
            if (is3DsEnabled()) {
                document.querySelector('form.mpgs_hostedsession > input[name=check_3ds_enrollment]').value = '1';
            }

            if (is3Ds2Enabled()) {
                updateSessionWithPaymentData(response)
                    .then(function (response) {
                        return initThreeDS(response);
                    })
                    .then(function (response) {
                        return initiateAuthentication(response);
                    })
                    .then(function (response, data) {
                        return authenticatePayer(response, data);
                    })
                    .then(function (response, data) {
                        return takeThreeDSChallenge(response, data);
                    })
                    .fail(function (error) {
                        var errorMessage;
                        if (error && error.msg) {
                            errorMessage = error.msg;
                        } else {
                            errorMessage = hsProcessingFailedMsg;
                        }
                        errorsContainer.innerText = errorMessage;
                        errorsContainer.style.display = 'block';
                        document.querySelector('#payment-confirmation button').disabled = false;
                    });

                return;
            }
            placeOrder(response);
        } else {
            errorsContainer.innerText = hsLoadingFailedMsg + ' (unexpected status: ' + response.status + ')';
            errorsContainer.style.display = 'block';
            document.querySelector('#payment-confirmation button').disabled = false;
        }
    }

    function updateSessionWithPaymentData(response) {
        return $.ajax({
            url: '{$hostedsession_action_url nofilter}',
            method: 'post',
            data: {
                check_3ds_enrollment: "2",
                action_type: "update_session",
                session_id: response.session.id,
                order_id: '{$mpgs_config.order_id}'
            },
            dataType: 'json'
        })
    }

    function placeOrder(data) {
        document.querySelector('form.mpgs_hostedsession > input[name=session_id]').value = data.session.id;
        document.querySelector('form.mpgs_hostedsession > input[name=session_version]').value = data.session.version;
        document.querySelector('form.mpgs_hostedsession').submit();
    }

    function is3DsEnabled() {
        {if $hostedsession_3ds == 1}
        return true;
        {else}
        return false;
        {/if}
    }

    function is3Ds2Enabled() {
        {if $hostedsession_3ds == 2}
        return true;
        {else}
        return false;
        {/if}
    }

    function hsInitialised(response) {
        if (response.status === 'ok') {
            hsLoaded = true;
        } else {
            throw hsLoadingFailedMsg;
        }
        hsLoading = false;
    }

    function initThreeDS(response) {
        var deferred = $.Deferred();

        ThreeDS.configure({
            merchantId: '{$mpgs_config.merchant_id}',
            sessionId: response.session.id,
            containerId: "hostedsession_modal",
            callback: function () {
                deferred.resolve(response);
            },
            configuration: {
                wsVersion: response.version
            }
        });

        return deferred.promise();
    }

    function initiateAuthentication(response) {
        var deferred = $.Deferred();

        ThreeDS.initiateAuthentication(
            '{$mpgs_config.order_id}',
            response.transaction.id,
            function (data) {
                deferred.resolve(response, data);
            }
        );

        return deferred.promise();
    }

    function authenticatePayer(response, data) {
        var deferred = $.Deferred();

        if (data && data.error) {
            var error = data.error;
            deferred.reject(error);
        } else {
            switch (data.gatewayRecommendation) {
                case "PROCEED":
                    ThreeDS.authenticatePayer(
                        response.order.id,
                        response.transaction.id,
                        function (data) {
                            deferred.resolve(response, data);
                        }
                    );
                    break;
                case "DO_NOT_PROCEED":
                    deferred.reject("Payment was declined, please try again later.");
                    break;
            }
        }

        return deferred.promise();
    }

    function takeThreeDSChallenge(response, data) {
        var deferred = $.Deferred();

        if (data.error) {
            deferred.reject(data.error);
        } else {
            var $modal = $('#hostedsession_modal');

            window.treeDS2Completed = function (transactionId) {
                document.querySelector('form.mpgs_hostedsession > input[name=transaction_id]').value = transactionId;
                placeOrder(response);
                $modal.hide();
                deferred.resolve(response, data);
            }

            window.treeDS2Failure = function (error) {
                $modal.hide();
                deferred.reject(error);
            }

            switch (data.gatewayRecommendation) {
                case "PROCEED":
                    var restApiResponse = data.restApiResponse;
                    var authentication = restApiResponse.authentication;

                    $modal.html(authentication.redirectHtml);
                    eval($('#authenticate-payer-script').text());
                    $modal.show();
                    break;
                case "DO_NOT_PROCEED":
                    deferred.reject("Payment was declined, please try again later.");
                    break;
            }
        }

        return deferred.promise();
    }

    function _loadPaymentSession() {
        if (hsLoaded || hsLoading) {
            return;
        }

        var section = document.getElementById('hostedsession_section');
        if (section.offsetParent === null) {
            loadPaymentSession();
            return;
        }

        if (typeof PaymentSession === "undefined") {
            loadPaymentSession();
            return;
        }

        if (document.getElementById('card-number') === null) {
            loadPaymentSession();
            return;
        }

        hsLoading = true;

        var config = {
            fields: {
                card: hsFieldMap()
            },
            frameEmbeddingMitigation: ["javascript"],
            callbacks: {
                initialized: hsInitialised,
                formSessionUpdate: hsFormUpdated
            },
            interaction: {
                displayControl: {
                    invalidFieldCharacters: 'REJECT',
                    formatCard: 'EMBOSSED'
                }
            }
        };

        PaymentSession.configure(config);
    }

    loadPaymentSession();
</script>

<style>
    .hostedsession_modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        z-index: 100;
    }

    .hostedsession_modal [id=threedsFrictionLessRedirect] {
        height: 100%;
    }

    .hostedsession_modal [id=challengeFrame] {
        width: 100%;
        height: 100%;
    }

    .hostedsession_modal [id=redirectTo3ds1AcsSimple] {
        height: 100%;
    }
</style>
