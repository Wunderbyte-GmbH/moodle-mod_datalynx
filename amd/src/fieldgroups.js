define(["jquery"], function($) {

    return {

        init: function(defaultlines, maxlines) {

            // We hide lines after the last line we show by default.
            defaultlines++;

            // Loop from defaultlines to maxlines.
            for (var line = defaultlines; line <= maxlines; line++) {
                // Disable every input within, this corrupts picture.
                $("#mform1 #id_" + line + " :input").prop('disabled', true);

                // Hide the whole line.
                $("#mform1 #id_" + line).hide();
            }

            // Add button functionality.
            $( "#id_addline" ).each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        $('fieldset:hidden:first').show(); // Find the first id that is hidden.
                        $('fieldset:visible:last :input').prop('disabled', false);
                    });
            });

            $( "#id_hideline" ).each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        $('fieldset:visible:last').hide();
                        $('fieldset:hidden:first :input').prop('disabled', false);
                    });
            });

        }
    };
});
