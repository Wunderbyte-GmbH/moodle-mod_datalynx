define(["jquery"], function($) {

    return {

        init: function(defaultlines, maxlines) {
            defaultlines++; // We start from 1.

            // Loop from defaultlines to maxlines.
            for (var line = defaultlines; line <= maxlines; line++) {
                // Disable every input within.
                // TODO: This corrupts filemanager.
                // $("#mform1 #id_" + line + " :input").prop('disabled', true);

                // Hide the whole line.
                $("#mform1 #id_" + line).hide();
            }

        }
    };
});
