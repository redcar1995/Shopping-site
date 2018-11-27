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

pimcore.registerNS("pimcore.object.helpers.gridConfigDialog");
pimcore.object.helpers.gridConfigDialog = Class.create({

    showFieldname: true,
    data: {},
    brickKeys: [],

    initialize: function (columnConfig, callback, resetCallback, showSaveAndShareTab, settings, previewSettings) {

        this.config = columnConfig;
        this.callback = callback;
        this.resetCallback = resetCallback;
        this.showSaveAndShareTab = showSaveAndShareTab;
        this.isShared = settings && settings.isShared;
        this.previewSettings = previewSettings || {};

        this.settings = settings || {};

        if (!this.callback) {
            this.callback = function () {
            };
        }

        this.configPanel = new Ext.Panel({
            layout: "border",
            iconCls: "pimcore_icon_table",
            title: t("grid_configuration"),
            items: [this.getLanguageSelection(), this.getSelectionPanel(), this.getLeftPanel()]

        });


        var tabs = [this.configPanel];

        if (this.showSaveAndShareTab) {
            this.savePanel = this.getSaveAndSharePanel();
            tabs.push(this.savePanel);
        }

        this.tabPanel = new Ext.TabPanel({
            activeTab: 0,
            forceLayout: true,
            items: tabs
        });

        buttons = [];

        if (this.previewSettings && this.previewSettings.allowPreview) {
            buttons.push({
                    text: t("refresh_preview"),
                    iconCls: "pimcore_icon_refresh",
                    handler: function () {
                        this.updatePreview();
                    }.bind(this)
                }
            );
        }

        if (this.resetCallback) {
            buttons.push(
                {
                    xtype: "button",
                    text: t("reset_config"),
                    // hidden: this.isShared,
                    iconCls: "pimcore_icon_cancel",
                    tooltip: t('reset_config_tooltip'),
                    style: {
                        marginLeft: 100
                    },
                    handler: function () {
                        this.resetToDefault();
                    }.bind(this)
                }
            );
        }

        buttons.push({
                text: t("apply"),
                iconCls: "pimcore_icon_apply",
                handler: function () {
                    this.commitData();
                }.bind(this)
            }
        );

        if (this.showSaveAndShareTab) {
            buttons.push({
                text: this.isShared ? t("save_copy_and_share") : t("save_and_share"),
                iconCls: "pimcore_icon_save",
                handler: function () {
                    if (!this.nameField.getValue()) {
                        this.tabPanel.setActiveTab(this.savePanel);
                        Ext.Msg.show({
                            title: t("error"),
                            msg: t('invalid_name'),
                            buttons: Ext.Msg.OK,
                            icon: Ext.MessageBox.ERROR
                        });
                        return;
                    }
                    this.commitData(true);
                }.bind(this)
            });
        }

        this.window = new Ext.Window({
            width: 950,
            height: '95%',
            modal: true,
            title: t('grid_options'),
            layout: "fit",
            items: [this.tabPanel],
            buttons: buttons
        });

        this.window.show();
        this.updatePreview();
    },

    getSaveAndSharePanel: function () {

        var user = pimcore.globalmanager.get("user");
        if (user.isAllowed("share_configurations")) {

            this.userStore = new Ext.data.JsonStore({
                autoDestroy: true,
                autoLoad: true,
                proxy: {
                    type: 'ajax',
                    url: '/admin/user/get-users-for-sharing',
                    reader: {
                        rootProperty: 'data',
                        idProperty: 'id'
                    }
                },
                fields: ['id', 'label']
            });

            this.rolesStore = new Ext.data.JsonStore({
                autoDestroy: true,
                autoLoad: true,
                proxy: {
                    type: 'ajax',
                    url: '/admin/user/get-roles-for-sharing',
                    reader: {
                        rootProperty: 'data',
                        idProperty: 'id'
                    }
                },
                fields: ['id', 'label']
            });
        }

        this.nameField = new Ext.form.TextField({
            fieldLabel: t('name'),
            name: 'gridConfigName',
            length: 50,
            allowBlank: false,
            width: '100%',
            value: this.settings ? this.settings.gridConfigName : ""
        });

        this.descriptionField = new Ext.form.TextArea({
            fieldLabel: t('description'),
            name: 'gridConfigDescription',
            height: 200,
            width: '100%',
            value: this.settings ? this.settings.gridConfigDescription : ""
        });

        if (user.isAllowed("share_configurations")) {
            this.userSharingField = Ext.create('Ext.form.field.Tag', {
                name: "sharedUserIds",
                width: '100%',
                height: 100,
                fieldLabel: t("shared_users"),
                queryDelay: 0,
                resizable: true,
                queryMode: 'local',
                minChars: 1,
                store: this.userStore,
                displayField: 'label',
                valueField: 'id',
                forceSelection: true,
                filterPickList: true,
                value: this.settings.sharedUserIds ? this.settings.sharedUserIds : ""
            });

            this.rolesSharingField = Ext.create('Ext.form.field.Tag', {
                name: "sharedRoleIds",
                width: '100%',
                height: 100,
                fieldLabel: t("shared_roles"),
                queryDelay: 0,
                resizable: true,
                queryMode: 'local',
                minChars: 1,
                store: this.rolesStore,
                displayField: 'label',
                valueField: 'id',
                forceSelection: true,
                filterPickList: true,
                value: this.settings.sharedRoleIds ? this.settings.sharedRoleIds : ""
            });
        }

        var items = [this.nameField, this.descriptionField];

        var user = pimcore.globalmanager.get("user");
        if (user.admin) {
            this.shareGlobally = new Ext.form.field.Checkbox(
                {
                    fieldLabel: t("share_globally"),
                    inputValue: true,
                    name: "shareGlobally",
                    value: this.settings.shareGlobally
                }
            );

            items.push(this.shareGlobally);
        }

        if (user.isAllowed("share_configurations")) {
            items.push(this.userSharingField);
            items.push(this.rolesSharingField);
        }

        this.settingsForm = Ext.create('Ext.form.FormPanel', {
            defaults: {
                labelWidth: 200
            },
            hidden: !this.showSaveAndShareTab,
            bodyStyle: "padding:10px;",
            autoScroll: true,
            border: false,
            iconCls: "pimcore_icon_save_and_share",
            title: user.isAllowed("share_configurations") ? t("save_and_share") : t("save"),
            items: items
        });
        return this.settingsForm;
    },

    doBuildChannelConfigTree: function (configuration) {

        var elements = [];
        if (configuration) {
            for (var i = 0; i < configuration.length; i++) {
                var configElement = this.getConfigElement(configuration[i]);
                if (configElement) {
                    var treenode = configElement.getConfigTreeNode(configuration[i]);

                    if (configuration[i].childs) {
                        var childs = this.doBuildChannelConfigTree(configuration[i].childs);
                        treenode.children = childs;
                        if (childs.length > 0) {
                            treenode.expandable = true;
                        }
                    }
                    elements.push(treenode);
                } else {
                    console.log("config element not found");
                }
            }
        }
        return elements;
    },

    getLeftPanel: function () {
        if (!this.leftPanel) {

            var items = this.getOperatorTrees();
            items.unshift(this.getClassDefinitionTreePanel());


            this.brickKeys = [];
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

    resetToDefault: function () {
        if (this.resetCallback) {
            this.resetCallback();
        } else {
            console.log("not supported");
        }
        this.window.close();
    },


    doGetRecursiveData: function (node) {
        var childs = [];
        node.eachChild(function (child) {
            var attributes = child.data.configAttributes;
            attributes.childs = this.doGetRecursiveData(child);
            childs.push(attributes);
        }.bind(this));

        return childs;
    },

    updatePreview: function () {
        this.commitData(false, true);
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
                    obj.key = child.data.key;
                    obj.label = child.data.layout ? child.data.layout.title : child.data.text;
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
                url: "/admin/object-helper/prepare-helper-column-configs",
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
            url: "/admin/object/grid-proxy?classId=" + this.previewSettings.classId + "&folderId=" + this.previewSettings.objectId,
            method: 'POST',
            params: {
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


    getLanguageSelection: function () {
        var storedata = [["default", t("default")]];
        for (var i = 0; i < pimcore.settings.websiteLanguages.length; i++) {
            storedata.push([pimcore.settings.websiteLanguages[i],
                pimcore.available_languages[pimcore.settings.websiteLanguages[i]]]);
        }

        var itemsPerPageStore = [
            [25, "25"],
            [50, "50"],
            [100, "100"],
            [200, "200"],
            [999999, t("all")]
        ];

        this.languageField = new Ext.form.ComboBox({
            name: "language",
            width: 330,
            mode: 'local',
            autoSelect: true,
            editable: false,
            value: this.config.language,
            store: new Ext.data.ArrayStore({
                id: 0,
                fields: [
                    'id',
                    'label'
                ],
                data: storedata
            }),
            listeners: {
                change: function() {
                    this.updatePreview();
                }.bind(this)
            },
            triggerAction: 'all',
            valueField: 'id',
            displayField: 'label'
        });

        this.itemsPerPage = new Ext.form.ComboBox({
            name: "itemsperpage",
            fieldLabel: t("items_per_page"),
            width: 200,
            mode: 'local',
            autoSelect: true,
            editable: true,
            value: (this.config.pageSize ? this.config.pageSize : pimcore.helpers.grid.getDefaultPageSize(-1)),
            store: new Ext.data.ArrayStore({
                id: 0,
                fields: [
                    'id',
                    'label'
                ],
                data: itemsPerPageStore
            }),
            triggerAction: 'all',
            valueField: 'id',
            displayField: 'label'
        });

        var compositeConfig = {
            xtype: "fieldset",
            layout: 'hbox',
            border: false,
            style: "border-top: none !important;",
            hideLabel: false,
            fieldLabel: t("language"),
            items: [this.languageField, {
                xtype: 'tbfill'
            }, this.itemsPerPage]
        };

        if (!this.languagePanel) {
            this.languagePanel = new Ext.form.FormPanel({
                region: "north",
                height: 43,
                items: [compositeConfig]
            });
        }

        return this.languagePanel;
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
                    var text = ts(nodeConf.label);

                    if (nodeConf.dataType !== "system" && this.showFieldname && nodeConf.key) {
                        text = text + " (" + nodeConf.key.replace("~", ".") + ")";
                    }

                    var child = {
                        text: text,
                        key: nodeConf.key,
                        type: "data",
                        dataType: nodeConf.dataType,
                        leaf: true,
                        layout: nodeConf.layout,
                        iconCls: "pimcore_icon_" + nodeConf.dataType
                    };
                    if (nodeConf.width) {
                        child.width = nodeConf.width;
                    }
                }
                childs.push(child);
            }

            this.cellEditing = Ext.create('Ext.grid.plugin.CellEditing', {
                clicksToEdit: 1
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

            this.selectionPanel = new Ext.tree.TreePanel({
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
                                    if (this.selectionPanel.getRootNode().findChild("key", record.data.key)) {
                                        dropHandlers.cancelDrop();
                                    } else {
                                        var copy = Ext.apply({}, record.data);
                                        delete copy.id;
                                        copy = record.createNode(copy);

                                        var ownerTree = this.selectionPanel;

                                        if (record.data.dataType == "classificationstore") {
                                            setTimeout(function () {
                                                var ccd = new pimcore.object.classificationstore.columnConfigDialog();
                                                ccd.getConfigDialog(ownerTree, copy, this.selectionPanel);
                                            }.bind(this), 100);
                                        }
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

                                var sourceType = this.getNodeTypeAndClass(sourceNode);
                                var targetType = this.getNodeTypeAndClass(realOverModel);
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
                listeners: {
                    itemcontextmenu: this.onTreeNodeContextmenu.bind(this)
                },
                columns: [
                    {
                        xtype: 'treecolumn',                    //this is so we know which column will show the tree
                        text: t('configuration'),
                        dataIndex: 'text',
                        flex: 90
                    },
                    {
                        dataIndex: 'preview',
                        text: t('preview'),
                        flex: 90,
                        renderer: function (value, metaData, record) {

                            if (record && record.parentNode.id == 0) {

                                var key = record.data.key;
                                record.data.inheritedFields = {};

                                if (key == "modificationDate" || key == "creationDate") {
                                    var timestamp = intval(value) * 1000;
                                    var date = new Date(timestamp);
                                    return Ext.Date.format(date, "Y-m-d H:i");

                                } else if (key == "published") {
                                    return Ext.String.format('<div style="text-align: left"><div role="button" class="x-grid-checkcolumn{0}" style=""></div></div>', value ? '-checked' : '');
                                } else {
                                    var fieldType = record.data.dataType;

                                    try {
                                        if (record.data.isOperator && record.data.configAttributes && pimcore.object.tags[record.data.configAttributes.renderer]) {
                                            var rendererType = record.data.configAttributes.renderer;
                                            var tag = pimcore.object.tags[rendererType];
                                        } else {
                                            var tag = pimcore.object.tags[fieldType];
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
                    }
                ]
            });
            var store = this.selectionPanel.getStore();
            var model = store.getModel();
            model.setProxy({
                type: 'memory'
            });
        }

        return this.selectionPanel;
    },

    parentIsOperator: function (record) {
        while (record) {
            if (record.data.isOperator) {
                return true;
            }
            record = record.parentNode;
        }
        return false;
    },

    getNodeTypeAndClass: function (node) {
        var type = "value";
        var className = "";
        if (node.data.configAttributes) {
            type = node.data.configAttributes.type;
            className = node.data.configAttributes['class'];
        } else if (node.data.dataType) {
            className = node.data.dataType.toLowerCase();
        }
        return {type: type, className: className};
    },

    onTreeNodeContextmenu: function (tree, record, item, index, e, eOpts) {
        e.stopEvent();

        tree.select();

        var menu = new Ext.menu.Menu();

        if (this.id != 0) {
            menu.add(new Ext.menu.Item({
                text: t('delete'),
                iconCls: "pimcore_icon_delete",
                handler: function (node) {
                    record.parentNode.removeChild(record, true);
                }.bind(this, record)
            }));

            if (record.data.children && record.data.children.length > 0) {
                menu.add(new Ext.menu.Item({
                    text: t('collapse_children'),
                    iconCls: "pimcore_icon_collapse_children",
                    handler: function (node) {
                        record.collapseChildren();
                    }.bind(this, record)
                }));

                menu.add(new Ext.menu.Item({
                    text: t('expand_children'),
                    iconCls: "pimcore_icon_expand_children",
                    handler: function (node) {
                        record.expandChildren();
                    }.bind(this, record)
                }));
            }

            if (record.data.isOperator) {
                menu.add(new Ext.menu.Item({
                    text: t('edit'),
                    iconCls: "pimcore_icon_edit",
                    handler: function (node) {
                        this.getConfigElement(node.data.configAttributes).getConfigDialog(node,
                            {
                                callback: this.updatePreview.bind(this)
                            });
                    }.bind(this, record)
                }));
            }
        }

        menu.showAt(e.pageX, e.pageY);
    },


    getClassDefinitionTreePanel: function () {
        if (!this.classDefinitionTreePanel) {

            var items = [];

            this.brickKeys = [];
            this.classDefinitionTreePanel = this.getClassTree("/admin/class/get-class-definition-for-column-config",
                this.config.classid, this.config.objectId);
        }

        return this.classDefinitionTreePanel;
    },

    getClassTree: function (url, classId, objectId) {

        var classTreeHelper = new pimcore.object.helpers.classTree(this.showFieldname);
        var tree = classTreeHelper.getClassTree(url, classId, objectId);

        tree.addListener("itemdblclick", function (tree, record, item, index, e, eOpts) {
            if (!record.data.root && record.datatype != "layout"
                && record.data.dataType != 'localizedfields') {
                var copy = Ext.apply({}, record.data);

                if (this.selectionPanel && !this.selectionPanel.getRootNode().findChild("key", record.data.key)) {
                    delete copy.id;
                    copy = this.selectionPanel.getRootNode().appendChild(copy);

                    var ownerTree = this.selectionPanel;

                    if (record.data.dataType == "classificationstore") {
                        var ccd = new pimcore.object.classificationstore.columnConfigDialog();
                        ccd.getConfigDialog(ownerTree, copy, this.selectionPanel);
                    } else {
                        this.updatePreview();
                    }
                }
            }
        }.bind(this));

        return tree;
    },

    getOperatorTrees: function () {
        var operators = Object.keys(pimcore.object.gridcolumn.operator);
        var operatorGroups = [];
        // var childs = [];
        for (var i = 0; i < operators.length; i++) {
            var operator = operators[i];
            if (!this.availableOperators || this.availableOperators.indexOf(operator) >= 0) {
                var nodeConfig = pimcore.object.gridcolumn.operator[operator].prototype;
                var configTreeNode = nodeConfig.getConfigTreeNode();

                var operatorGroup = nodeConfig.operatorGroup ? nodeConfig.operatorGroup : "other";

                if (!operatorGroups[operatorGroup]) {
                    operatorGroups[operatorGroup] = [];
                }

                var groupName = nodeConfig.group || "other";
                if (!operatorGroups[operatorGroup][groupName]) {
                    operatorGroups[operatorGroup][groupName] = [];
                }
                operatorGroups[operatorGroup][groupName].push(configTreeNode);
            }
        }

        var operatorGroupKeys = [];
        for (k in operatorGroups) {
            if (operatorGroups.hasOwnProperty(k)) {
                operatorGroupKeys.push(k);
            }
        }
        operatorGroupKeys.sort();
        var result = [];
        var len = operatorGroupKeys.length;
        for (i = 0; i < len; i++) {
            var operatorGroupName = operatorGroupKeys[i];
            var groupNodes = operatorGroups[operatorGroupName];
            result.push(this.getOperatorTree(operatorGroupName, groupNodes));

        }
        return result;
    },

    getOperatorTree: function (operatorGroupName, groups) {
        var groupKeys = [];
        for (k in groups) {
            if (groups.hasOwnProperty(k)) {
                groupKeys.push(k);
            }
        }

        groupKeys.sort();

        var len = groupKeys.length;

        var groupNodes = [];

        for (i = 0; i < len; i++) {
            var k = groupKeys[i];
            var childs = groups[k];
            childs.sort(
                function (x, y) {
                    return x.text < y.text ? -1 : 1;
                }
            );

            var groupNode = {
                iconCls: 'pimcore_icon_folder',
                text: t(k),
                allowDrag: false,
                allowDrop: false,
                leaf: false,
                expanded: true,
                children: childs
            };

            groupNodes.push(groupNode);
        }

        var tree = new Ext.tree.TreePanel({
            title: t('operator_group_' + operatorGroupName),
            iconCls: 'pimcore_icon_gridconfig_operator_' + operatorGroupName,
            xtype: "treepanel",
            region: "south",
            autoScroll: true,
            layout: 'fit',
            rootVisible: false,
            resizeable: true,
            split: true,
            viewConfig: {
                plugins: {
                    ptype: 'treeviewdragdrop',
                    ddGroup: "columnconfigelement",
                    enableDrag: true,
                    enableDrop: false
                }
            },
            root: {
                id: "0",
                root: true,
                text: t("base"),
                draggable: false,
                leaf: false,
                isTarget: false,
                children: groupNodes
            }
        });

        return tree;
    },

    getConfigElement: function (configAttributes) {
        var element = null;
        if (configAttributes && configAttributes.class && configAttributes.type) {
            var jsClass = configAttributes.class.toLowerCase();
            if (pimcore.object.gridcolumn[configAttributes.type] && pimcore.object.gridcolumn[configAttributes.type][jsClass]) {
                element = new pimcore.object.gridcolumn[configAttributes.type][jsClass](this.config.classid);
            }
        } else {
            var dataType = configAttributes.dataType.toLowerCase();
            if (pimcore.object.gridcolumn.value[dataType]) {
                element = new pimcore.object.gridcolumn.value[dataType](this.config.classid);
            } else {
                element = new pimcore.object.gridcolumn.value.defaultvalue(this.config.classid);
            }
        }
        return element;
    }

});
