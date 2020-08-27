{extends file='parent:frontend/checkout/table_footer.tpl'}
{block name="frontend_checkout_footer_benefits"}

    {if $displayVariants == 'popup' || $displayVariants == 'scroll out'}
        {$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden
        = 'netzhirsch_filling_articles_up_to_the_free_shipping_limit--hidden' }
    {/if}

    <div id="fillingArticles" class="panel--body is--rounded {$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden}">
        {include file="frontend/_includes/product_slider.tpl"}
    </div>

    {$smarty.block.parent}

{/block}