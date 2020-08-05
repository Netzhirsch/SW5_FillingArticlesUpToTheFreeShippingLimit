{extends file='parent:frontend/_includes/product_slider_item.tpl'}

{block name="frontend_common_product_slider_item"}

    <div class="product-slider--item" style="width: 100%;">

        {include
            file="frontend/listing/box_article.tpl"
            sArticle=$fillingArticle
            productBoxLayout=$productBoxLayout
            fixedImageSize=$fixedImageSize
        }

        {include file="frontend/detail/buy.tpl" sArticle=$fillingArticle}
    </div>

{/block}