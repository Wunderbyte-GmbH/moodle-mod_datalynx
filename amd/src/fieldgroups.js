define(["jquery"], function($) {

    return {

        init: function(fieldgroupname, defaultlines, maxlines, requiredlines) {

            // We hide lines after the last line we show by default.
            defaultlines++;

            // Loop from defaultlines to maxlines.
            for (var line = defaultlines; line <= maxlines; line++) {
                $("div[data-field-name='" + fieldgroupname + "'] [data-line='" + line + "']").hide(); // Hide the whole line.
            }

            // Add button functionality.
            $("div.datalynx-field-wrapper #id_addline").click(function (e) {
                e.preventDefault(); // Don't follow hrefs.
                $(this).closest(".datalynx-field-wrapper").find("[data-line]:hidden:first").show();
            });

            // Remove this one line.
            $("div[data-field-name='" + fieldgroupname + "'] [data-removeline]").each(function () {
                    $(this).off( "click" );
                    $(this).click(function(e) {
                        e.preventDefault(); // Don't follow hrefs.
                        var thisline = $(this).closest('.lines');
                        var lineid = thisline.data("line");
                        var parentcontainer = thisline.closest('.datalynx-field-wrapper');
                        var lastvisibleline = parentcontainer.find('.lines:visible').last();
                        var lastvisiblelineid = lastvisibleline.data('line');
                        // Remove all files from associated file manager.
                        thisline.find('.fp-file').each(function () {
                            $(this).click();
                            $(".fp-file-delete:visible").trigger('click');
                            $(".fp-dlg-butconfirm:visible").trigger('click');
                        });
                        // Remove data from input fields.
                        $(this).closest('.lines').find('input').each(function () {
                            // Do not affect hidden inputs.
                            if ($(this).attr('type') != 'hidden') {
                                $(this).val('');
                            }
                        });
                        // Remove atto editor content.
                        $(this).closest('.lines').find('.editor_atto_content').each(function () {
                            $(this).html('');
                        });
                        // Remove textarea content.
                        $(this).closest('.lines').find('textarea').each(function () {
                            $(this).val('');
                        });
                        // Remove single select content.
                        $(this).closest('.lines').find('select').each(function () {
                            $(this).find('option[value=""]').prop('selected', true);
                        });
                        // Deactivate the time/date field and remove team members.
                        $(this).closest('.lines').find('[id$=enabled]:checked,' +
                            ' .form-autocomplete-selection .tag').each(function () {
                            $(this).trigger('click');
                        });

                        // Hide the empty lines if not required or the only line remaining.
                        // TODO: Changed this to >= so people can remove the first line as well.
                        if(lineid > requiredlines && lineid >= 1) {
                            thisline.hide();
                            // Alter DOM: Reorder lines and make content ordered properly.
                            // Strategy: The deleted line should be moved under the last
                            // visible line. All visible lines from thisline up to lastvisibline get moved one line up.
                            // Lines not visible should not be changed.
                            // thisline will be first not visible line and gets id of lastvisibleline.
                            // TODO: Also move the content if the lines in the right place.
                            if (lineid != maxlines) {
                                parentcontainer.find('[data-line]').each(function () {
                                    if($(this).data('line') > lineid && $(this).data('line') <= lastvisiblelineid) {
                                        // Line numbers minus one.
                                        var newid = $(this).data('line') - 1;
                                        $(this).attr('data-line', newid);
                                    }
                                    if($(this).data('line') == lineid) {
                                    // New line number for removed line.
                                    $(this).attr('data-line', lastvisiblelineid);
                                    }
                                });
                            }
                            var newcontentorder = [];
                            parentcontainer.find('[data-line]').each(function () {
                                newcontentorder[$(this).data('line')] = $(this);
                            });
                            parentcontainer.remove('.lines');
                            for (var i = newcontentorder.length; i >= 0; i--){
                                parentcontainer.prepend(newcontentorder[i]);
                            }
                        }

                    });
            });

        }

    };
});
