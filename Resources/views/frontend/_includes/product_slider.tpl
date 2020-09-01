{extends file='parent:frontend/_includes/product_slider.tpl'}

{block name="frontend_common_product_slider_container"}


    {$CartFreeShippingFillingArticleMessageSliderTitel
        = "{s name='CartFreeShippingFillingArticleMessageSliderTitel'}Füllartikel{/s}"}

    {if !empty($fillingArticles)}
        <div class="product-slider">
            <div
                    class="
                            panel--title
                            is--underline
                            product-slider--title
                    "
                    data-cart-free-shipping-filling-article-message-slider-titel=
                        "{$CartFreeShippingFillingArticleMessageSliderTitel}"
            >
                {$CartFreeShippingFillingArticleMessageSliderTitel}
            </div>

            <div class="product-slider--container">

                {include file="frontend/_includes/product_slider_items.tpl" fillingArticles=$fillingArticles}

            </div>
        </div>
    {/if}
{/block}
