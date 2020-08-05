$(document).ready(function () {

    // filling artikel slider on ajax cart
    $.subscribe('plugin/swCollapseCart/onLoadCartFinished', function() {
        addArticleToAjaxCart();
        fillingArticleSlider();
    });

    $.subscribe('plugin/swCollapseCart/onRemoveArticleFinished', function() {
        addArticleToAjaxCart();
        fillingArticleSlider();
    });

    $.subscribe('plugin/swCollapseCart/onArticleAdded', function() {
        addArticleToAjaxCart();
        fillingArticleSlider();
    });

    //filling article slider

    $('a[href="#fillingArticles"]').on("click", function (event) {
        event.preventDefault();

        let fillingArticlesContainer = $('#fillingArticles');
        // display variante popup
        if (fillingArticlesContainer.hasClass('netzhirsch_filling_articles_up_to_the_free_shipping_limit--hidden')) {
            let content = fillingArticlesContainer.html();
            $.modal.open(content, {
                title: $('[data-cart-free-shipping-filling-article-message-slider-titel]')
                            .data('cart-free-shipping-filling-article-message-slider-titel'),
                overlay: true,
                width: 700
            });
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