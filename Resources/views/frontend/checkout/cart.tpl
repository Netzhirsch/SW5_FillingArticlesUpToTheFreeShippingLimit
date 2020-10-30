{extends file='parent:frontend/checkout/cart.tpl'}

{block name='frontend_checkout_cart_deliveryfree'}
    {if $sShippingcostsDifference }

        {if $noteAboveBasket && !empty($fillingArticles)}

            {$shippingDifferenceContent
                ="<strong>{s name='CartInfoFreeShipping'}{/s}</strong> {s name='CartInfoFreeShippingDifference'}{/s}{s name='CartInfoFreeShippingFillingArticleMessage'}<br><a href=\"#fillingArticles\" title=\"Passende Artikel\">Hier </a> finden Sie passende Artikel dazu.{/s}"
            }

        {else}

            {$shippingDifferenceContent
                ="<strong>{s name='CartInfoFreeShipping'}{/s}</strong> {s name='CartInfoFreeShippingDifference'}{/s}"
            }

        {/if}

        {include file="frontend/_includes/messages.tpl" type="warning" content="{$shippingDifferenceContent|cat}"}

    {/if}

{/block}

{block name='frontend_checkout_cart_premium'}

        {block name='frontend_checkout_cart_promotion'}

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

        {/block}

        {$smarty.block.parent}
{/block}
