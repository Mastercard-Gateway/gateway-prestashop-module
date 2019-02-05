{if !$mpgs_gateway_validated}
	<div class="bootstrap">
		<div class="module_confirmation conf confirm alert alert-warning">
			{l s='Payment methods are not configured correctly or the API crendentials are not valid. To activate the payment methods please your details to the forms below.' mod='mastercard'}
		</div>
	</div>
{/if}
<div class="panel">
	<div class="row mastercard-header">
		<img src="{$module_dir|escape:'html':'UTF-8'}views/img/template_1_logo.png" class="col-xs-6 col-md-4 text-center" id="payment-logo" />
		<div class="col-xs-6 col-md-4 text-center">
		</div>
		<div class="col-xs-12 col-md-4 text-center">
			<h4>{l s='Online payment processing' mod='mastercard'}</h4>
			<h4>{l s='Fast - Secure - Reliable' mod='mastercard'}</h4>
		</div>
	</div>
</div>
