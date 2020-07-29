{extends file='parent:frontend/checkout/table_footer.tpl'}
{block name="frontend_checkout_footer_benefits"}
        {include file="frontend/_includes/product_slider.tpl"}
    {$smarty.block.parent}
{/block}