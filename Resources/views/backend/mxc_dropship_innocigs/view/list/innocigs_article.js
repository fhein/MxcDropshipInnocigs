//

Ext.define('Shopware.apps.MxcDropshipInnocigs.view.list.InnocigsArticle', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.mxc-innocigs-article-listing-grid',
    region: 'center',

    configure: function() {
        return {
            detailWindow: 'Shopware.apps.MxcDropshipInnocigs.view.detail.Window',
            columns: {
                code:   { header: 'Code'},
                name:   { header: 'Name', flex: 3 },
                active: { header: 'active', width: 60, flex: 0 }
            },
            addButton: false,
            deleteButton: false,
            deleteColumn: false
        };
    },

    registerEvents: function() {
        var me = this;
        me.callParent(arguments);
        me.addEvents(
            /**
             * @event mxcSaveArticle
             */
            'mxcSaveArticle',
            /**
             * @event mxcSaveActiveStates
             */
            'mxcSaveActiveStates'
        );
    },

    createToolbarItems: function() {
        var me = this;
        var items = me.callParent(arguments);
        items = Ext.Array.insert(items, 0, [ me.createActivateButton(), me.createDeactivateButton()]);
        return items;
    },

    handleActiveStateChanges: function(changeTo) {
        var me = this;
        var selModel = me.getSelectionModel();
        var records = selModel.getSelection();
        Ext.each(records, function(record) {
            // deselect records which already have the target states
            // set the target state otherwise
            if (record.get('active') === changeTo) {
                selModel.deselect(record);
            } else {
                record.set('active', changeTo)
            }
        });
        me.fireEvent('mxcSaveActiveStates', selModel);
    },

    createActivateButton: function() {
        var me = this;
        return Ext.create('Ext.button.Button', {
            text: 'Activate selected',
            iconCls: 'sprite-tick',
            handler: function() {
                me.handleActiveStateChanges(true);
            }
        });
    },

    createDeactivateButton: function() {
        var me = this;
        return Ext.create('Ext.button.Button', {
            text: 'Deactivate selected',
            iconCls: 'sprite-cross',
            handler: function() {
                me.handleActiveStateChanges(false);
            }
        });
    },

    createPlugins: function () {
        var me = this;
        var items = me.callParent(arguments);

        me.cellEditor = Ext.create('Ext.grid.plugin.CellEditing', {
            clicksToEdit: 1,
            listeners: {
                edit: function(editor, e) {
                    // the 'edit' event gets fired even if the new value equals the old value
                    if (e.originalValue === e.value) {
                        return;
                    }
                    me.fireEvent('mxcSaveArticle', e.record);
                }
            }
        });
        items.push(me.cellEditor);

        return items;
    },

    destroy: function() {
        var me = this;
        // If the window gets closed while the cell editor is active
        // an exception gets thrown. This is a workaround for that problem.
        me.cellEditor.completeEdit();
        me.callParent(arguments);
    }
});
