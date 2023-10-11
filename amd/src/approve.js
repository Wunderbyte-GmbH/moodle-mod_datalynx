define(['jquery'], function($) {

    return {
        init: function(approvedicon, disapprovedicon) {
            // After initialisation we loop through all links for subscribe / unsubscribe.
            $( ".datalynxfield__approve" ).each(function () {
                var href = this.href;
                var params = extractParams(href.split('?')[1]);

                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        // AJAX call
                        var actionurl = "field/_approve/ajax.php";
                        $.ajax(
                            {
                                method: "POST",
                                url: actionurl,
                                data: params,
                                context: this,
                                success: function(data) {
                                    if (data && $(this).children().hasClass('approved')) {
                                        $(this).children().removeClass('approved');
                                        $(this).children().prop('src', disapprovedicon);
                                        $(this).children().prop('alt', 'approve');
                                        $(this).children().prop('title', 'approve');
                                        params.action = 'approve';
                                    } else if (data && !$(this).children().hasClass('approved')) {
                                        $(this).children().addClass('approved');
                                        $(this).children().prop('src', approvedicon);
                                        $(this).children().prop('alt', 'disapprove');
                                        $(this).children().prop('title', 'disapprove');
                                        params.action = 'disapprove';
                                    }
                                },
                            }
                        );
                });

            });

            /**
             * Extract params.
             *
             * @param {string} paramstring
             * @returns {{}}
             */
            function extractParams(paramstring) {
                let params = paramstring.split("&");
                let output = {}; // Create an object.
                for (var i = 0; i < params.length; i++) {
                    var param = params[i];
                    output[param.split("=")[0]] = param.split("=")[1];
                }
                if ('approve' in output) {
                    output.entryid = output.approve;
                    output.action = 'approve';
                } else if ('disapprove' in output) {
                    output.entryid = output.disapprove;
                    output.action = 'disapprove';
                } else {
                    output.entryid = output.approve;
                    output.action = 'approve';
                }
                return output;
            }
        }
    };
});
