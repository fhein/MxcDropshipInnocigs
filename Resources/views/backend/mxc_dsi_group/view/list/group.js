//{namespace name=backend/mxc_dsi_test/view/list/innocigs_group}
//{block name="backend/mxc_dsi_test/view/mxc_dsi_test/view/list/window"}
Ext.define('Shopware.apps.MxcDsiGroup.view.list.Group', {
    extend: 'Shopware.grid.Panel',
    alias:  'widget.mxc-dsi-group-listing-grid',
    region: 'center',

    snippets: {
        groups: {
            acceptedGroups: '{s name=innocigs/configurator/group/accepted_groups_header}Accepted groups{/s}',
            ignoredGroups: '{s name=innocigs/configurator/group/ignored_groups_header}Ignored groups{/s}',
            selected: '{s name=innocigs/configurator/group/group_header_selected}selected{/s}',
        }
    },

    initComponent: function() {
        let me = this;
        me.listeners = {
            cellclick: function(view, td, cellIndex, record) {
                if (cellIndex === 0 && record.get('accepted') === true) {
                    me.fireEvent('mxcSelectGroup', record, false);
                }
            },
            viewready: function(view, opts) {
                let selected = [];
                me.store.each(function(record) {
                    if (record.get('accepted') === true) {
                        selected.push(record);
                    }
                });
                if (selected.length > 0) {
                    me.getSelectionModel().select(selected, true, true);
                }
            }
        };
        me.callParent(arguments);
    },

    configure: function() {
        let me = this;
        return {
            detailWindow: 'Shopware.apps.MxcDsiGroup.view.detail.Window',
            columns: {
                name:       { header: 'Name', flex: 3 }
            },
            toolbar: false,
            deleteColumn: false,
            pagingbar: false
        };
    },

    registerEvents: function() {
        let me = this;
        me.callParent(arguments);
        me.addEvents(
            /**
             * @event mxcSaveGroup
             */
            'mxcSaveGroup',
            /**
             * @event mxcSelectGroup
             */
            'mxcSelectGroup',
        );
    },

    createSelectionModel: function () {
        let me = this;
        return Ext.create('Ext.selection.CheckboxModel', {
            checkOnly: true,
            showHeaderCheckbox: false,
            listeners: {
                select: function (sm, record) {
                    let success = me.fireEvent('mxcSelectGroup', record, true);
                    if (success === false) {
                        sm.deselect(record, true);
                    }
                },
            }
        });
    },

    createFeatures: function() {
        let me = this;
        let items = me.callParent(arguments);

        me.groupingFeature =  Ext.create('Ext.grid.feature.Grouping', {
            groupHeaderTpl: Ext.create('Ext.XTemplate',
                '<span>{ name:this.formatHeader }</span>',
                '<span>&nbsp;({ rows.length } ' + me.snippets.groups.selected + ')</span>',
                {
                    formatHeader: function(accepted) {
                        if (accepted === true || accepted === 'true') {
                            return me.snippets.groups.acceptedGroups;
                        } else {
                            return me.snippets.groups.ignoredGroups;
                        }
                    }
                }
            ),
            // hideGroupedHeader: true,
            // startCollapsed: false
        });

        items.push(me.groupingFeature);
        return items;
    },
});
//{/block}