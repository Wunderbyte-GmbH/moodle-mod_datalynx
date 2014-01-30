/**
 * @package datalynxfield
 * @subpackage datalynxview
 * @copyright  2013 Itamar Tzadok
 */

/**
 * Datalynxview field overlay
 */
M.datalynxfield_datalynxview_overlay = {};

M.datalynxfield_datalynxview_overlay.init = function(Y, options) {
    YUI().use('panel', 'resize-plugin', 'dd-plugin', 'transition', function (Y) {

        var datalynxviews = Y.all('.datalynxfield-datalynxview.overlay');
        
        // Create the panel for each field.
        datalynxviews.each(function (dfview) {
            var viewBtn = dfview.one('button');
            var panelSource = dfview.one('.panelContent');
            var panel = new Y.Panel({
                srcNode      : panelSource,
                headerContent: 'Datalynx view',
                width        : 600,
                height       : 400,
                zIndex       : 1000,
                centered     : true,
                modal        : true,
                visible      : false,
                render       : true,
                plugins      : [Y.Plugin.Drag,Y.Plugin.Resize]
            });
            panelSource.removeClass('hide');
            // When the View is pressed, show the modal form.
            viewBtn.on('click', function (e) {
                panel.show();
            });
        });
    });
};