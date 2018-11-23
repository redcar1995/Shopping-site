/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

pimcore.registerNS("pimcore.asset.listfolder");
pimcore.asset.listfolder = Class.create({

    onlyDirectChildren: false,

    initialize: function (element) {
        this.element = element;

    },


    getLayout: function () {
        this.filterField = new Ext.form.TextField({
            width: 200,
            style: "margin: 0 10px 0 0;",
            enableKeyEvents: true,
            value: this.preconfiguredFilter,
            listeners: {
                "keydown" : function (field, key) {
                    if (key.getKey() == key.ENTER) {
                        var input = field;
                        var proxy = this.store.baseParams.filter = input.getValue();
                        this.store.load();
                    }
                }.bind(this)
            }
        });


        var proxy = new Ext.data.HttpProxy({
            type: 'ajax',
            url: "/admin/asset/grid-proxy",
            reader: {
                type: 'json',
                rootProperty: 'data',
                totalProperty: 'total',
                successProperty: 'success',
                idProperty: 'key'
            },
            extraParams: {
                limit: itemsPerPage,
                folderId: this.element.data.id
            }
        });

        var readerFields = ['id', 'fullpath','type', 'creationDate', 'modificationDate', 'size', 'idPath'];

        this.selectionColumn = new Ext.selection.CheckboxModel();

        var typesColumns = [
            {text: t("id"), sortable: true, dataIndex: 'id', editable: false, flex: 40, filter: 'numeric'},
            {text: t("filename"), sortable: true, dataIndex: 'fullpath', editable: false, flex: 100, filter: 'string', renderer: Ext.util.Format.htmlEncode},
            {text: t("type"), sortable: true, dataIndex: 'type', editable: false, flex: 50, filter: 'string'}
        ];


        typesColumns.push({text: t("creationDate"), width: 150, sortable: true, dataIndex: 'creationDate', editable: false, filter: 'date',
                                                                                renderer: function(d) {
            var date = new Date(d * 1000);
            return Ext.Date.format(date, "Y-m-d H:i:s");
        }});
        typesColumns.push({text: t("modificationDate"), width: 150, sortable: true, dataIndex: 'modificationDate', editable: false, filter: 'date',
        renderer: function(d) {
            var date = new Date(d * 1000);
            return Ext.Date.format(date, "Y-m-d H:i:s");
        }});

        typesColumns.push(
            {text: t("size"), sortable: false, dataIndex: 'size', editable: false, filter: 'string'}
        );

        var itemsPerPage = pimcore.helpers.grid.getDefaultPageSize(-1);
        this.store = new Ext.data.Store({
            proxy: proxy,
            remoteSort: true,
            remoteFilter: true,
            filter: this.filterField,
            fields: readerFields
        });

        this.pagingtoolbar = pimcore.helpers.grid.buildDefaultPagingToolbar(this.store, {pageSize: itemsPerPage});

        this.checkboxOnlyDirectChildren = new Ext.form.Checkbox({
            name: "onlyDirectChildren",
            style: "margin-bottom: 5px; margin-left: 5px",
            checked: this.onlyDirectChildren,
            boxLabel: t("only_children"),
            listeners: {
                "change" : function (field, checked) {
                    this.grid.filters.clearFilters();

                    this.store.getProxy().setExtraParam("only_direct_children", checked);

                    this.onlyDirectChildren = checked;
                    this.pagingtoolbar.moveFirst();
                }.bind(this)
            }
        });

        this.grid = Ext.create('Ext.grid.Panel', {
            title: "List",
            iconCls: "pimcore_icon_table_tab",
            frame: false,
            autoScroll: true,
            store: this.store,
            columnLines: true,
            stripeRows: true,
            columns : typesColumns,
            plugins: ['pimcore.gridfilters'],
            trackMouseOver: true,
            bbar: this.pagingtoolbar,
            selModel: this.selectionColumn,
            viewConfig: {
                forceFit: true
            },
            listeners: {
                activate: function() {
                    this.store.getProxy().setExtraParam("only_direct_children", this.onlyDirectChildren);
                    this.store.load();
                }.bind(this),
                rowdblclick: function(grid, record, tr, rowIndex, e, eOpts ) {
                    var data = this.store.getAt(rowIndex);
                    pimcore.helpers.openAsset(data.get("id"), data.get("type"));

                }.bind(this)
            },
            tbar: [
                "->"
                ,this.checkboxOnlyDirectChildren
                , "-"
                ,{
                    text: t("download_selected_as_zip"),
                    iconCls: "pimcore_icon_zip pimcore_icon_overlay_download",
                    handler: function () {
                        var ids = [];

                        var selectedRows = this.grid.getSelectionModel().getSelection();
                        for (var i = 0; i < selectedRows.length; i++) {
                            ids.push(selectedRows[i].data.id);
                        }

                        if(ids.length) {
                            pimcore.elementservice.downloadAssetFolderAsZip(this.element.id, ids);
                        } else {
                            Ext.Msg.alert(t('error'), t('please_select_items_to_download'));
                        }
                    }.bind(this)
                }
            ]
        });

        this.grid.on("rowcontextmenu", this.onRowContextmenu);

        return this.grid;
    },

    onRowContextmenu: function (grid, record, tr, rowIndex, e, eOpts ) {

        var menu = new Ext.menu.Menu();
        var data = grid.getStore().getAt(rowIndex);
        var selModel = grid.getSelectionModel();
        var selectedRows = selModel.getSelection();

        if (selectedRows.length <= 1) {

            menu.add(new Ext.menu.Item({
                text: t('open'),
                iconCls: "pimcore_icon_open",
                handler: function (data) {
                    pimcore.helpers.openAsset(data.data.id, data.data.type);
                }.bind(this, data)
            }));

            if (pimcore.elementservice.showLocateInTreeButton("asset")) {
                menu.add(new Ext.menu.Item({
                    text: t('show_in_tree'),
                    iconCls: "pimcore_icon_show_in_tree",
                    handler: function () {
                        try {
                            try {
                                pimcore.treenodelocator.showInTree(record.id, "asset", this);
                            } catch (e) {
                                console.log(e);
                            }

                        } catch (e2) {
                            console.log(e2);
                        }
                    }
                }));
            }
            
            menu.add(new Ext.menu.Item({
                text: t('delete'),
                iconCls: "pimcore_icon_delete",
                handler: function (data) {
                    var store = this.getStore();

                    var options = {
                        "elementType" : "asset",
                        "id": data.data.id,
                        "success": function() {
                            this.getStore().reload();
                        }.bind(this)
                    };

                    pimcore.elementservice.deleteElement(options);

                }.bind(grid, data)
            }));
        } else {
            menu.add(new Ext.menu.Item({
                text: t('open'),
                iconCls: "pimcore_icon_open",
                handler: function (data) {
                    var selectedRows = grid.getSelectionModel().getSelection();
                    for (var i = 0; i < selectedRows.length; i++) {
                        var data = selectedRows[i].data;
                        pimcore.helpers.openAsset(data.id, data.type);
                    }
                }.bind(this, data)
            }));

            menu.add(new Ext.menu.Item({
                text: t('delete'),
                iconCls: "pimcore_icon_delete",
                handler: function (data) {
                    var ids = [];
                    var selectedRows = grid.getSelectionModel().getSelection();
                    for (var i = 0; i < selectedRows.length; i++) {
                        ids.push(selectedRows[i].data.id);
                    }
                    ids = ids.join(',');

                    var options = {
                        "elementType" : "asset",
                        "id": ids,
                        "success": function() {
                            this.store.reload();
                        }.bind(this)
                    };

                    pimcore.elementservice.deleteElement(options);

                }.bind(grid, data)
            }));
        }

        e.stopEvent();
        menu.showAt(e.getXY());
    }

});

