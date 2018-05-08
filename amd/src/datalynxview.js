define(['jquery'], function($) {

    return {
        init: function() {

            var datalynxviews = $('.datalynxfield-datalynxview.overlay');
            datalynxviews.each(function () {
                /* eslint-disable no-console */
                console.log($(this).find('.panelContent'));
                /* eslint-enable no-console */
                //$(this).find('.panelContent').removeClass('hide');

                var content = $(this).find('.panelContent');

                $(this).find('button').on("click", function(){
                    content.toggleClass('hide');
                    displayOverlay(content);
                });
            });

            // I will keep this for future extension.
            // It should be able to display an overlay instead of toggling in html.
            function displayOverlay(content) {
                $("<table id='overlay'><tbody><tr><td>" + content.prop('outerHTML') + "</td></tr></tbody></table>").css({
                    "position": "fixed",
                    "top": 0,
                    "left": 0,
                    "width": "100%",
                    "height": "100%",
                    "background-color": "rgba(0,0,0,.5)",
                    "z-index": 10000,
                    "vertical-align": "middle",
                    "text-align": "center",
                    "font-size": "100%",
                    "cursor": "wait"
                }).appendTo("body");

                // Click whereever closes overlay.
                $("#overlay").on("click", function(){
                    $("#overlay").remove();
                });
            }

        }
    };
});
