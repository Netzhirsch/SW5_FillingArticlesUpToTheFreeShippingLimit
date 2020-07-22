{extends file='parent:frontend/checkout/table_footer.tpl'}
{block name="frontend_checkout_footer_benefits"}
    <div class="product-slider" data-product-slider="true">

        <!-- Product slider direction arrows -->
        <a class="product-slider--arrow arrow--next is--horizontal"></a>
        <a class="product-slider--arrow arrow--prev is--horizontal"></a>

        <div class="product-slider--container is--horizontal">
            {foreach $fillingArticles as $sArticle}
                <div class="product-slider--item">
                    {include file="parent:frontend/listing/product-box/box-product-slider.tpl"}
                </div>
            {/foreach}
        </div>
    </div>
    {$smarty.block.parent}
{/block}