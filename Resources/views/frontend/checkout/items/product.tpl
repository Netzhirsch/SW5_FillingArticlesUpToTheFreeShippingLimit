{extends file='parent:frontend/checkout/items/product.tpl'}

{block name='frontend_checkout_cart_item_quantity_selection'}

    {$smarty.block.parent}

    {if $sBasketItem.missingAmountToShippingCostFreeBoarder > 0}
        {$sBasketItem.missingAmountToShippingCostFreeBoarder}
        {s name='CartInfoMissingAmountToShippingCostFreeBoarder'}weitere bis VSK-frei{/s}
    {/if}

{/block}