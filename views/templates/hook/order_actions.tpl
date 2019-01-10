
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        {l s='MasterCard Payment Actions (Online)' mod='mastercard'}
    </div>
    <div>
        <h4>Order Reference Number: {$mpgs_order_ref}</h4>
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
