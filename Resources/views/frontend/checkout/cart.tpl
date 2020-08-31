{extends file='parent:frontend/checkout/cart.tpl'}

{block name='frontend_checkout_cart_deliveryfree'}
    {if $sShippingcostsDifference}

        {if $noteAboveBasket}

            {$shippingDifferenceContent
                ="<strong>{s name='CartInfoFreeShipping'}{/s}</strong> {s name='CartInfoFreeShippingDifference'}{/s}{s name='CartInfoFreeShippingFillingArticleMessage'}<br><a href=\"#fillingArticles\" title=\"Passende Artikel\">Hier </a> finden Sie passende Artikel dazu{/s}"
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
            {if $displayVariants == 'popup' || $displayVariants == 'scroll out'}
                {$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden
                = 'netzhirsch_filling_articles_up_to_the_free_shipping_limit--hidden' }
            {/if}
            <div id="fillingArticles" class="{$netzhirschFillingArticlesUpToTheFreeShippingLimitHidden}">
                <div class="panel--body is--rounded product--table premium-product panel">
                    {include file="frontend/_includes/product_slider.tpl"}
                </div>
            </div>
        {/block}
        {$smarty.block.parent}
{/block}
