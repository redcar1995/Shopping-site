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

pimcore.registerNS("pimcore.settings.robotstxt");
pimcore.settings.robotstxt = Class.create({

    initialize: function(id) {

        this.site = "";
        this.data = {data: ""};

        this.getTabPanel();
        this.load();
    },

    load: function () {
        Ext.Ajax.request({
            url: "/admin/settings/robots-txt",
            params: {
                site: this.site
            },
            success: function (response) {

                try {
                    var data = Ext.decode(response.responseText);
                    if(data.success && this.editArea instanceof Ext.form.TextArea) {
                        this.data = data;
                        this.editArea.setValue(this.data.data);
                    }
                } catch (e) {

                }
            }.bind(this)
        });
    },

    activate: function () {
        var tabPanel = Ext.getCmp("pimcore_panel_tabs");
        tabPanel.setActiveItem("pimcore_robotstxt");
    },

    getTabPanel: function () {

        if (!this.panel) {
            this.panel = new Ext.Panel({
                id: "pimcore_robotstxt",
                title: "robots.txt",
                iconCls: "pimcore_icon_robots",
                border: false,
                layout: "fit",
                closable:true,
                items: [this.getEditPanel()]
            });

            var tabPanel = Ext.getCmp("pimcore_panel_tabs");
            tabPanel.add(this.panel);
            tabPanel.setActiveItem("pimcore_robotstxt");


            this.panel.on("destroy", function () {
                pimcore.globalmanager.remove("robotstxt");
            }.bind(this));

            pimcore.layout.refresh();
        }

        return this.panel;
    },

    getEditPanel: function () {

        if (!this.editPanel) {

            if(this.data.onFileSystem) {
                this.editArea = new Ext.Panel({
                    bodyStyle: "padding:50px;",
                    html: t("robots_txt_exists_on_filesystem")
                });
            } else {
                this.editArea = new Ext.form.TextArea({
                    xtype: "textarea",
                    name: "data",
                    value: this.data.data,
                    width: "100%",
                    height: "100%",
                    style: "font-family: 'Courier New', Courier, monospace;"
                });
            }

            this.editPanel = new Ext.Panel({
                bodyStyle: "padding: 10px;",
                items: [this.editArea],
                tbar: ["->", {
                    xtype: 'tbtext',
                    text: t("select_site")
                }, {
                    xtype: "combo",
                    store: pimcore.globalmanager.get("sites"),
                    valueField: "id",
                    displayField: "domain",
                    triggerAction: "all",
                    editable: false,
                    listeners: {
                        "select": function (el) {
                            this.site = el.getValue();
                            this.load();
                        }.bind(this)
                    }
                }],
                buttons: [{
                    text: t("save"),
                    iconCls: "pimcore_icon_apply",
                    handler: this.save.bind(this)
                }]
            });
            this.editPanel.on("bodyresize", function (el, width, height) {
                this.editArea.setWidth(width-20);
                this.editArea.setHeight(height-20);
            }.bind(this));
        }

        return this.editPanel;
    },


    save : function () {

        Ext.Ajax.request({
            url: "/admin/settings/robots-txt",
            method: "PUT",
            params: {
                data: this.editArea.getValue(),
                site: this.site
            },
            success: function (response) {

                try {
                    var data = Ext.decode(response.responseText);
                    if(data.success) {
                        pimcore.helpers.showNotification(t("success"), t("saved_successfully"), "success");
                    } else {
                        throw "save error";
                    }
                } catch (e) {
                    pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
                }
            }.bind(this)
        });
    }
});

