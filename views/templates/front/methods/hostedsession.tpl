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
            <input class="form-control" aria-label="{l s='Expiry Month:' mod='mastercard'}" type="text" maxlength="2" id="expiry-month" value="" />
        </div>
        <div class="col-md-3">
            <input class="form-control" aria-label="{l s='Expiry Year:' mod='mastercard'}" type="text" maxlength="2" id="expiry-year" value="" />
        </div>
    </div>
    <div class="form-group row">
        <label class="col-md-3 form-control-label" for="security-code">{l s='Security Code:' mod='mastercard'}</label>
        <div class="col-md-3">
            <input class="form-control" aria-label="{l s='Security Code:' mod='mastercard'}" type="text" maxlength="4" id="security-code" value="" readonly="readonly" />
        </div>
    </div>
</section>

<div id="hostedsession_errors" style="color: red; display: none;" class="errors"></div>

<script src="{$hostedsession_component_url}"></script>
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
            placeOrder(response);
        } else {
            errorsContainer.innerText = hsLoadingFailedMsg + ' (unexpected status: '+response.status+')';
            errorsContainer.style.display = 'block';
            document.querySelector('#payment-confirmation button').disabled = false;
        }
    }

    function placeOrder(data) {
        document.querySelector('form.mpgs_hostedsession > input[name=session_id]').value = data.session.id;
        document.querySelector('form.mpgs_hostedsession > input[name=session_version]').value = data.session.version;
        document.querySelector('form.mpgs_hostedsession').submit();
    }

    function is3DsEnabled() {
        {if $hostedsession_3ds}
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
