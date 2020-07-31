{extends file='parent:frontend/checkout/table_footer.tpl'}
{block name="frontend_checkout_footer_benefits"}

    <div class="panel--body is--rounded">
        {include file="frontend/_includes/product_slider.tpl"}
    </div>

    {$smarty.block.parent}

{/block}