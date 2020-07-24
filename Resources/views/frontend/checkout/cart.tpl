{extends file='parent:frontend/checkout/cart.tpl'}

{block name='frontend_checkout_cart_deliveryfree'}
    {if $sShippingcostsDifference}
        {$shippingDifferenceContent="<strong>{s name='CartInfoFreeShipping'}{/s}</strong> {s name='CartInfoFreeShippingDifference'}{/s}{s name='CartInfoFreeShippingFillingArticleMessage'}<br><a href=\"#fillingArticles\" title=\"Passende Artikel\">Hier </a> finden Sie passende Artikel dazu{/s}"}

        {include file="frontend/_includes/messages.tpl" type="warning" content="{$shippingDifferenceContent|cat}"}
    {/if}
{/block}
