{extends file='parent:frontend/checkout/ajax_cart.tpl'}

{block name='frontend_checkout_ajax_cart'}

    {if $sShippingcostsDifference > 0 && $viewInAjaxBasket == 1 && !empty($fillingArticles)}

        {include file="frontend/_includes/messages.tpl" type="warning" content="{$message|cat}"}

        {if !empty($fillingArticles)}

            {if $displayVariants == 'popup' || $displayVariants == 'scroll out'}
                {$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden
                = 'is--hidden' }
            {/if}

            {$CartFreeShippingFillingArticleMessageSliderTitel
            = "{s name='CartFreeShippingFillingArticleMessageSliderTitel'}Füllartikel{/s}"}

            <div id="fillingArticles" class="premium-product panel {$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden}">
                <div class="panel--title is--underline"
                     data-cart-free-shipping-filling-article-message-slider-titel=
                     "{$CartFreeShippingFillingArticleMessageSliderTitel}"
                >
                    {$CartFreeShippingFillingArticleMessageSliderTitel}
                </div>

                {include file="frontend/_includes/product_slider.tpl" articles=$fillingArticles}
            </div>

        {/if}
    {/if}

    {$smarty.block.parent}

{/block}
