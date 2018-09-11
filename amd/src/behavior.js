define(['jquery'], function($) {

    return {
        init: function() {
            $('img[data-for]').on( "click", function ( event ) {

                var img = event.target;
                var behaviorid = img.getAttribute('data-behavior-id');
                var permissionid = img.getAttribute('data-permission-id');
                var forproperty = img.getAttribute('data-for');
                var sesskey = $('table.datalynx-behaviors').attr('data-sesskey'); // Maybe M.cfg.sesskey.
                var actionurl = "behavior_edit_ajax.php";


                // This was in javascript-static.js in lib.
                var obj = {
                                behaviorid: behaviorid,
                                permissionid: permissionid,
                                forproperty: forproperty,
                                sesskey: sesskey
                            };

                var list = [];
                for(var k in obj) {
                    k = encodeURIComponent(k);
                    var value = obj[k];
                    if(obj[k] instanceof Array) {
                        for(var i in value) {
                            list.push(k+'[]='+encodeURIComponent(value[i]));
                        }
                    } else {
                        list.push(k+'='+encodeURIComponent(value));
                    }
                }
                obj = list.join('&');

                // Ajax request to get current options.
                $.ajax(
                    {
                        method: "POST",
                        timeout: 5000,
                        url: actionurl,
                        data: obj,
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
