
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        {l s='MasterCard Payment Actions (Online)' mod='mastercard'}
    </div>
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
    </div>
</div>
