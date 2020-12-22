<section id="hostedsession_section">
    <div class="form-group row">
        <label class="col-md-3 form-control-label" for="card-number">{l s='Card Number:' mod='mastercard'}</label>
        <div class="col-md-6">
            <input class="form-control" aria-label="{l s='Card Number:' mod='mastercard'}" type="text" maxlength="24" id="card-number" value="" readonly="readonly" />
        </div>
    </div>
    <div class="form-group row">
        <label class="col-md-3 form-control-label" for="expiry-month">{l s='Expiry:' mod='mastercard'}</label>
        <div class="col-md-3">
            <input class="form-control" aria-label="{l s='Expiry Month:' mod='mastercard'}" type="text" maxlength="2" id="expiry-month" value="" readonly="readonly" />
        </div>
        <div class="col-md-3">
            <input class="form-control" aria-label="{l s='Expiry Year:' mod='mastercard'}" type="text" maxlength="2" id="expiry-year" value="" readonly="readonly" />
        </div>
    </div>
    <div class="form-group row">
        <label class="col-md-3 form-control-label" for="security-code">{l s='Security Code:' mod='mastercard'}</label>
        <div class="col-md-3">
            <input class="form-control" aria-label="{l s='Security Code:' mod='mastercard'}" type="text" maxlength="4" id="security-code" value="" readonly="readonly" />
        </div>
    </div>
</section>

<div id="redirect_html" style="display: none"></div>

<div id="hostedsession_errors" style="color: red; display: none;" class="errors"></div>

<div id="hostedsession_modal" style="display: none" class="hostedsession_modal"></div>

<script async src="{$hostedsession_component_url}"></script>
<script>
    if (self !== top) {
        top.location = self.location;
    }

    var hsLoaded = false,
        hsLoading = false;

    var hsLoadingFailedMsg = "{l s='Failed loading Hosted Session payment method, please try again later.' mod='mastercard'}";

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
        }  else if (response.status === "ok") {
            if (is3DsEnabled()) {
                document.querySelector('form.mpgs_hostedsession > input[name=check_3ds_enrollment]').value = '1';
            }
            if (is3Ds2Enabled()) {
                document.querySelector('form.mpgs_hostedsession > input[name=check_3ds_enrollment]').value = '2';

                initAuth(response);
                return;
            }
            placeOrder(response);
        } else {
            errorsContainer.innerText = hsLoadingFailedMsg + ' (unexpected status: '+response.status+')';
            errorsContainer.style.display = 'block';
            document.querySelector('#payment-confirmation button').disabled = false;
        }
    }

    function initAuth(response) {
        return $.post("{$hostedsession_action_url}", {
            check_3ds_enrollment: "2",
            action_type: "init",
            session_id: response.session.id,
            session_version: response.session.version
        }, function (authInitResString) {
            var authInitRes = JSON.parse(authInitResString);
            if (!authInitRes.success) {
                $('#hostedsession_errors').text(authInitRes.error).show();
                document.querySelector('#payment-confirmation button').disabled = false;
                return;
            }

            $('#redirect_html').html(authInitRes.redirectHtml);
            eval($('#initiate-authentication-script').text());

            authPayer(response, authInitRes);
        });
    }

    function authPayer(response, authInitRes) {
        return $.post("{$hostedsession_action_url}", {
            check_3ds_enrollment: "2",
            action_type: "authenticate",
            session_id: response.session.id,
            session_version: response.session.version,
            transaction_id: authInitRes.transaction_id,
            browserDetails: {
                javaEnabled: navigator.javaEnabled(),
                language: navigator.language,
                screenHeight: window.screen.height,
                screenWidth: window.screen.width,
                timeZone: new Date().getTimezoneOffset(),
                colorDepth: screen.colorDepth,
                acceptHeaders: 'application/json',
                '3DSecureChallengeWindowSize': 'FULL_SCREEN'
            }
        }, function (authPayerResString) {
            var $modal = $('#hostedsession_modal');
            window.treeDS2Completed = function (transactionId) {
                document.querySelector('form.mpgs_hostedsession > input[name=transaction_id]').value = transactionId;
                placeOrder(response);
                $modal.hide();
            }

            window.treeDS2Failure = function (error) {
                $('#hostedsession_errors').text(error).show();
                document.querySelector('#payment-confirmation button').disabled = false;
                $modal.hide();
            }
            var authPayerRes = JSON.parse(authPayerResString);
            if (!authPayerRes.success) {
                $('#hostedsession_errors').text(authPayerRes.error).show();
                document.querySelector('#payment-confirmation button').disabled = false;
                return;
            }
            $modal.html(authPayerRes.redirectHtml);
            eval($('#authenticate-payer-script').text());
            if (authPayerRes.action === 'challenge') {
                $modal.show();
            }
        });
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

        PaymentSession.configure({
            fields: {
                card: hsFieldMap()
            },
            frameEmbeddingMitigation: ['javascript'],
            callbacks: {
                initialized: hsInitialised,
                formSessionUpdate: hsFormUpdated
            }
        });
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
</style>