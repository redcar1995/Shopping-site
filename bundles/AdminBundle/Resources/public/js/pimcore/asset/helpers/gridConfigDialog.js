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

pimcore.registerNS("pimcore.asset.helpers.gridConfigDialog");
pimcore.asset.helpers.gridConfigDialog = Class.create(pimcore.element.helpers.gridConfigDialog, {

    getLeftPanel: function () {
        if (!this.leftPanel) {
            var items = this.getMetadataTreePanel();

            this.leftPanel = new Ext.Panel({
                cls: "pimcore_panel_tree pimcore_gridconfig_leftpanel",
                region: "center",
                split: true,
                width: 300,
                minSize: 175,
                collapsible: true,
                collapseMode: 'header',
                collapsed: false,
                animCollapse: false,
                layout: 'accordion',
                hideCollapseTool: true,
                header: false,
                layoutConfig: {
                    animate: false
                },
                hideMode: "offsets",
                items: items
            });
        }

        return this.leftPanel;
    },

    commitData: function (save, preview) {

        this.data = {};
        if (this.languageField) {
            this.data.language = this.languageField.getValue();
        }

        if (this.itemsPerPage) {
            this.data.pageSize = this.itemsPerPage.getValue();
        }

        var operatorFound = false;

        if (this.selectionPanel) {
            this.data.columns = [];
            this.selectionPanel.getRootNode().eachChild(function (child) {
                var obj = {};

                if (child.data.isOperator) {
                    var attributes = child.data.configAttributes;
                    var operatorChilds = this.doGetRecursiveData(child);
                    attributes.childs = operatorChilds;
                    operatorFound = true;

                    obj.isOperator = true;
                    obj.attributes = attributes;

                } else {
                    obj.label =  child.data.text;
                    if(child.data.dataType == "system") {
                        obj.key = child.data.text + '~system';
                    } else if(child.data.dataType == "asset" || child.data.dataType == "object" || child.data.dataType == "document") {
                        child.data.layout.subtype = child.data.dataType;
                        child.data.layout.fieldtype = 'manyToOneRelation';
                    } else if(child.data.language) {
                        obj.key = child.data.text + '~' + child.data.language;
                        obj.label = child.data.layout.title = child.data.text + ' (' + child.data.language + ')';
                    } else {
                        obj.key = child.data.text;
                    }

                    obj.language = child.data.language;
                    obj.type = child.data.dataType;
                    obj.layout = child.data.layout;
                    if (child.data.width) {
                        obj.width = child.data.width;
                    }
                }
                this.data.columns.push(obj);
            }.bind(this));
        }

        var user = pimcore.globalmanager.get("user");

        if (this.showSaveAndShareTab) {
            this.settings = Ext.apply(this.settings, this.settingsForm.getForm().getFieldValues());
        }

        if (this.showSaveAndShareTab && user.isAllowed("share_configurations")) {

            if (this.settings.sharedUserIds != null) {
                this.settings.sharedUserIds = this.settings.sharedUserIds.join();
            }
            if (this.settings.sharedRoleIds != null) {
                this.settings.sharedRoleIds = this.settings.sharedRoleIds.join();
            }
            this.settings.shareGlobally = this.shareGlobally ? this.shareGlobally.getValue() : false;
        } else {
            delete this.settings.sharedUserIds;
            delete this.settings.sharedRoleIds;
        }

        if (!operatorFound) {
            if (preview) {
                this.requestPreview();
            } else {
                this.callback(this.data, this.settings, save);
                this.window.close();
            }
        } else {
            var columnsPostData = Ext.encode(this.data.columns);
            Ext.Ajax.request({
                url: "/admin/aset-helper/prepare-helper-column-configs",
                method: 'POST',
                params: {
                    columns: columnsPostData
                },
                success: function (preview, response) {
                    var responseData = Ext.decode(response.responseText);
                    this.data.columns = responseData.columns;

                    if (preview) {
                        this.requestPreview();
                    } else {
                        this.callback(this.data, this.settings, save);
                        this.window.close();
                    }

                }.bind(this, preview)
            });
        }
    },

    requestPreview: function () {
        var language = this.languageField.getValue();
        var fields = this.data.columns;
        var count = fields.length;
        var i;
        var keys = [];
        for (i = 0; i < count; i++) {
            var item = fields[i];
            keys.push(item.key);
        }

        Ext.Ajax.request({
            url: "/admin/asset/grid-proxy",
            params: {
                "folderId": this.previewSettings.folderId,
                "fields[]": keys,
                language: language,
                limit: 1
            },
            success: function (response) {
                var responseData = Ext.decode(response.responseText);
                if (responseData && responseData.data && responseData.data.length == 1) {
                    var rootNode = this.selectionPanel.getRootNode()
                    var childNodes = rootNode.childNodes;
                    var previewItem = responseData.data[0];
                    var store = this.selectionPanel.getStore()
                    var i;
                    var count = childNodes.length;

                    for (i = 0; i < count; i++) {
                        var node = childNodes[i];
                        var nodeId = node.id;
                        var column = this.data.columns[i];

                        var columnKey = column.key;
                        var value = previewItem[columnKey];

                        var record = store.getById(nodeId);
                        record.set("preview", value, {
                            commit: true
                        });
                    }
                }

            }.bind(this)
        });
    },

    getSelectionPanel: function () {
        if (!this.selectionPanel) {

            var childs = [];

            for (var i = 0; i < this.config.selectedGridColumns.length; i++) {
                var nodeConf = this.config.selectedGridColumns[i];

                if (nodeConf.isOperator) {
                    var child = this.doBuildChannelConfigTree([nodeConf.attributes]);
                    if (!child || !child[0]) {
                        continue;
                    }
                    child = child[0];
                } else {
                    if(nodeConf.layout) {
                        var text = nodeConf.layout.name;
                    } else {
                        var text = nodeConf.label;
                    }
                    var subType = nodeConf.type;

                    if (nodeConf.dataType !== "system" && this.showFieldname && subType) {
                        text = text + " (" + subType + ")";
                    }

                    var child = {
                        text: text,
                        key: nodeConf.key,
                        type: "data",
                        dataType: nodeConf.dataType,
                        leaf: true,
                        layout: nodeConf.layout,
                        iconCls: "pimcore_icon_" + nodeConf.dataType,
                        language: nodeConf.language
                    };
                    if (nodeConf.width) {
                        child.width = nodeConf.width;
                    }
                }
                childs.push(child);
            }

            this.cellEditing = Ext.create('Ext.grid.plugin.CellEditing', {
                clicksToEdit: 1,
                listeners: {
                    beforeedit: function(editor, context, eOpts) {
                        //need to clear cached editors of cell-editing editor in order to
                        //enable different editors per row
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
                    },
                    afteredit: function (editor) {
                        this.commitData(false, true);
                    }.bind(this)
                }
            });

            var store = new Ext.data.TreeStore({
                fields: [{
                    name: "text"
                }, {
                    name: "preview",
                    persist: false
                }

                ],
                root: {
                    id: "0",
                    root: true,
                    text: t("selected_grid_columns"),
                    leaf: false,
                    isTarget: true,
                    expanded: true,
                    children: childs
                }
            });

            var columns = [
                {
                    xtype: 'treecolumn',
                    text: t('configuration'),
                    dataIndex: 'text',
                    flex: 90
                }
            ];

            var languagestore = [["",t("default")],["none",t("none")]];
            for (var i = 0; i < pimcore.settings.websiteLanguages.length; i++) {
                languagestore.push([pimcore.settings.websiteLanguages[i],
                    pimcore.available_languages[pimcore.settings.websiteLanguages[i]]]);
            }

            columns.push({
                text: t('language'),
                sortable: true,
                dataIndex: "language",
                getEditor: function() {
                    return new Ext.form.ComboBox({
                        name: "language",
                        store: languagestore,
                        editable: false,
                        triggerAction: 'all',
                        mode: "local",
                        listeners: {
                            focusenter: function ( combo, event, eOpts ) {
                                var currentRecord =  this.selectionPanel.getSelection();
                                if(currentRecord[0].data.dataType == "system") {
                                    combo.disable();
                                } else {
                                    combo.expand();
                                }
                            }.bind(this),
                        },
                    });
                }.bind(this),
                width: 150
            });

            if (this.previewSettings && this.previewSettings.allowPreview) {
                columns.push({
                    dataIndex: 'preview',
                    text: t('preview'),
                    flex: 90,
                    renderer: function (value, metaData, record) {
                        if (record && record.parentNode.id == 0) {
                            var key = record.data.text;
                            record.data.inheritedFields = {};

                            if (key == "preview" && value) {
                                return '<img src="' + value + '" />';
                            } else if ((key == "modificationDate" || key == "creationDate") && value) {
                                var timestamp = intval(value) * 1000;
                                var date = new Date(timestamp);
                                return Ext.Date.format(date, "Y-m-d H:i");

                            } else {
                                var fieldType = record.data.dataType;

                                try {
                                    if (record.data.isOperator && record.data.configAttributes && pimcore.asset.tags[record.data.configAttributes.renderer]) {
                                        var rendererType = record.data.configAttributes.renderer;
                                        var tag = pimcore.asset.tags[rendererType];
                                    } else {
                                        var tag = pimcore.asset.tags[fieldType];
                                    }

                                    if (tag) {
                                        var fc = tag.prototype.getGridColumnConfig({
                                            key: key,
                                            layout: {
                                                noteditable: true
                                            }
                                        }, true);

                                        value = fc.renderer(value, null, record);
                                    }
                                } catch (e) {
                                    console.log(e);
                                }

                                if (typeof value == "string") {
                                    value = '<div style="max-height: 50px">' + value + '</div>';
                                }
                                return value;
                            }
                        }

                    }.bind(this)
                });
            }

            this.selectionPanel = new Ext.tree.Panel({
                store: store,
                plugins: [this.cellEditing],
                rootVisible: false,
                viewConfig: {
                    plugins: {
                        ptype: 'treeviewdragdrop',
                        ddGroup: "columnconfigelement"
                    },
                    listeners: {
                        beforedrop: function (node, data, overModel, dropPosition, dropHandlers, eOpts) {
                            var target = overModel.getOwnerTree().getView();
                            var source = data.view;

                            if (target != source) {
                                var record = data.records[0];
                                var isOperator = record.data.isOperator;
                                var realOverModel = overModel;
                                if (dropPosition == "before" || dropPosition == "after") {
                                    realOverModel = overModel.parentNode;
                                }

                                if (isOperator || this.parentIsOperator(realOverModel)) {
                                    var attr = record.data;
                                    if (record.data.configAttributes) {
                                        attr = record.data.configAttributes;
                                    }
                                    var element = this.getConfigElement(attr);
                                    var copy = element.getCopyNode(record);
                                    data.records = [copy]; // assign the copy as the new dropNode
                                    var configWindow = element.getConfigDialog(copy,
                                        {
                                            callback: this.updatePreview.bind(this)
                                        });

                                    if (configWindow) {
                                        //this is needed because of new focus management of extjs6
                                        setTimeout(function () {
                                            configWindow.focus();
                                        }, 250);
                                    }

                                } else {
                                    if (record.data.dataType == "system" && this.selectionPanel.getRootNode().findChild("text", record.data.key)) {
                                        dropHandlers.cancelDrop();
                                    } else {
                                        var copy = Ext.apply({}, record.data);
                                        delete copy.id;
                                        copy = record.createNode(copy);

                                        data.records = [copy]; // assign the copy as the new dropNode
                                    }
                                }
                            } else {
                                // node has been moved inside right selection panel
                                var record = data.records[0];
                                var isOperator = record.data.isOperator;
                                var realOverModel = overModel;
                                if (dropPosition == "before" || dropPosition == "after") {
                                    realOverModel = overModel.parentNode;
                                }

                                if (isOperator || this.parentIsOperator(realOverModel)) {
                                    var attr = record.data;
                                    if (record.data.configAttributes) {
                                        // there is nothing to do, this guy has been configured already
                                        return;
                                        // attr = record.data.configAttributes;
                                    }
                                    var element = this.getConfigElement(attr);

                                    var copy = element.getCopyNode(record);
                                    data.records = [copy]; // assign the copy as the new dropNode
                                    var window = element.getConfigDialog(copy, {
                                        callback: this.updatePreview.bind(this)
                                    });

                                    if (window) {
                                        //this is needed because of new focus management of extjs6
                                        setTimeout(function () {
                                            window.focus();
                                        }, 250);
                                    }

                                    record.parentNode.removeChild(record);
                                }
                            }
                        }.bind(this),
                        drop: function (node, data, overModel) {
                            overModel.set('expandable', true);
                            this.updatePreview();

                        }.bind(this),
                        nodedragover: function (targetNode, dropPosition, dragData, e, eOpts) {
                            var sourceNode = dragData.records[0];

                            if (sourceNode.data.isOperator) {
                                var realOverModel = targetNode;
                                if (dropPosition == "before" || dropPosition == "after") {
                                    realOverModel = realOverModel.parentNode;
                                }

                                var allowed = true;

                                if (typeof realOverModel.data.isChildAllowed == "function") {
                                    console.log("no child allowed");
                                    allowed = allowed && realOverModel.data.isChildAllowed(realOverModel, sourceNode);
                                }

                                if (typeof sourceNode.data.isParentAllowed == "function") {
                                    console.log("parent not allowed");
                                    allowed = allowed && sourceNode.data.isParentAllowed(realOverModel, sourceNode);
                                }

                                return allowed;
                            } else {
                                var targetNode = targetNode;

                                var allowed = true;
                                if (this.parentIsOperator(targetNode)) {
                                    if (dropPosition == "before" || dropPosition == "after") {
                                        targetNode = targetNode.parentNode;
                                    }

                                    if (typeof targetNode.data.isChildAllowed == "function") {
                                        allowed = allowed && targetNode.data.isChildAllowed(targetNode, sourceNode);
                                    }

                                    if (typeof sourceNode.data.isParentAllowed == "function") {
                                        allowed = allowed && sourceNode.data.isParentAllowed(targetNode, sourceNode);
                                    }

                                }

                                return allowed;
                            }
                        }.bind(this),
                        options: {
                            target: this.selectionPanel
                        }
                    }
                },
                id: 'tree',
                region: 'east',
                title: t('selected_grid_columns'),
                layout: 'fit',
                width: 640,
                split: true,
                autoScroll: true,
                rowLines: true,
                columnLines: true,
                trackMouseOver: true,
                listeners: {
                    itemcontextmenu: this.onTreeNodeContextmenu.bind(this)
                },
                columns: columns
            });
            var store = this.selectionPanel.getStore();
            var model = store.getModel();
            model.setProxy({
                type: 'memory'
            });
        }

        return this.selectionPanel;
    },

    getMetadataTreePanel: function () {
        if (!this.metadataTreePanel) {
            this.metadataTreePanel = this.getMetadataTree("/admin/asset-helper/get-metadata-for-column-config");
        }

        return this.metadataTreePanel;
    },

    getMetadataTree: function (url) {
        var metadataTreeHelper = new pimcore.asset.helpers.metadataTree(this.showFieldname);
        var tree = metadataTreeHelper.getMetaTree(url, 0, 0);

        tree.addListener("itemdblclick", function (tree, record, item, index, e, eOpts) {
            if (!record.data.root && record.data.type != "layout") {
                if (record.data.dataType == "system" && this.selectionPanel.getRootNode().findChild("text", record.data.key)) {
                    dropHandlers.cancelDrop();
                } else {
                    var copy = Ext.apply({}, record.data);

                    delete copy.id;
                    this.selectionPanel.getRootNode().appendChild(copy);

                    this.updatePreview();
                }
            }
        }.bind(this));

        return tree;
    },

    getConfigElement: function (configAttributes) {
        return null;
    }
});
