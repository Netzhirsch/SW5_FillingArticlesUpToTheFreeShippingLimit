{extends file='parent:frontend/checkout/items/product.tpl'}

{block name='frontend_checkout_cart_item_quantity_selection'}

    {if $sBasketItem.MissingAmountToShippingCostFreeBoarder > 0}
        {$sBasketItem.MissingAmountToShippingCostFreeBoarder}
        {s name='CartInfoMissingAmountToShippingCostFreeBoarder'}mehr bis {$CartInfoFreeShipping}{/s}
    {/if}

    {$smarty.block.parent}

{/block}