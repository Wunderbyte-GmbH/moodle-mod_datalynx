define(['jquery'], function($) {

    return {
        init: function() {
            $('table.datalynx-behaviors img[data-for]').on( "click", function ( event ) {

                var img = event.target;
                var behaviorid = img.getAttribute('data-behavior-id');
                var permissionid = img.getAttribute('data-permission-id');
                var forproperty = img.getAttribute('data-for');
                var sesskey = $('table.datalynx-behaviors').attr('data-sesskey');
                var actionurl = "behavior_edit_ajax.php";
                var build_querystring;


                // Ajax request to get current options.
                $.ajax(
                    {
                        method: "POST",
                        timeout: 5000,
                        url: actionurl,
                        data: build_querystring({
                            behaviorid: behaviorid,
                            permissionid: permissionid,
                            forproperty: forproperty,
                            sesskey: sesskey
                        }),
                        context: this,
                        dataType: "text",
                        success: function(data) {
                            if (data != '') {
                                // console.log("RAW JSON DATA: " + data);
                                var src = img.getAttribute("src");
                                if (src.search("-enabled") !== -1) {
                                    src = src.replace("-enabled", "-n");
                                } else {
                                    src = src.replace("-n", "-enabled");
                                }
                                img.setAttribute("src", src);
                            }
                        },
                        error: function() {
                            alert("Error");
                        }
                    }
                );


            });
        }
    };
});



/*
M.mod_datalynx.behaviors_helper.toggle_image = function (img) {
    alert("image");
    var src = img.get("src");
    if (src.search("-enabled") !== -1) {
        src = src.replace("-enabled", "-n");
    } else {
        src = src.replace("-n", "-enabled");
    }
    img.set("src", src);
}
*/