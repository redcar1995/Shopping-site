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

pimcore.registerNS("pimcore.object.classes.data.multiselect");
pimcore.object.classes.data.multiselect = Class.create(pimcore.object.classes.data.data, {

    type: "multiselect",
    /**
     * define where this datatype is allowed
     */
    allowIn: {
        object: true,
        objectbrick: true,
        fieldcollection: true,
        localizedfield: true,
        classificationstore: true,
        block: true,
        encryptedField: true
    },

    initialize: function (treeNode, initData) {
        this.type = "multiselect";

        this.initData(initData);

        // overwrite default settings
        this.availableSettingsFields = ["name", "title", "tooltip", "mandatory", "noteditable", "invisible",
            "visibleGridView", "visibleSearch", "style"];

        this.treeNode = treeNode;
    },

    getTypeName: function () {
        return t("multiselect");
    },

    getGroup: function () {
        return "select";
    },

    getIconClass: function () {
        return "pimcore_icon_multiselect";
    },

    getLayout: function ($super) {

        $super();

        this.specificPanel.removeAll();
        var specificItems = this.getSpecificPanelItems(this.datax, false);
        this.specificPanel.add(specificItems);

        return this.layout;
    },

    getSpecificPanelItems: function (datax, inEncryptedField) {

        var selectionModel;

        if (typeof datax.options != "object") {
            datax.options = [];
        }

        var valueStore = new Ext.data.JsonStore({
            fields: ["key", "value"],
            data: datax.options
        });

        var cellEditing = Ext.create('Ext.grid.plugin.CellEditing', {
            clicksToEdit: 1
        });
        
        var valueGrid;

        valueGrid = Ext.create('Ext.grid.Panel', {
            itemId: "valueeditor",
            viewConfig: {
                plugins: [
                    {
                        ptype: 'gridviewdragdrop',
                        dragroup: 'objectclassselect'
                    }
                ]
            },
            plugins: [cellEditing],
            tbar: [{
                xtype: "tbtext",
                text: t("selection_options")
            }, "-", {
                xtype: "button",
                iconCls: "pimcore_icon_add",
                handler: function () {
                    var u = {
                        key: "",
                        value: ""
                    };

                    var selectedRow = selectionModel.getSelected();
                    var idx;
                    if (selectedRow) {
                        idx = valueStore.indexOf(selectedRow) + 1;
                    } else {
                        idx = valueStore.getCount();
                    }
                    valueStore.insert(idx, u);
                    selectionModel.select(idx);
                }.bind(this)
            },
                {
                    xtype: "button",
                    iconCls: "pimcore_icon_edit",
                    handler: this.showoptioneditor.bind(this, valueStore)

                }
            ],
            style: "margin-top: 10px",
            store: valueStore,
            disabled: this.isInCustomLayoutEditor(),
            selModel: Ext.create('Ext.selection.RowModel', {}),
            columnLines: true,
            columns: [
                {
                    text: t("display_name"), sortable: true, dataIndex: 'key', editor: new Ext.form.TextField({}),
                    width: 200
                },
                {
                    text: t("value"), sortable: true, dataIndex: 'value', editor: new Ext.form.TextField({}),
                    width: 200
                },
                {
                    xtype: 'actioncolumn',
                    menuText: t('up'),
                    width: 30,
                    items: [
                        {
                            tooltip: t('up'),
                            icon: "/pimcore/static6/img/flat-color-icons/up.svg",
                            handler: function (grid, rowIndex) {
                                if (rowIndex > 0) {
                                    var rec = grid.getStore().getAt(rowIndex);
                                    grid.getStore().removeAt(rowIndex);
                                    grid.getStore().insert(--rowIndex, [rec]);
                                    var sm = valueGrid.getSelectionModel();
                                    selectionModel.select(rowIndex);
                                }
                            }.bind(this)
                        }
                    ]
                },
                {
                    xtype: 'actioncolumn',
                    menuText: t('down'),
                    width: 30,
                    items: [
                        {
                            tooltip: t('down'),
                            icon: "/pimcore/static6/img/flat-color-icons/down.svg",
                            handler: function (grid, rowIndex) {
                                if (rowIndex < (grid.getStore().getCount() - 1)) {
                                    var rec = grid.getStore().getAt(rowIndex);
                                    grid.getStore().removeAt(rowIndex);
                                    grid.getStore().insert(++rowIndex, [rec]);
                                    var sm = valueGrid.getSelectionModel();
                                    selectionModel.select(rowIndex);
                                }
                            }.bind(this)
                        }
                    ]
                },
                {
                    xtype: 'actioncolumn',
                    menuText: t('remove'),
                    width: 30,
                    items: [
                        {
                            tooltip: t('remove'),
                            icon: "/pimcore/static6/img/flat-color-icons/delete.svg",
                            handler: function (grid, rowIndex) {
                                grid.getStore().removeAt(rowIndex);
                            }.bind(this)
                        }
                    ]
                }
            ],
            autoHeight: true
        });

        selectionModel = valueGrid.getSelectionModel();;
        valueGrid.on("afterrender", function () {

            var dropTargetEl = valueGrid.getEl();
            var gridDropTarget = new Ext.dd.DropZone(dropTargetEl, {
                ddGroup: 'objectclassmultiselect',
                getTargetFromEvent: function (e) {
                    return valueGrid.getEl().dom;
                }.bind(this),
                onNodeOver: function (overHtmlNode, ddSource, e, data) {
                    if (data["grid"] && data["grid"] == valueGrid) {
                        return Ext.dd.DropZone.prototype.dropAllowed;
                    }
                    return Ext.dd.DropZone.prototype.dropNotAllowed;
                }.bind(this),
                onNodeDrop: function (target, dd, e, data) {
                    if (data["grid"] && data["grid"] == valueGrid) {
                        var rowIndex = valueGrid.getView().findRowIndex(e.target);
                        if (rowIndex !== false) {
                            var store = valueGrid.getStore();
                            var rec = store.getAt(data.rowIndex);
                            store.removeAt(data.rowIndex);
                            store.insert(rowIndex, [rec]);
                        }
                    }
                    return false;
                }.bind(this)
            });
        }.bind(this));

        var specificItems = [
            {
                xtype: "numberfield",
                fieldLabel: t("width"),
                name: "width",
                value: datax.width
            },
            {
                xtype: "numberfield",
                fieldLabel: t("height"),
                name: "height",
                value: datax.height
            },
            {
                xtype: "numberfield",
                fieldLabel: t("maximum_items"),
                name: "maxItems",
                value: datax.maxItems,
                minValue: 0
            },
            {
                xtype: "combo",
                fieldLabel: t("multiselect_render_type"),
                name: "renderType",
                itemId: "renderType",
                mode: 'local',
                store: [
                    ['list', 'List'],
                    ['tags', 'Tags']
                ],
                value: datax["renderType"] ? datax["renderType"] : 'list',
                triggerAction: "all",
                editable: false,
                forceSelection: true
            },
            {
                xtype: "textfield",
                fieldLabel: t("options_provider_class"),
                width: 600,
                name: "optionsProviderClass",
                value: datax.optionsProviderClass
            },
            {
                xtype: "textfield",
                fieldLabel: t("options_provider_data"),
                width: 600,
                value: datax.optionsProviderData,
                name: "optionsProviderData"
            },
            valueGrid
        ];

        return specificItems;
    },

    applyData: function ($super) {

        $super();

        var options = [];

        var valueEditor = this.specificPanel.getComponent("valueeditor");
        if (valueEditor) {
            var valueStore = valueEditor.getStore();
            valueStore.commitChanges();
            valueStore.each(function (rec) {
                options.push({
                    key: rec.get("key"),
                    value: rec.get("value")
                });
            });
        }

        this.datax.options = options;
    },

    applySpecialData: function (source) {
        if (source.datax) {
            if (!this.datax) {
                this.datax = {};
            }
            Ext.apply(this.datax,
                {
                    options: source.datax.options,
                    width: source.datax.width,
                    height: source.datax.height,
                    maxItems: source.datax.maxItems,
                    renderType: source.datax.renderType
                });
        }
    },

    showoptioneditor: function (valueStore) {
        var editor = new pimcore.object.helpers.optionEditor(valueStore);
        editor.edit();
    }
});
