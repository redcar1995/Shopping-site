/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 * @package    Object
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */


pimcore.registerNS("pimcore.object.importcolumn.operator.phpcode");

pimcore.object.importcolumn.operator.phpcode = Class.create(pimcore.object.gridcolumn.operator.Abstract, {
    type: "operator",
    class: "PHPCode",
    iconCls: "pimcore_icon_operator_phpcode",
    defaultText: "PHP Code",

    getConfigTreeNode: function (configAttributes) {
        if (configAttributes) {
            var nodeLabel = this.getNodeLabel(configAttributes);
            var node = {
                draggable: true,
                iconCls: this.iconCls,
                text: nodeLabel,
                configAttributes: configAttributes,
                isTarget: true,
                expanded: true,
                leaf: false,
                expandable: false
            };
        } else {

            //For building up operator list
            var configAttributes = {type: this.type, class: this.class};

            var node = {
                draggable: true,
                iconCls: this.iconCls,
                text: this.getDefaultText(),
                configAttributes: configAttributes,
                isTarget: true,
                leaf: true,
                isChildAllowed: this.allowChild
            };
        }
        node.isOperator = true;
        return node;
    },


    getCopyNode: function (source) {
        var copy = source.createNode({
            iconCls: this.iconCls,
            text: source.data.text,
            isTarget: true,
            leaf: false,
            expandable: false,
            isOperator: true,
            isChildAllowed: this.allowChild,
            configAttributes: {
                label: source.data.text,
                type: this.type,
                class: this.class
            }
        });

        return copy;
    },


    getConfigDialog: function (node) {
        this.node = node;

        this.textField = new Ext.form.TextField({
            fieldLabel: t('label'),
            length: 255,
            width: 400,
            value: this.node.data.configAttributes.label
        });

        this.phpClassField = new Ext.form.TextField({
            fieldLabel: t('php_class'),
            width: 400,
            value: this.node.data.configAttributes.phpClass
        });

        this.additionalDataField = new Ext.form.TextArea({
            fieldLabel: t('additional_data'),
            width: 400,
            value: this.node.data.configAttributes.additionalData
        });


        this.configPanel = new Ext.Panel({
            layout: "form",
            bodyStyle: "padding: 10px;",
            items: [this.textField, this.phpClassField, this.additionalDataField],
            buttons: [{
                text: t("apply"),
                iconCls: "pimcore_icon_apply",
                handler: function () {
                    this.commitData();
                }.bind(this)
            }]
        });

        this.window = new Ext.Window({
            width: 600,
            height: 300,
            modal: true,
            title: t('operator_phpcode_settings'),
            layout: "fit",
            items: [this.configPanel]
        });

        this.window.show();
        return this.window;
    },

    commitData: function () {
        this.node.data.configAttributes.label = this.textField.getValue();
        this.node.data.configAttributes.phpClass = this.phpClassField.getValue();
        this.node.data.configAttributes.additionalData = this.additionalDataField.getValue();

        var nodeLabel = this.getNodeLabel(this.node.data.configAttributes);
        this.node.set('text', nodeLabel);
        this.node.set('isOperator', true);

        this.window.close();
    },

    getNodeLabel: function(configAttributes) {
        var nodeLabel = configAttributes.label;
        if (configAttributes.locale) {
            nodeLabel += '<span class="pimcore_gridnode_hint"> (' + configAttributes.locale + ')</span>';
        }

        return nodeLabel;
    }
});