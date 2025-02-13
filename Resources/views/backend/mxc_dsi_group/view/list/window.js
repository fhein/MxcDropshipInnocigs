//

Ext.define('Shopware.apps.MxcDsiGroup.view.list.Window', {
    extend: 'Shopware.window.Listing',
    alias: 'widget.mxc-dsi-group-list-window',
    height: 450,
    width: 624,
    title : 'InnoCigs Configurator Groups',

    configure: function() {
        return {
            listingGrid: 'Shopware.apps.MxcDsiGroup.view.list.Group',
            listingStore: 'Shopware.apps.MxcDsiGroup.store.Group',
        };
    }
});