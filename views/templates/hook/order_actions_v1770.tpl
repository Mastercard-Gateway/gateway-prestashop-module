<div class="card mt-2 d-print-none">
    <div class="card-header">
        <i class="material-icons">settings</i>
        {l s='MasterCard Payment Actions (Online)' mod='mastercard'}
    </div>
    <div class="card-body">
        <div>
            <h4>Gateway Order ID: {$mpgs_order_ref}</h4>
            {if $can_review}
                <p>{l s='This order has been marked to require payment review. Please review the order at the Payment Gateway Administration.' mod='mastercard'}</p>
            {/if}
        </div>
        {if $can_action}
            <div class="well hidden-print">
                {if $can_capture}
                    <a id="desc-order-capture_payment" class="btn btn-primary" href="{$link->getAdminLink('AdminMpgs')|escape:'html':'UTF-8'}&amp;action=capture&amp;id_order={$order->id|intval}">
                        <i class="material-icons">sync_alt</i>
                        {l s='Capture Payment' mod='mastercard'}
                    </a>
                {/if}

                {if $can_void}
                    <a id="desc-order-void_payment" class="btn btn-primary" href="{$link->getAdminLink('AdminMpgs')|escape:'html':'UTF-8'}&amp;action=void&amp;id_order={$order->id|intval}">
                        <i class="material-icons">close</i>
                        {l s='Void Authorization' mod='mastercard'}
                    </a>
                {/if}

                {if $can_refund}
                    <a id="desc-order-refund_payment" class="btn btn-primary" href="{$link->getAdminLink('AdminMpgs')|escape:'html':'UTF-8'}&amp;action=refund&amp;id_order={$order->id|intval}">
                        <i class="material-icons">sync_alt</i>
                        {l s='Full Refund' mod='mastercard'}
                    </a>
                {/if}
            </div>
        {/if}
    </div>
</div>

<script>
    $(function () {
        var container = $('.refund-checkboxes-container');
        var html = $.parseHTML(
            '<div class="cancel-product-element form-group restock-products" style="display: block;">' +
                '<div class="checkbox">' +
                    '<div class="md-checkbox md-checkbox-inline">' +
                        '<label><input type="checkbox"' +
                            ' name="withdrawToCustomer"' +
                            {if !$can_partial_refund}
                            ' disabled="disabled"' +
                            {/if}
                            ' material_design="material_design" value="1">' +
                        '<i class="md-checkbox-control"></i>{l s='Withdraw the funds back to customer (Credit slip is required)' mod='mastercard'}</label>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        container.prepend(html);
    })
</script>

{if $has_refunds}
<div class="card mt-2" id="view_order_payments_block">
    <div class="card-header">
        <h3 class="card-header-title">
            {l s='MasterCard Payment Refunds (Online)' mod='mastercard'}
        </h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
            <tr>
                <th class="table-head-date">Id</th>
                <th class="table-head-payment">Credit Slip Number</th>
                <th class="table-head-amount">Amount</th>
                <th class="table-head-invoice">Transaction ID</th>
            </tr>
            </thead>
            <tbody>
            {foreach $refunds AS $refund}
            <tr>
                <td>{$refund->refund_id}</td>
                <td>
                    {if $refund->order_slip_id}
                        <a class="_blank" title="{l s='See the document'}" href="{$link->getAdminLink('AdminPdf', true, [], ['submitAction' => 'generateOrderSlipPDF', 'id_order_slip' => $refund->order_slip_id])|escape:'html':'UTF-8'}">
                            {Configuration::get('PS_CREDIT_SLIP_PREFIX')}{'%06d'|sprintf:$refund->order_slip_id}
                        </a>
                    {else}
                        {l s='Full refund'}
                    {/if}
                </td>
                <td>{displayPrice price=$refund->total currency=$order->id_currency}</td>
                <td>{$refund->transaction_id}</td>
            </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</div>
{/if}

{if $has_voids}
<div class="card mt-2" id="view_order_payments_block">
  <div class="card-header">
    <h3 class="card-header-title">
        {l s='MasterCard Payment Void (Online)' mod='mastercard'}
    </h3>
  </div>
  <div class="card-body">
    <table class="table">
      <thead>
      <tr>
        <th class="table-head-date">Id</th>
        <th class="table-head-amount">Amount</th>
        <th class="table-head-invoice">Transaction ID</th>
      </tr>
      </thead>
      <tbody>
      {foreach $voids AS $void}
        <tr>
          <td>{$void->void_id}</td>
          <td>{displayPrice price=$void->total currency=$void->id_currency}</td>
          <td>{$void->transaction_id}</td>
        </tr>
      {/foreach}
      </tbody>
    </table>
  </div>
</div>
{/if}
