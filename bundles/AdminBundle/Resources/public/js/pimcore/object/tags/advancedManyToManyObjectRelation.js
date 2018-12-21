/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) 2009-2013 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

pimcore.registerNS("pimcore.object.tags.advancedManyToManyObjectRelation");
pimcore.object.tags.advancedManyToManyObjectRelation = Class.create(pimcore.object.tags.manyToManyObjectRelation, {

    type: "advancedManyToManyObjectRelation",
    dataChanged: false,
    idProperty: "id",
    pathProperty: "fullpath",
    allowBatchAppend: true,

    initialize: function (data, fieldConfig) {
        this.data = [];
        this.fieldConfig = fieldConfig;

        var classStore = pimcore.globalmanager.get("object_types_store");
        var classIdx = classStore.findExact("text", fieldConfig.allowedClassId);
        var classNameText;
        if (classIdx >= 0) {
            var classRecord = classStore.getAt(classIdx);
            classNameText = classRecord.data.text;
        } else {
            classNameText = "";
        }

        this.fieldConfig.classes = [{classes: classNameText, id: fieldConfig.allowedClassId}];

        if (data) {
            this.data = data;
        }

        var fields = [];
        if (typeof this.fieldConfig.visibleFields != "string") {
            this.fieldConfig.visibleFields = "";
        }

        var visibleFields = Ext.isString(this.fieldConfig.visibleFields) ? this.fieldConfig.visibleFields.split(",") : [];
        this.visibleFields = visibleFields;

        fields.push("id");
        fields.push("inheritedFields");
        fields.push("metadata");

        var i;

        for (i = 0; i < visibleFields.length; i++) {
            fields.push(visibleFields[i]);
        }

        for (i = 0; i < this.fieldConfig.columns.length; i++) {
            fields.push(this.fieldConfig.columns[i].key);
        }

        this.store = new Ext.data.JsonStore({
            data: this.data,
            listeners: {
                add: function () {
                    this.dataChanged = true;
                }.bind(this),
                remove: function () {
                    this.dataChanged = true;
                }.bind(this),
                clear: function () {
                    this.dataChanged = true;
                }.bind(this),
                update: function (store) {
                    if (store.ignoreDataChanged) {
                        return;
                    }
                    this.dataChanged = true;
                }.bind(this)
            },
            fields: fields
        });
    },

    createLayout: function (readOnly) {
        var autoHeight = false;
        if (intval(this.fieldConfig.height) < 15) {
            autoHeight = true;
        }

        var cls = 'object_field';
        var i;

        var visibleFields = this.visibleFields;

        var columns = [];
        columns.push({text: 'ID', dataIndex: 'id', width: 50});

        for (i = 0; i < visibleFields.length; i++) {
            if (!empty(this.fieldConfig.visibleFieldDefinitions) && !empty(visibleFields[i])) {
                var layout = this.fieldConfig.visibleFieldDefinitions[visibleFields[i]];

                var field = {
                    key: visibleFields[i],
                    label: layout.title == "fullpath" ? t("reference") : layout.title,
                    layout: layout,
                    position: i,
                    type: layout.fieldtype
                };

                var fc = pimcore.object.tags[layout.fieldtype].prototype.getGridColumnConfig(field);

                fc.width = 100;
                fc.hidden = false;
                fc.layout = field;
                fc.editor = null;
                fc.sortable = false;
                if(fc.layout.key === "fullpath") {
                    fc.renderer = this.fullPathRenderCheck.bind(this);
                }
                columns.push(fc);
            }
        }

        for (i = 0; i < this.fieldConfig.columns.length; i++) {
            var width = 100;
            if (this.fieldConfig.columns[i].width) {
                width = this.fieldConfig.columns[i].width;
            }

            var cellEditor = null;
            var renderer = null;
            var listeners = null;

            if (this.fieldConfig.columns[i].type == "number" && !readOnly) {
                cellEditor = function() {
                    return new Ext.form.NumberField({});
                }.bind();
            } else if (this.fieldConfig.columns[i].type == "text" && !readOnly) {
                cellEditor = function() {
                    return new Ext.form.TextField({});
                };
            } else if (this.fieldConfig.columns[i].type == "select" && !readOnly) {
               var selectData = [];

                if (this.fieldConfig.columns[i].value) {
                    var selectDataRaw = this.fieldConfig.columns[i].value.split(";");

                    for (var j = 0; j < selectDataRaw.length; j++) {
                        selectData.push([selectDataRaw[j], ts(selectDataRaw[j])]);
                    }
                }

                cellEditor = function(selectData) {
                    return new Ext.form.ComboBox({
                        typeAhead: true,
                        queryDelay: 0,
                        queryMode: "local",
                        forceSelection: true,
                        triggerAction: 'all',
                        lazyRender: false,
                        mode: 'local',

                        store: new Ext.data.ArrayStore({
                            fields: [
                                'value',
                                'label'
                            ],
                            data: selectData
                        }),
                        valueField: 'value',
                        displayField: 'label'
                    });
                }.bind(this, selectData);
            } else if(this.fieldConfig.columns[i].type == "multiselect" && !readOnly) {
                cellEditor =  function(fieldInfo) {
                    return new pimcore.object.helpers.metadataMultiselectEditor({
                        fieldInfo: fieldInfo
                    });
                }.bind(this, this.fieldConfig.columns[i]);
            } else if (this.fieldConfig.columns[i].type == "bool") {
                renderer = function (value, metaData, record, rowIndex, colIndex, store) {
                    if (value) {
                        return '<div style="text-align: center"><div role="button" class="x-grid-checkcolumn x-grid-checkcolumn-checked" style=""></div></div>';
                    } else {
                        return '<div style="text-align: center"><div role="button" class="x-grid-checkcolumn" style=""></div></div>';
                    }
                };

                listeners = {
                    "mousedown": this.cellMousedown.bind(this, this.fieldConfig.columns[i].key, this.fieldConfig.columns[i].type)
                };

                if (readOnly) {
                    columns.push(Ext.create('Ext.grid.column.Check'), {
                        text: ts(this.fieldConfig.columns[i].label),
                        dataIndex: this.fieldConfig.columns[i].key,
                        width: width,
                        renderer: renderer
                    });
                    continue;
                }

            }

            if(this.fieldConfig.columns[i].type == "select") {
                renderer = function (value, metaData, record, rowIndex, colIndex, store) {
                    return ts(value);
                }
            }

            var columnConfig = {
                text: ts(this.fieldConfig.columns[i].label),
                dataIndex: this.fieldConfig.columns[i].key,
                renderer: renderer,
                listeners: listeners,
                sortable: true,
                width: width
            };

            if (cellEditor) {
                columnConfig.getEditor = cellEditor;
            }

            columns.push(columnConfig);
        }


        if (!readOnly) {
            columns.push({
                xtype: 'actioncolumn',
                menuText: t('up'),
                width: 40,
                items: [
                    {
                        tooltip: t('up'),
                        icon: "/bundles/pimcoreadmin/img/flat-color-icons/up.svg",
                        handler: function (grid, rowIndex) {
                            if (rowIndex > 0) {
                                var rec = grid.getStore().getAt(rowIndex);
                                grid.getStore().removeAt(rowIndex);
                                grid.getStore().insert(rowIndex - 1, [rec]);
                            }
                        }.bind(this)
                    }
                ]
            });
            columns.push({
                xtype: 'actioncolumn',
                menuText: t('down'),
                width: 40,
                items: [
                    {
                        tooltip: t('down'),
                        icon: "/bundles/pimcoreadmin/img/flat-color-icons/down.svg",
                        handler: function (grid, rowIndex) {
                            if (rowIndex < (grid.getStore().getCount() - 1)) {
                                var rec = grid.getStore().getAt(rowIndex);
                                grid.getStore().removeAt(rowIndex);
                                grid.getStore().insert(rowIndex + 1, [rec]);
                            }
                        }.bind(this)
                    }
                ]
            });
        }

        columns.push({
            xtype: 'actioncolumn',
            menuText: t('open'),
            width: 40,
            items: [
                {
                    tooltip: t('open'),
                    icon: "/bundles/pimcoreadmin/img/flat-color-icons/open_file.svg",
                    handler: function (grid, rowIndex) {
                        var data = grid.getStore().getAt(rowIndex);
                        pimcore.helpers.openObject(data.data.id, "object");
                    }.bind(this)
                }
            ]
        });

        if (!readOnly) {
            columns.push({
                xtype: 'actioncolumn',
                menuText: t('remove'),
                width: 40,
                items: [
                    {
                        tooltip: t('remove'),
                        icon: "/bundles/pimcoreadmin/img/flat-color-icons/delete.svg",
                        handler: function (grid, rowIndex) {
                            grid.getStore().removeAt(rowIndex);
                        }.bind(this)
                    }
                ]
            });
        }

        var toolbarItems = this.getEditToolbarItems(readOnly);


        this.cellEditing = Ext.create('Ext.grid.plugin.CellEditing', {
            clicksToEdit: 1,
            listeners: {
                beforeedit: function (editor, context, eOpts) {
                    editor.editors.each(function (e) {
                        try {
                            // complete edit, so the value is stored when hopping around with TAB
                            e.completeEdit();
                            Ext.destroy(e);
                        } catch (exception) {
                            // garbage collector was faster
                            // already destroyed
                        }
                    });

                    editor.editors.clear();
                }
            }
        });


        this.component = Ext.create('Ext.grid.Panel', {
            store: this.store,
            border: true,
            style: "margin-bottom: 10px",
            enableDragDrop: true,
            ddGroup: 'element',
            trackMouseOver: true,
            selModel: {
                selType: (this.fieldConfig.enableBatchEdit ? 'checkboxmodel': 'rowmodel')
            },
            columnLines: true,
            stripeRows: true,
            columns: {
                items: columns
            },
            viewConfig: {
                plugins: {
                    ptype: 'gridviewdragdrop',
                    draggroup: 'element'
                },
                markDirty: false,
                listeners: {
                    refresh: function (gridview) {
                        this.requestNicePathData(this.store.data);
                    }.bind(this),
                    drop: function () {
                        // this is necessary to avoid endless recursion when long lists are sorted via d&d
                        // TODO: investigate if there this is already fixed 6.2
                        if (this.object.toolbar && this.object.toolbar.items && this.object.toolbar.items.items) {
                            this.object.toolbar.items.items[0].focus();
                        }
                    }.bind(this),
                    // see https://github.com/pimcore/pimcore/issues/979
                    // probably a ExtJS 6.0 bug. withou this, dropdowns not working anymore if plugin is enabled
                    // TODO: investigate if there this is already fixed 6.2
                    cellmousedown: function (element, td, cellIndex, record, tr, rowIndex, e, eOpts) {
                        if (cellIndex >= visibleFields.length) {
                            return false;
                        } else {
                            return true;
                        }
                    }
                }
            },
            componentCls: cls,
            width: this.fieldConfig.width,
            height: this.fieldConfig.height,
            tbar: {
                items: toolbarItems,
                ctCls: "pimcore_force_auto_width",
                cls: "pimcore_force_auto_width",
                minHeight: 32
            },
            autoHeight: autoHeight,
            bodyCls: "pimcore_object_tag_objects pimcore_editable_grid",
            plugins: [
                this.cellEditing
            ]
        });

        if (!readOnly) {
            this.component.on("rowcontextmenu", this.onRowContextmenu);
        }

        this.component.reference = this;

        if (!readOnly) {
            this.component.on("afterrender", function () {

                var dropTargetEl = this.component.getEl();
                var gridDropTarget = new Ext.dd.DropZone(dropTargetEl, {
                    ddGroup: 'element',
                    getTargetFromEvent: function (e) {
                        return this.component.getEl().dom;
                        //return e.getTarget(this.grid.getView().rowSelector);
                    }.bind(this),

                    onNodeOver: function (overHtmlNode, ddSource, e, data) {
                        var returnValue = Ext.dd.DropZone.prototype.dropAllowed;
                        data.records.forEach(function (record) {
                            var fromTree = this.isFromTree(ddSource);
                            if (!this.dndAllowed(record.data, fromTree)) {
                                returnValue = Ext.dd.DropZone.prototype.dropNotAllowed;
                            }
                        }.bind(this));

                        return returnValue;
                    }.bind(this),

                    onNodeDrop: function (target, dd, e, data) {

                        this.nodeElement = data;
                        var fromTree = this.isFromTree(dd);
                        var toBeRequested = new Ext.util.Collection();

                        data.records.forEach(function (record) {
                            var data = record.data;
                            if (this.dndAllowed(data, fromTree)) {
                                if (data["grid"] && data["grid"] == this.component) {
                                    var rowIndex = this.component.getView().findRowIndex(e.target);
                                    if (rowIndex !== false) {
                                        var rec = this.store.getAt(data.rowIndex);
                                        this.store.removeAt(data.rowIndex);
                                        toBeRequested.add(this.store.insert(rowIndex, [rec]));
                                        this.requestNicePathData(toBeRequested);
                                    }
                                } else {
                                    var initData = {
                                        id: data.id,
                                        metadata: '',
                                        inheritedFields: {}
                                    };

                                    if (!this.objectAlreadyExists(initData.id)) {
                                        toBeRequested.add(this.loadObjectData(initData, this.visibleFields));
                                    }
                                }
                            }
                        }.bind(this));

                        if(toBeRequested.length) {
                            this.requestNicePathData(toBeRequested);
                            return true;
                        }

                        return false;

                    }.bind(this)
                });

                if (this.fieldConfig.enableBatchEdit) {
                    var grid = this.component;
                    var menu = grid.headerCt.getMenu();

                    var batchAllMenu = new Ext.menu.Item({
                        text: t("batch_change"),
                        iconCls: "pimcore_icon_table pimcore_icon_overlay_go",
                        handler: function (grid) {
                            var columnDataIndex = menu.activeHeader;
                            this.batchPrepare(columnDataIndex, grid, false, false);
                        }.bind(this, grid)
                    });

                    menu.add(batchAllMenu);

                    var batchSelectedMenu = new Ext.menu.Item({
                        text: t("batch_change_selected"),
                        iconCls: "pimcore_icon_structuredTable pimcore_icon_overlay_go",
                        handler: function (grid) {
                            menu = grid.headerCt.getMenu();
                            var columnDataIndex = menu.activeHeader;
                            this.batchPrepare(columnDataIndex, grid, true, false);
                        }.bind(this, grid)
                    });
                    menu.add(batchSelectedMenu);
                    menu.on('beforeshow', function (batchAllMenu, grid) {
                        var menu = grid.headerCt.getMenu();
                        var columnDataIndex = menu.activeHeader.dataIndex;
                        var metaIndex = this.fieldConfig.columnKeys.indexOf(columnDataIndex);

                        if (metaIndex < 0) {
                            batchAllMenu.hide();
                        } else {
                            batchAllMenu.show();
                        }

                    }.bind(this, batchAllMenu, grid));
                }
            }.bind(this));
        }


        return this.component;
    },

    getLayoutEdit: function () {
        return this.createLayout(false);
    },

    getLayoutShow: function () {
        return this.createLayout(true);
    },

    getEditToolbarItems: function (readOnly) {
        var toolbarItems = [
            {
                xtype: "tbspacer",
                width: 20,
                height: 16,
                cls: "pimcore_icon_droptarget"
            },
            {
                xtype: "tbtext",
                text: "<b>" + this.fieldConfig.title + "</b>"
            }];

        if (!readOnly) {
            toolbarItems = toolbarItems.concat([
                "->",
                {
                    xtype: "button",
                    iconCls: "pimcore_icon_delete",
                    handler: this.empty.bind(this)
                },
                {
                    xtype: "button",
                    iconCls: "pimcore_icon_search",
                    handler: this.openSearchEditor.bind(this)
                },
                this.getCreateControl()]);
        }

        return toolbarItems;
    },

    dndAllowed: function (data, fromTree) {
        // check if data is a treenode, if not allow drop because of the reordering
        if (!fromTree) {
            if (data["grid"] && data["grid"] == this.component) {
                return true;
            }
            return false;
        }

        // only allow objects not folders
        if (data.type == "folder" || data.elementType != "object") {
            return false;
        }

        var classname = data.className;

        var classStore = pimcore.globalmanager.get("object_types_store");
        var classRecord = classStore.getAt(classStore.findExact("text", classname));
        var isAllowedClass = false;

        if (classRecord) {
            if (this.fieldConfig.allowedClassId == classRecord.data.text) {
                isAllowedClass = true;
            }
        }
        return isAllowedClass;
    },

    addDataFromSelector: function (items) {

        if (items.length > 0) {
            toBeRequested = new Ext.util.Collection();

            for (var i = 0; i < items.length; i++) {
                if (!this.objectAlreadyExists(items[i].id)) {
                    toBeRequested.add(this.loadObjectData(items[i], this.visibleFields));
                }
            }
            this.requestNicePathData(toBeRequested);
        }
    },

    cellMousedown: function (key, colType, grid, cell, rowIndex, cellIndex, e) {

        // this is used for the boolean field type

        var store = grid.getStore();
        var record = store.getAt(rowIndex);

        if (colType == "bool") {
            record.set(key, !record.data[key]);
        }
    },

    loadObjectData: function (item, fields) {

        var newItem = this.store.add(item);

        Ext.Ajax.request({
            url: "/admin/object-helper/load-object-data",
            params: {
                id: item.id,
                'fields[]': fields
            },
            success: function (response) {
                var rdata = Ext.decode(response.responseText);
                var key;

                if (rdata.success) {
                    var rec = this.store.getById(item.id);
                    for (key in rdata.fields) {
                        rec.set(key, rdata.fields[key]);
                    }
                }
            }.bind(this)
        });

        return newItem;
    },

    normalizeTargetData: function (targets) {
        if (!targets) {
            return targets;
        }

        targets.each(function (record) {
            var type = record.data.type;
            record.data.type = "object";
            record.data.subtype = type;
            record.data.path = record.data.fullpath;
        }, this);

        return targets;

    },

    getGridColumnConfig: function(field) {
        return {text: ts(field.label), width: 150, sortable: false, dataIndex: field.key,
            getEditor: this.getWindowCellEditor.bind(this, field),
            renderer: function (key, value, metaData, record) {
                this.applyPermissionStyle(key, value, metaData, record);

                if(record.data.inheritedFields[key]
                    && record.data.inheritedFields[key].inherited == true) {
                    metaData.tdCls += " grid_value_inherited";
                }


                if (value) {
                    var result = [];
                    var i;
                    for (i = 0; i < value.length && i < 10; i++) {
                        var item = value[i];
                        result.push(item["fullpath"]);
                    }
                    return result.join("<br />");
                }
                return value;
            }.bind(this, field.key)};
    },


    getCellEditValue: function () {
        return this.getValue();
    },

    batchPrepare: function(columnDataIndex, grid, onlySelected, append){
        var columnIndex = columnDataIndex.fullColumnIndex;
        var editor = grid.getColumns()[columnIndex].getEditor();
        var metaIndex = this.fieldConfig.columnKeys.indexOf(columnDataIndex.dataIndex);
        var columnConfig = this.fieldConfig.columns[metaIndex];

        if (columnConfig.type == 'multiselect') { //create edit layout for multiselect field
            var selectData = [];
            if (columnConfig.value) {
                var selectDataRaw = columnConfig.value.split(";");
                for (var j = 0; j < selectDataRaw.length; j++) {
                    selectData.push([selectDataRaw[j], ts(selectDataRaw[j])]);
                }
            }

            var store = new Ext.data.ArrayStore({
                fields: [
                    'id',
                    'label'
                ],
                data: selectData
            });

            var options = {
                triggerAction: "all",
                editable: false,
                store: store,
                componentCls: "object_field",
                height: '100%',
                valueField: 'id',
                displayField: 'label'
            };

            editor = Ext.create('Ext.ux.form.MultiSelect', options);
        } else if (columnConfig.type == 'bool') { //create edit layout for bool meta field
            editor = new Ext.form.Checkbox();
        }

        var editorLabel = Ext.create('Ext.form.Label', {
          text: grid.getColumns()[columnIndex].text + ':',
          style: {
            float: 'left',
            margin: '0 20px 0 0'
          }
        });

        var formPanel = Ext.create('Ext.form.Panel', {
            xtype: "form",
            border: false,
            items: [editorLabel, editor],
            bodyStyle: "padding: 10px;",
            buttons: [
                {
                    text: t("edit"),
                    handler: function() {
                        if(formPanel.isValid()) {
                            this.batchProcess(columnDataIndex.dataIndex, editor, grid, onlySelected);
                        }
                    }.bind(this)
                }
            ]
        });
        var batchTitle = onlySelected ? "batch_edit_field_selected" : "batch_edit_field";
        var title = t(batchTitle) + " " + grid.getColumns()[columnIndex].text;
        this.batchWin = new Ext.Window({
            autoScroll: true,
            modal: false,
            title: title,
            items: [formPanel],
            bodyStyle: "background: #fff;",
            width: 500,
            maxHeight: 400
        });
        this.batchWin.show();
        this.batchWin.updateLayout();

    },

    batchProcess: function (dataIndex, editor, grid, onlySelected) {

        var newValue = editor.getValue();
        var valueType = "primitive";

        if (onlySelected) {
            var selectedRows = grid.getSelectionModel().getSelection();
            for (var i=0; i<selectedRows.length; i++) {
                selectedRows[i].set(dataIndex, newValue);
            }
        } else {
            var items = grid.store.data.items;
            for (var i = 0; i < items.length; i++)
            {
                var record = grid.store.getAt(i);
                record.set(dataIndex, newValue);
            }
        }

        this.batchWin.close();
    }

});

// @TODO BC layer, to be removed in v6.0
pimcore.object.tags.objectsMetadata = pimcore.object.tags.advancedManyToManyObjectRelation;
