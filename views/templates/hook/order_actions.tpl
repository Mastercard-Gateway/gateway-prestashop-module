
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        {l s='MasterCard Payment Actions (Online)' mod='mastercard'}
    </div>
    <div>
        <h4>Gateway Order ID: {$mpgs_order_ref}</h4>
        {if $can_review}
            <p>{l s='This order has been marked to require payment review. Please review the order at the Payment Gateway Administration.' mod='mastercard'}</p>
        {/if}
    </div>
    {if $can_action}
    <div class="well hidden-print">
        {if $can_capture}
            <a id="desc-order-capture_payment" class="btn btn-default" href="{$link->getAdminLink('AdminMpgs')|escape:'html':'UTF-8'}&amp;action=capture&amp;id_order={$order->id|intval}">
                <i class="icon-exchange"></i>
                {l s='Capture Payment' mod='mastercard'}
            </a>
        {/if}

        {if $can_void}
            <a id="desc-order-void_payment" class="btn btn-default" href="{$link->getAdminLink('AdminMpgs')|escape:'html':'UTF-8'}&amp;action=void&amp;id_order={$order->id|intval}">
                <i class="icon-remove"></i>
                {l s='Void Authorization' mod='mastercard'}
            </a>
        {/if}

        {if $can_refund}
            <a id="desc-order-refund_payment" class="btn btn-default" href="{$link->getAdminLink('AdminMpgs')|escape:'html':'UTF-8'}&amp;action=refund&amp;id_order={$order->id|intval}">
                <i class="icon-exchange"></i>
                {l s='Full Refund' mod='mastercard'}
            </a>
        {/if}

        {*{if $can_review}*}
            {*<a id="desc-order-accept_payment" class="btn btn-default" href="{$link->getAdminLink('AdminMpgs')|escape:'html':'UTF-8'}&amp;action=accept&amp;id_order={$order->id|intval}">*}
                {*<i class="icon-exchange"></i>*}
                {*{l s='Accept Review' mod='mastercard'}*}
            {*</a>*}
            {*<a id="desc-order-reject_payment" class="btn btn-default" href="{$link->getAdminLink('AdminMpgs')|escape:'html':'UTF-8'}&amp;action=reject&amp;id_order={$order->id|intval}">*}
                {*<i class="icon-exchange"></i>*}
                {*{l s='Reject Review' mod='mastercard'}*}
            {*</a>*}
        {*{/if}*}
    </div>
    {/if}
</div>

<script>
    $(function() {
        var partialRefund = $('.partial_refund_fields [type=submit]').parent();
        var html = $.parseHTML('<p class="checkbox">'
        + '<label for="withdrawToCustomer">'
        + '<input type="checkbox" id="withdrawToCustomer" name="withdrawToCustomer">'
            + 'Withdraw the funds back to customer'
        + '</label>'
        + '</p>')
        partialRefund.prepend(html);
    })
</script>

<div class="panel">

    <div class="panel-heading">
        <i class="icon-cogs"></i>
        {l s='MasterCard Payment Refunds (Online)' mod='mastercard'}
    </div>


    <div class="table-responsive">
        <table class="table" id="mpgs_refunds_table">
            <thead>
            <tr>
                <th>
                    <span class="title_box ">Id</span>
                </th>
                <th>
                    <span class="title_box ">Credit Slip Number</span>
                </th>
                <th>
                    <span class="title_box ">Amount</span>
                </th>
            </tr>
            </thead>
            <tbody>
            {foreach $refunds AS $refund}
            <tr>
                <td>
                    {$refund->refund_id}
                </td>
                <td>
                    {if $refund->order_slip_id}
                    <a class="_blank" title="{l s='See the document'}" href="{$link->getAdminLink('AdminPdf', true, [], ['submitAction' => 'generateOrderSlipPDF', 'id_order_slip' => $refund->order_slip_id])|escape:'html':'UTF-8'}">
                        {Configuration::get('PS_CREDIT_SLIP_PREFIX')}{'%06d'|sprintf:$refund->order_slip_id}
                    </a>
                    {else}
                        {l s='Full refund'}
                    {/if}
                </td>
                <td>
                    {displayPrice price=$refund->total currency=$order->id_currency}
                </td>
            </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</div>
