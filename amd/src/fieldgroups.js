define(["jquery"], function($) {

    return {

        init: function(defaultlines, maxlines) {

            // We hide lines after the last line we show by default.
            defaultlines++;

            // Loop from defaultlines to maxlines.
            for (var line = defaultlines; line <= maxlines; line++) {
                $("#mform1 #line_" + line).hide(); // Hide the whole line.
            }

            // Add button functionality.
            $( "#id_addline" ).each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        if ($('input[name=visiblelines]').get(0).value < maxlines) {
                            $('.lines:hidden:first').show(); // Find the first id that is hidden.
                            $('input[name=visiblelines]').get(0).value++; // Add one to the visible lines input.
                        }
                    });
            });

            $( "#id_hideline" ).each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        if ($('input[name=visiblelines]').get(0).value > 0) {
                            $('.lines:visible:last').hide();
                            $('input[name=visiblelines]').get(0).value--;
                        }
                    });
            });

        }
    };
});
