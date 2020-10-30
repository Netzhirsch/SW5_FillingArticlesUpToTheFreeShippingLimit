$(document).ready(function () {

    // filling artikel slider on ajax cart
    $.subscribe('plugin/swCollapseCart/onLoadCartFinished', function() {
        addArticleToAjaxCart();
        fillingArticleSlider();
    });

    // remove empty slider container
    $.subscribe('plugin/swResponsive/onCartRefreshSuccess', function () {
        if ($('.container--ajax-cart .product-slider--item').length === 0) {
            $('.container--ajax-cart .product-slider--container').remove();
        }
    });

    // check if slider needs to add after removing an article
    $.subscribe('plugin/swCollapseCart/onRemoveArticleFinished', function() {
        addArticleToAjaxCart();
        fillingArticleSlider();
    });

    // check if slider needs to add after adding an article (voucher is an article)
    $.subscribe('plugin/swCollapseCart/onArticleAdded', function() {
        addArticleToAjaxCart();
        fillingArticleSlider();
    });

    //filling article slider
    $('a[href="#fillingArticles"]').on("click", function (event) {
        event.preventDefault();
        let fillingArticlesContainer = $('#fillingArticles');
        // display variante popup
        if (fillingArticlesContainer.hasClass('is--hidden')) {
            let content = fillingArticlesContainer.html();
            $.modal.open(content, {
                overlay: true,
                width: 226,
                height: 420
            });

            window.StateManager.addPlugin(
                '.js--modal .product-slider',
                'swProductSlider',{
                }
            );

        } else {

            // smoothe scroll
            let ziel = $(this).attr("href");

            $('html,body').animate({
                scrollTop: $(ziel).offset().top
            }, 2000, function () {
                location.hash = ziel;
            });
        }
    });
    return false;
});

function fillingArticleSlider() {
    if ($('#fillingArticles').length > 0) {
        $('.container--ajax-cart').swProductSlider({
            itemMinWidth: 300
        });
        // Initialisierung über den StateManager
        window.StateManager.addPlugin(
            '.container--ajax-cart',
            'swProductSlider',
            { itemMinWidth: 300 }
        );
    }
}

function addArticleToAjaxCart() {
    if ($('#fillingArticles').length > 0) {
        $('.buybox--form').swAddArticle({});
        // Initialisierung über den StateManager
        window.StateManager.addPlugin(
            '.buybox--form',
            'swAddArticle',
        );
    }
}
