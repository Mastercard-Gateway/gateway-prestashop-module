<form id="payment-form" class="mpgs_hostedsession" method="POST" onsubmit="return payWithHostedSession(this)" action="{$hostedsession_action_url}">
    <input type="hidden" name="session_id" value="" />
    <input type="hidden" name="session_version" value="" />
    <input type="hidden" name="check_3ds_enrollment" value="" />
    <input type="hidden" name="transaction_id" value="" />
</form>
<script>
    function payWithHostedSession(form) {
        var sessionEl = form.querySelector('[name=session_id]');
        if (sessionEl && sessionEl.value !== '') {
            return true;
        } else {
            PaymentSession.updateSessionFromForm('card');
            return false;
        }
    }
</script>
