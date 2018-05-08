define(['jquery'], function($) {

    return {
        init: function(options) {
            var coursefield = options.coursefield;
            var groupfield = options.groupfield;
            var actionurl = options.acturl; // Points to loadgroups.php.

            // Read groupid from second select and enter in input field.
            $("#id_"+groupfield).on( "change", function () {
                $("#id_"+groupfield + 'id').val($( "#id_"+groupfield+" option:selected" ).val());
            });

            // Read courseid and call ajax at change to receive all groups in course.
            $("#id_"+coursefield).on( "change", function () {
                var group = $( "#id_"+groupfield ); // Get group select.
                var courseid =  $( "#id_"+coursefield+" option:selected" ).val(); // Get the courseid.
                group.find('option').remove().end(); // Remove current options.

                // Load groups from course.
                if (courseid != 0) {
                    // Ajax request to get current options.
                    $.ajax(
                        {
                            method: "POST",
                            url: actionurl,
                            data: 'courseid=' + courseid,
                            context: this,
                            dataType: "text",
                            success: function(data) {
                                if (data != '') {
                                    // Add Group to options, value is groupid.
                                    $.each(data.split(','), function(key, value) {
                                        group.append($("<option></option>").attr("value",value.split(" ",1)).text(value));
                                    });
                                }
                            },
                            error: function() {
                                alert("Error");
                            }
                        }
                    );
                }
            }); // End on change _course.
        }
    };
});
