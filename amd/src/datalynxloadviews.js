define(['jquery'], function($) {

    return {
        init: function(options) {
            // Get field name from options.
            var dffield = options.dffield;
            var viewfield = options.viewfield;
            var textfieldfield = options.textfieldfield;
            var actionurl = options.acturl;
            var presentdlid = options.presentdlid;
            var thisfieldstring = options.thisfieldstring;
            var update = options.update;
            var fieldtype = options.fieldtype;

            // Read courseid and call ajax at change to receive all groups in course.
            $("#id_"+dffield).on( "change", function () {
                var view = $( "#id_"+viewfield ); // Get view select.
                var textfield = $( "#id_"+textfieldfield ); // Get textfield select.
                var dfid = $(this).val(); // Get the datalynx id.

                // Remove view and textfield options from view select.
                if (view) {
                    view.find('option').remove().end(); // Remove current options.
                }
                if (textfield) {
                    textfield.find('option').remove().end(); // Remove current options.
                }


                // Load views and/or textfields from datalynx.
                if (dfid != 0) {
                    // Ajax request to get current options.
                    $.ajax(
                        {
                            method: "POST",
                            url: actionurl,
                            data: 'dfid=' + dfid,
                            context: this,
                            dataType: "text",
                            success: function(data) {
                                if (data != '') {
                                    var respoptions = data.split('#');

                                    // Add view options.
                                    if (view) {
                                        var viewoptions = respoptions[0].split(',');
                                        for (var i = 0; i < viewoptions.length; ++i) {
                                            var arr = viewoptions[i].trim().split(' ');
                                            var qid = arr.shift();
                                            var qname = arr.join(' ');
                                            view.append($("<option></option>").attr("value",qid).text(qname));
                                        }
                                    }

                                    // Add textfield options.
                                    if (textfield) {
                                        var textfieldoptions = respoptions[1].split(',');

                                        // If this datalynx instance itself is chosen provide this new field itself as first option.
                                        if (dfid == presentdlid && update == 0 && fieldtype == 'text') {
                                            textfield.append($("<option></option>").attr("value","-1").text(thisfieldstring));
                                        }
                                        for (var i = 0; i < textfieldoptions.length; ++i) {
                                            var arr = textfieldoptions[i].trim().split(' ');
                                            var qid = arr.shift();
                                            var qname = arr.join(' ');
                                            textfield.append($("<option></option>").attr("value",qid).text(qname));
                                        }
                                    }
                                }
                            },
                            error: function() {
                                alert("Error while loading views and textfields.");
                            }
                        });
                }
            });
        }
    };
});