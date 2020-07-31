{extends file='parent:frontend/checkout/items/product.tpl'}
{block name='frontend_checkout_cart_item_quantity_selection'}
    {$sBasketItem.MissingAmountToShippingCostFreeBoarder}
    {s name='CartInfoMissingAmountToShippingCostFreeBoarder'}bis {/s}
    {$CartInfoFreeShipping}
    {$smarty.block.parent}
{/block}