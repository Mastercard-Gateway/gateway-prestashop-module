{*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="panel">
	<div class="row mastercard-header">
		<img src="{$module_dir|escape:'html':'UTF-8'}views/img/template_1_logo.png" class="col-xs-6 col-md-4 text-center" id="payment-logo" />
		<div class="col-xs-6 col-md-4 text-center">
			<h4>{l s='Online payment processing' mod='mastercard'}</h4>
			<h4>{l s='Fast - Secure - Reliable' mod='mastercard'}</h4>
		</div>
		<div class="col-xs-12 col-md-4 text-center">
			<a href="#" onclick="javascript:return false;" class="btn btn-primary" id="create-account-btn">{l s='Create an account now!' mod='mastercard'}</a><br />
			{l s='Already have an account?' mod='mastercard'}<a href="#" onclick="javascript:return false;"> {l s='Log in' mod='mastercard'}</a>
		</div>
	</div>

	<hr />
	
	<div class="mastercard-content">
		<div class="row">
			<div class="col-md-6">
				<h5>{l s='My payment module offers the following benefits' mod='mastercard'}</h5>
				<dl>
					<dt>&middot; {l s='Increase customer payment options' mod='mastercard'}</dt>
					<dd>{l s='Visa®, Mastercard®, Diners Club®, American Express®, Discover®, Network and CJB®, plus debit, gift cards and more.' mod='mastercard'}</dd>
					
					<dt>&middot; {l s='Help to improve cash flow' mod='mastercard'}</dt>
					<dd>{l s='Receive funds quickly from the bank of your choice.' mod='mastercard'}</dd>
					
					<dt>&middot; {l s='Enhanced security' mod='mastercard'}</dt>
					<dd>{l s='Multiple firewalls, encryption protocols and fraud protection.' mod='mastercard'}</dd>
					
					<dt>&middot; {l s='One-source solution' mod='mastercard'}</dt>
					<dd>{l s='Conveniance of one invoice, one set of reports and one 24/7 customer service contact.' mod='mastercard'}</dd>
				</dl>
			</div>
			
			<div class="col-md-6">
				<h5>{l s='FREE My Payment Module Glocal Gateway (Value of 400$)' mod='mastercard'}</h5>
				<ul>
					<li>{l s='Simple, secure and reliable solution to process online payments' mod='mastercard'}</li>
					<li>{l s='Virtual terminal' mod='mastercard'}</li>
					<li>{l s='Reccuring billing' mod='mastercard'}</li>
					<li>{l s='24/7/365 customer support' mod='mastercard'}</li>
					<li>{l s='Ability to perform full or patial refunds' mod='mastercard'}</li>
				</ul>
				<br />
				<em class="text-muted small">
					* {l s='New merchant account required and subject to credit card approval.' mod='mastercard'}
					{l s='The free My Payment Module Global Gateway will be accessed through log in information provided via email within 48 hours.' mod='mastercard'}
					{l s='Monthly fees for My Payment Module Global Gateway will apply.' mod='mastercard'}
				</em>
			</div>
		</div>

		<hr />
		
		<div class="row">
			<div class="col-md-12">
				<h4>{l s='Accept payments in the United States using all major credit cards' mod='mastercard'}</h4>
				
				<div class="row">
					<img src="{$module_dir|escape:'html':'UTF-8'}views/img/template_1_cards.png" class="col-md-6" id="payment-logo" />
					<div class="col-md-6">
						<h6 class="text-branded">{l s='For transactions in US Dollars (USD) only' mod='mastercard'}</h6>
						<p class="text-branded">{l s='Call 888-888-1234 if you have any questions or need more information!' mod='mastercard'}</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
