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

pimcore.registerNS("pimcore.element.dependencies");
pimcore.element.dependencies = Class.create({

    initialize: function(element, type) {
        this.element = element;
        this.type = type;
        this.requiresLoaded = false;
        this.requiredByLoaded = false;
    },

    getLayout: function() {
        
        this.requiresPanel = new Ext.Panel({
            flex: 1,
            layout: "fit"
        });
        
        this.requiredByPanel = new Ext.Panel({
            flex: 1,
            layout: "fit"
        });
        
        if (this.layout == null) {
            this.layout = new Ext.Panel({
                tabConfig: {
                    tooltip: t('dependencies')
                },
                border: false,
                scrollable: "y",
                iconCls: "pimcore_icon_dependencies",
                listeners:{
                    activate: this.getData.bind(this)
                }
            });
        }
        return this.layout;
    },

    waitForLoaded: function() {
        if (this.requiredByLoaded && this.requiresLoaded) {
            this.completeLoad();
        } else {
            window.setTimeout(this.waitForLoaded.bind(this), 1000);
        }
    },

    completeLoad: function() {
        
        this.layout.add(this.requiresNote);
        this.layout.add(this.requiresGrid);
        
        this.layout.add(this.requiredByNote);
        this.layout.add(this.requiredByGrid);
        
        this.layout.updateLayout();
    },


    getData: function() {
        
        // only load it once
        if(this.requiresLoaded && this.requiredByLoaded) {
            return;
        }
        
        
        Ext.Ajax.request({
            url: '/admin/element/get-requires-dependencies',
            params: {
                id: this.element.id,
                elementType: this.type
            },
            success: this.getRequiresLayout.bind(this)
        });
        
        Ext.Ajax.request({
            url: '/admin/element/get-required-by-dependencies',
            params: {
                id: this.element.id,
                elementType: this.type
            },
            success: this.getRequiredByLayout.bind(this)
        });


        this.waitForLoaded();
    },
        
    getRequiresLayout: function(response) {
        if (response != null) {
            this.requiresData = Ext.decode(response.responseText);
        }
                
        
        this.requiresStore = new Ext.data.JsonStore({
            autoDestroy: true,
            data: this.requiresData,
            fields: ['id', 'path', 'type', 'subtype'],
            proxy: {
                type: 'memory',
                reader: {
                    type: 'json',
                    rootProperty: 'requires'
                }
            }
        });                

        this.requiresGrid = new Ext.grid.GridPanel({
            store: this.requiresStore,
            columns: [
                {text: "ID", sortable: true, dataIndex: 'id'},
                {text: t("path"), sortable: true, dataIndex: 'path', flex: 1},
                {text: t("type"), sortable: true, dataIndex: 'type'},
                {text: t("subtype"), sortable: true, dataIndex: 'subtype'}
            ],
            collapsible: true,
            columnLines: true,
            stripeRows: true,
            autoHeight: true,              
            title: t('requires'),
            viewConfig: {
                forceFit: true
            },
            style: "margin-bottom: 30px;"
        });
        this.requiresGrid.on("rowclick", this.click.bind(this));
        
        
        this.requiresNote = new Ext.Panel({
            html:t('hidden_dependencies'),
            cls:'dependency-warning',
            border:false,
            hidden: !this.requiresData.hasHidden                        
        });
        
        
        
        this.requiresLoaded = true;        
    },
    getRequiredByLayout: function(response) {
        if (response != null) {
            this.requiredByData = Ext.decode(response.responseText);
        }
                
        this.requiredByStore = new Ext.data.JsonStore({
            autoDestroy: true,
            data: this.requiredByData,
            fields: ['id', 'path', 'type', 'subtype'],
            proxy: {
                type: 'memory',
                reader: {
                    type: 'json',
                    rootProperty: 'requiredBy'
                }
            }
        });
                                
        this.requiredByGrid = Ext.create('Ext.grid.Panel', {
            store: this.requiredByStore,
            columns: [
                {text: "ID", sortable: true, dataIndex: 'id'},
                {text: t("path"), sortable: true, dataIndex: 'path', flex: 1},
                {text: t("type"), sortable: true, dataIndex: 'type'},
                {text: t("subtype"), sortable: true, dataIndex: 'subtype'}
            ],
            collapsible: true,
            autoExpandColumn: "path",
            columnLines: true,
            stripeRows: true,
            autoHeight: true,
            title: t('required_by'),
            viewConfig: {
                forceFit: true
            }
        });
        this.requiredByGrid.on("rowclick", this.click.bind(this));
        
        
        
        this.requiredByNote = new Ext.Panel({
            html:t('hidden_dependencies'),
            cls:'dependency-warning',
            border:false,
            hidden: !this.requiredByData.hasHidden                        
        });
        
    
        this.requiredByLoaded = true;        
    },

    click: function ( grid, record, tr, rowIndex, e, eOpts ) {
        
        var d = record.data;

        if (d.type == "object") {
            pimcore.helpers.openObject(d.id, d.subtype);
        }
        else if (d.type == "asset") {
            pimcore.helpers.openAsset(d.id, d.subtype);
        }
        else if (d.type == "document") {
            pimcore.helpers.openDocument(d.id, d.subtype);
        }
    }

});