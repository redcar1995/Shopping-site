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

pimcore.registerNS("pimcore.object.classes.data.table");
pimcore.object.classes.data.table = Class.create(pimcore.object.classes.data.data, {

    type: "table",
    /**
     * define where this datatype is allowed
     */
    allowIn: {
        object: true,
        objectbrick: true,
        fieldcollection: true,
        localizedfield: true,
        classificationstore : true,
        block: true,
        encryptedField: true
    },

    initialize: function (treeNode, initData) {
        this.type = "table";

        this.initData(initData);

        // overwrite default settings
        this.availableSettingsFields = ["name","title","tooltip","mandatory","noteditable","invisible",
                                        "visibleGridView","visibleSearch","style"];

        this.treeNode = treeNode;
    },

    getGroup: function () {
            return "structured";
    },

    getTypeName: function () {
        return t("table");
    },

    getIconClass: function () {
        return "pimcore_icon_table";
    },

    getLayout: function ($super) {

        $super();

        this.specificPanel.removeAll();
        var specificItems = this.getSpecificPanelItems(this.datax);
        this.specificPanel.add(specificItems);

        return this.layout;
    },

    getSpecificPanelItems: function (datax, inEncryptedField) {
        return [
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
                fieldLabel: t("rows"),
                name: "rows",
                value: datax.rows,
                disabled: this.isInCustomLayoutEditor()
            },
            {
                xtype: "checkbox",
                fieldLabel: t("rows_fixed"),
                name: "rowsFixed",
                checked: datax.rowsFixed,
                disabled: this.isInCustomLayoutEditor()
            },
            {
                xtype: "numberfield",
                fieldLabel: t("cols"),
                name: "cols",
                value: datax.cols,
                disabled: this.isInCustomLayoutEditor()
            },
            {
                xtype: "checkbox",
                fieldLabel: t("cols_fixed"),
                name: "colsFixed",
                checked: datax.colsFixed,
                disabled: this.isInCustomLayoutEditor()
            },
            {
                xtype: "textarea",
                fieldLabel: t("data"),
                name: "data",
                width: 500,
                height: 300,
                value: datax.data,
                disabled: this.isInCustomLayoutEditor()
            }
        ];
    },

    applySpecialData: function(source) {
        if (source.datax) {
            if (!this.datax) {
                this.datax =  {};
            }
            Ext.apply(this.datax,
                {
                    width: source.datax.width,
                    height: source.datax.height,
                    cols: source.datax.cols,
                    colsFixed: source.datax.colsFixed,
                    rows: source.datax.rows,
                    rowsFixed: source.datax.rowsFixed,
                    data: source.datax.data
                });
        }
    }

});
