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

            // Remove this one line.
            $("div[data-field-name='" + fieldgroupname + "'] [data-removeline]").each(function () {
                    $(this).addClass("btn-danger");
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.

                        // Remove files from file manager....
                        // $(this).closest('[data-line]').find('.fp-file').click();
                        // Go from removeline to maxline and move all inputs up by one.
                        var removeline = $(this).data('removeline');
                        for (var i = removeline; i <= maxlines; i++) {

                            var next = $("div[data-field-name='" + fieldgroupname +
                                "'] div[data-line='" + (i+1) + "'] input[type='text']");
                            for (var j = 0; j < next.length; j++) {
                                var nextValue = next.eq(j).val();
                                var currentFieldId = $("div[data-field-name='" + fieldgroupname +
                                    "'] div[data-line='" + i + "'] input[type='text']")[j].id;
                                $("#" + currentFieldId).val(nextValue);
                            }
                        }

                        // Set the last line to empty.
                         $("div[data-field-name='" + fieldgroupname + "'] div[data-line='" + maxlines +
                            "'] input[type='text']").val('');

                        // Hide the last visible line.
                        if ($("input[name=visiblelines]").get(0).value > 0) {
                            $("div[data-field-name='" + fieldgroupname + "'] .lines:visible:last").hide();
                            $("input[name=visiblelines]").get(0).value--;
                        }

                    });
            });

        }
    };
});
