define(["jquery"], function($) {

    return {

        init: function(defaultlines, maxlines) {

            defaultlines++; // We start from 1.

            // Loop from defaultlines to maxlines.
            for (var line = defaultlines; line <= maxlines; line++) {
                // Disable every input within, this corrupts filemanager.
                // $("#mform1 #id_" + line + " :input").prop('disabled', true);

                // Hide the whole line.
                $("#mform1 #id_" + line).hide();
            }

            // Add button functionality.
            $( "#id_addline" ).each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.

                        // Find the first id that is hidden.
                        $('fieldset:hidden:first').show();
                    });
            });
            $( "#id_hideline" ).each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        $('fieldset:visible:last').hide();
                    });
            });

        }
    };
});
