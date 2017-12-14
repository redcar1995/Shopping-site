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

pimcore.registerNS("pimcore.object.classes.data.date");
pimcore.object.classes.data.date = Class.create(pimcore.object.classes.data.data, {

    type: "date",
    /**
     * define where this datatype is allowed
     */
    allowIn: {
        object: true,
        objectbrick: true,
        fieldcollection: true,
        localizedfield: true,
        classificationstore: true,
        block: true
    },

    initialize: function (treeNode, initData) {
        this.type = "date";

        this.initData(initData);

        this.treeNode = treeNode;
    },

    getTypeName: function () {
        return t("date");
    },

    getGroup: function () {
        return "date";
    },

    getIconClass: function () {
        return "pimcore_icon_date";
    },

    getLayout: function ($super) {


        $super();

        var date = {
            fieldLabel: t("default_value"),
            name: "defaultValue",
            cls: "object_field",
            width: 300,
            disabled: this.datax.useCurrentDate
        };

        if (this.datax.defaultValue) {
            var tmpDate;
            if (typeof this.datax.defaultValue === 'object') {
                tmpDate = this.datax.defaultValue;
            } else {
                tmpDate = new Date(this.datax.defaultValue * 1000);
            }

            date.value = tmpDate;
        }

        this.component = new Ext.form.DateField(date);

        var columnTypeData = [["bigint(20)", "BIGINT"], ["datetime", "DATETIME"]];

        this.columnTypeField = new Ext.form.ComboBox({
            name: "columnType",
            mode: 'local',
            autoSelect: true,
            forceSelection: true,
            editable: false,
            fieldLabel: t("column_type"),
            value: this.datax.columnType ? this.datax.columnType : 'bigint(20)',
            store: new Ext.data.ArrayStore({
                fields: [
                    'id',
                    'label'
                ],
                data: columnTypeData
            }),
            triggerAction: 'all',
            valueField: 'id',
            displayField: 'label'
        });


        this.specificPanel.removeAll();
        this.specificPanel.add([
            this.component,
            {
                xtype: "checkbox",
                fieldLabel: t("use_current_date"),
                name: "useCurrentDate",
                checked: this.datax.useCurrentDate,
                listeners: {
                    change: this.toggleDefaultDate.bind(this)
                },
                disabled: this.isInCustomLayoutEditor()
            }, {
                xtype: "panel",
                bodyStyle: "padding-top: 3px",
                style: "margin-bottom: 10px",
                html: '<span class="object_field_setting_warning">' + t('default_value_warning') + '</span>'
            },
            this.columnTypeField
        ]);

        return this.layout;
    },

    toggleDefaultDate: function (checkbox, checked) {
        if (checked) {
            this.component.setValue(null);
            this.component.setDisabled(true);
        } else {
            this.component.enable();
        }
    },

    applyData: function ($super) {
        $super();
        this.datax.queryColumnType = this.datax.columnType;
    },

    applySpecialData: function (source) {
        if (source.datax) {
            if (!this.datax) {
                this.datax = {};
            }
            Ext.apply(this.datax,
                {
                    defaultValue: source.datax.defaultValue,
                    useCurrentDate: source.datax.useCurrentDate
                });
        }
    }

});
