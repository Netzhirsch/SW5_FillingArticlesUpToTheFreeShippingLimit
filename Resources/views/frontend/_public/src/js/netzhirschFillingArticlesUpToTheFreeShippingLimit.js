$(document).ready(function () {
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