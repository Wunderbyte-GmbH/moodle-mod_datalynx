define(["jquery"], function($) {

    return {

        init: function(fieldgroupname, defaultlines, maxlines) {

            // We hide lines after the last line we show by default.
            defaultlines++;

            // Loop from defaultlines to maxlines.
            for (var line = defaultlines; line <= maxlines; line++) {
                $("div[data-field-name='" + fieldgroupname + "'] [data-line='" + line + "']").hide(); // Hide the whole line.
            }

            // Add button functionality.
            $("div[data-field-name='" + fieldgroupname + "'] #id_addline").each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        if ($("input[name=visiblelines]").get(0).value < maxlines) {
                            $("div[data-field-name='" + fieldgroupname + "'] .lines:hidden:first").show(); // Find the first hidden.
                            $("input[name=visiblelines]").get(0).value++; // Add one to the visible lines input.
                        }
                    });
            });

            $("div[data-field-name='" + fieldgroupname + "'] #id_hideline").each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        if ($("input[name=visiblelines]").get(0).value > 0) {
                            $("div[data-field-name='" + fieldgroupname + "'] .lines:visible:last").hide();
                            $("input[name=visiblelines]").get(0).value--;
                        }
                    });
            });

        }
    };
});
