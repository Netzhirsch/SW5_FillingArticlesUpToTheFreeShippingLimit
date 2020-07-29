{extends file='parent:frontend/_includes/product_slider_items.tpl'}
{block name="frontend_common_product_slider_items"}
    {foreach $fillingArticles as $fillingArticle}
        {include file="frontend/_includes/product_slider_item.tpl"}
    {/foreach}
{/block}