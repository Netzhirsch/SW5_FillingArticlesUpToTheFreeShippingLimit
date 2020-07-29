$(document).ready(function () {
    $('a[href="#fillingArticles"]').on("click", function (event) {
        event.preventDefault();
        let ziel = $(this).attr("href");

        $('html,body').animate({
            scrollTop: $(ziel).offset().top
        }, 2000, function () {
            location.hash = ziel;
        });
    });
    return false;
});