{extends file='parent:frontend/_includes/product_slider.tpl'}

{block name="frontend_common_product_slider_container"}

    {if $displayVariants == 'popup' || $displayVariants == 'scroll out'}
        {$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden
            = 'netzhirsch_filling_articles_up_to_the_free_shipping_limit--hidden' }
    {/if}

    {$CartFreeShippingFillingArticleMessageSliderTitel
        = "{s name='CartFreeShippingFillingArticleMessageSliderTitel'}Füllartikel{/s}"}

    <div
            class="
                    panel--title
                    is--underline
                    product-slider--title
                    {$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden}
                   "

            data-cart-free-shipping-filling-article-message-slider-titel=
                "{$CartFreeShippingFillingArticleMessageSliderTitel}"
    >
        {$CartFreeShippingFillingArticleMessageSliderTitel}
    </div>
    <div
            id="fillingArticles"
            class="product-slider--container {$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden}"
    >

        {include file="frontend/_includes/product_slider_items.tpl" fillingArticles=$fillingArticles}

    </div>
{/block}