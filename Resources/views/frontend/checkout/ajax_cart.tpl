{extends file='parent:frontend/checkout/ajax_cart.tpl'}
{block name='frontend_checkout_ajax_cart'}
    {if $sShippingcostsDifference > 0 && $viewInAjaxBasket == 1}

        {include file="frontend/_includes/messages.tpl" type="warning" content="{$message|cat}"}

        {include file="frontend/_includes/product_slider.tpl"}
    {/if}
    {$smarty.block.parent}
{/block}
