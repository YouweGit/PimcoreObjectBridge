/**
 * Adds object bridge under Relations in field selector
 */
pimcore.registerNS("pimcore.object.classes.data.objectBridge");
pimcore.object.classes.data.objectBridge = Class.create(pimcore.object.classes.data.data, {

    type: "objectBridge",
    /**
     * define where this data type is allowed
     */
    allowIn: {
        object: true,
        objectbrick: true,
        fieldcollection: true,
        localizedfield: true,
        classificationstore: false,
        block: true
    },

    initialize: function (treeNode, initData) {
        this.type = "objectBridge";

        this.initData(initData);

        if (typeof this.datax.lazyLoading == "undefined") {
            this.datax.lazyLoading = true;
        }

        if (typeof this.datax.decimalPrecision == "undefined") {
            this.datax.decimalPrecision = 2;
        }

        // overwrite default settings
        this.availableSettingsFields = ["name", "title", "tooltip", "mandatory", "noteditable", "invisible",
            "visibleGridView", "visibleSearch", "style"];

        this.treeNode = treeNode;
    },
    /**
     * @returns {string}
     */
    getGroup: function () {
        return "relation";
    },
    /**
     * @returns {string}
     */
    getTypeName: function () {
        return t("objectBridge");
    },
    /**
     * @returns {string}
     */
    getIconClass: function () {
        return "pimcore_icon_multihref";
    },
    /**
     * @param $super Magic parameter from prototype-js, php like parent::getLayout()
     * @returns {Ext.Panel}
     */
    getLayout: function ($super) {
        $super();

        this.specificPanel.removeAll();

        this.specificPanel.add(this.getStandardColumns());


        this.headerConfigPanel = this.getHeaderConfigPanel();
        this.specificPanel.add(this.headerConfigPanel);

        // Source object
        this.sourceClassCombo = this.getSourceClassCombo();
        this.specificPanel.add(this.sourceClassCombo);

        this.sourceClassFieldStore = this.getSourceClassFieldStore();
        this.sourceClassFieldSelect = this.getSourceClassFieldSelect();
        this.specificPanel.add(this.sourceClassFieldSelect);

        this.sourceHiddenFieldsStore = this.getSourceHiddenFieldsStore();
        this.sourceHiddenFieldsSelect = this.getSourceHiddenFieldsSelect();
        this.specificPanel.add(this.sourceHiddenFieldsSelect);

        // Bridge object
        this.bridgeClassStore = this.getBridgeClassStore();
        this.bridgeClassCombo = this.getBridgeClassCombo();

        this.specificPanel.add(this.bridgeClassCombo);

        this.bridgeClassFieldStore = this.getBridgeClassFieldStore();
        this.bridgeFieldStore = this.getBridgeFieldStore();


        this.bridgeClassFieldSelect = this.getBridgeClassFieldSelect();
        this.specificPanel.add(this.bridgeClassFieldSelect);

        this.bridgeHiddenFieldsStore = this.getBridgeHiddenFieldsStore();
        this.bridgeHiddenFieldsSelect = this.getBridgeHiddenFieldsSelect();
        this.specificPanel.add(this.bridgeHiddenFieldsSelect);

        this.bridgeFieldSelect = this.getBridgeFieldCombo();
        this.specificPanel.add(this.bridgeFieldSelect);

        this.specificPanel.add({
            allowBlank: false,
            minWidth: 500,
            xtype: 'textfield',
            fieldLabel: t('bridge_folder'),
            name: 'bridgeFolder',
            value: this.datax.bridgeFolder
        });

        this.specificPanel.add({
            allowBlank: true,
            minWidth: 500,
            xtype: 'textfield',
            fieldLabel: t('source_prefix'),
            name: 'sourcePrefix',
            value: this.datax.sourcePrefix
        });

        this.specificPanel.add({
            allowBlank: true,
            minWidth: 500,
            xtype: 'textfield',
            fieldLabel: t('bridge_prefix'),
            name: 'bridgePrefix',
            value: this.datax.bridgePrefix
        });
        this.specificPanel.add({
            allowBlank: true,
            minWidth: 500,
            xtype: 'numberfield',
            fieldLabel: t('decimal_precision'),
            name: 'decimalPrecision',
            value: this.datax.decimalPrecision
        });

        this.stores = {};
        this.grids = {};
        // Make sure this gets executed after all objects have been instantiated
        if (this.datax.sourceAllowedClassName) {
            this.sourceClassFieldStore.load({
                name: this.datax.sourceAllowedClassName
            });

            this.sourceHiddenFieldsStore.load({
                name: this.datax.sourceAllowedClassName
            });
        }

        if (this.datax.bridgeAllowedClassName) {
            this.bridgeClassFieldStore.load({name: this.datax.bridgeAllowedClassName});
            this.bridgeHiddenFieldsStore.load({name: this.datax.bridgeAllowedClassName});
            this.bridgeFieldStore.load({name: this.datax.bridgeAllowedClassName});
        }
        return this.layout;
    },
    /**
     * @param $super Magic parameter from prototype-js, php like parent::isValid()
     * @returns {boolean}
     */
    isValid: function ($super) {
        var baseValid = $super();
        // if name is not valid don't bother looping in specific fields
        if (baseValid === false) {
            return baseValid;
        }

        var hasErrors = false;
        // Avoid error in ext getRefItems
        if (
            this.specificPanel &&
            this.specificPanel.monitor &&
            this.specificPanel.monitor.getItems()
        ) {
            this.specificPanel.monitor.getItems().each(function (item) {
                if (item.isValid() === false) {
                    item.markInvalid(t('mandatory_field_empty'));
                    hasErrors = true;
                }
            }, this);
        }

        return (!hasErrors);
    },
    /**
     * @returns {*[]}
     */
    getStandardColumns: function () {
        return [
            {
                xtype: "numberfield",
                fieldLabel: t("width"),
                name: "width",
                value: this.datax.width
            },
            {
                xtype: "numberfield",
                fieldLabel: t("height"),
                name: "height",
                value: this.datax.height
            },
            {
                xtype: "numberfield",
                fieldLabel: t("maximum_items"),
                name: "maxItems",
                value: this.datax.maxItems,
                disabled: this.isInCustomLayoutEditor(),
                minValue: 0
            },
            {
                xtype: "checkbox",
                fieldLabel: t("lazy_loading"),
                name: "lazyLoading",
                checked: this.datax.lazyLoading,
                disabled: this.isInCustomLayoutEditor()
            },
            {
                xtype: "displayfield",
                hideLabel: true,
                value: t('lazy_loading_description'),
                cls: "pimcore_extra_label_bottom",
                style: "padding-bottom:0;"
            },
            {
                xtype: "displayfield",
                hideLabel: true,
                value: t('lazy_loading_warning'),
                cls: "pimcore_extra_label_bottom",
                style: "color:red; font-weight: bold;"
            },
            {
                xtype: "checkbox",
                fieldLabel: t("disable_up_down"),
                name: "disableUpDown",
                checked: this.datax.disableUpDown,
                disabled: this.isInCustomLayoutEditor()
            }
        ];
    },

    getHeaderConfigPanel: function () {
        return [{
            xtype: "checkbox",
            fieldLabel: t("auto_resize"),
            name: "autoResize",
            checked: this.datax.autoResize
        }, {
            xtype: "checkbox",
            fieldLabel: t("new_line_split"),
            name: "newLineSplit",
            checked: this.datax.newLineSplit
        }, {
            xtype: 'numberfield',
            fieldLabel: t("max_width_resize"),
            name: 'maxWidthResize',
            value: this.datax.maxWidthResize
        }, {
            xtype: "checkbox",
            fieldLabel: t("allow_create"),
            name: "allowCreate",
            checked: this.datax.allowCreate
        }, {
            xtype: "checkbox",
            fieldLabel: t("allow_delete"),
            name: "allowDelete",
            checked: this.datax.allowDelete
        }
        ];
    },

    /**
     * @returns {Ext.form.ComboBox}
     */
    getSourceClassCombo: function () {
        return Ext.create('Ext.form.ComboBox', {
            allowBlank: false,
            minWidth: 500,
            typeAhead: true,
            triggerAction: 'all',
            store: pimcore.globalmanager.get('object_types_store'),
            valueField: 'text',
            editable: true,
            queryMode: 'local',
            mode: 'local',
            anyMatch: true,
            displayField: 'text',
            fieldLabel: t('source_allowed_class'),
            name: 'sourceAllowedClassName',
            value: this.datax.sourceAllowedClassName,
            forceSelection: true,
            listeners: {
                select: function (combo, record, index) {

                    this.datax.sourceAllowedClassName = record.data.text;
                    if (this.datax.sourceAllowedClassName) {

                        this.sourceClassFieldSelect.clearValue();
                        this.bridgeClassCombo.clearValue();
                        this.bridgeClassFieldSelect.clearValue();
                        this.sourceHiddenFieldsSelect.clearValue();
                        this.bridgeFieldSelect.clearValue();
                        this.bridgeHiddenFieldsSelect.clearValue();

                        this.sourceClassFieldSelect.store.load({
                            params: {
                                name: this.datax.sourceAllowedClassName
                            },
                            callback: function () {
                                this.bridgeClassCombo.setDisabled(false);

                            }.bind(this)
                        });
                    } else {
                        this.sourceClassFieldSelect.setDisabled(true);
                        this.bridgeClassCombo.setDisabled(true);
                        this.bridgeClassFieldSelect.setDisabled(true);
                        this.bridgeFieldSelect.setDisabled(true);
                    }
                }.bind(this)
            }
        });
    },
    /**
     * @returns {Ext.data.Store}
     */
    getSourceClassFieldStore: function () {
        return new Ext.data.Store({
            proxy: {
                type: 'ajax',
                url: '/admin/object-helper/grid-get-column-config',
                extraParams: {
                    no_brick_columns: "true",
                    gridtype: 'all',
                    name: this.datax.sourceAllowedClassName
                },
                reader: {
                    type: 'json',
                    rootProperty: 'availableFields'
                }
            },
            fields: ['key', 'label'],
            autoLoad: false,
            listeners: {
                load: function () {
                    this.sourceClassFieldSelect.setDisabled(false);
                    this.bridgeClassCombo.setDisabled(false);
                    this.sourceHiddenFieldsSelect.setDisabled(false);
                }.bind(this)
            }
        });
    },
    /**
     * @returns {Ext.form.field.Tag}
     */
    getSourceClassFieldSelect: function () {
        return new Ext.form.field.Tag({
            queryMode: 'local',
            disabled: true,
            allowBlank: false,
            minWidth: 500,
            name: 'sourceVisibleFields',
            triggerAction: 'all',
            forceSelection: true,
            editable: true,
            fieldLabel: t('source_visible_fields'),
            store: this.sourceClassFieldStore,
            value: this.datax.sourceVisibleFields,
            displayField: 'key',
            valueField: 'key',
            listeners: {
                select: function (field, records) {
                    this.sourceHiddenFieldsSelect.clearValue();
                    this.sourceHiddenFieldsSelect.getStore().loadData(records, false);
                }.bind(this),
                change: function (field, value, oldValue) {
                    this.datax.sourceVisibleFields = value;
                }.bind(this)
            }
        });
    },
    /**
     * @returns {Ext.data.Store}
     */
    getSourceHiddenFieldsStore: function () {
        return new Ext.data.Store({
            proxy: {
                type: 'ajax',
                url: '/admin/object-helper/grid-get-column-config',
                extraParams: {
                    no_brick_columns: "true",
                    gridtype: 'all',
                    name: this.datax.sourceAllowedClassName
                },
                reader: {
                    type: 'json',
                    rootProperty: 'availableFields'
                }
            },
            fields: ['key', 'label'],
            autoLoad: false,
            listeners: {
                load: function () {
                    this.sourceHiddenFieldsSelect.setDisabled(false);
                }.bind(this)
            }
        });
    },

    /**
     * @returns {Ext.form.field.Tag}
     */
    getSourceHiddenFieldsSelect: function () {
        return new Ext.form.field.Tag({
            queryMode: 'local',
            disabled: true,
            allowBlank: true,
            minWidth: 500,
            name: 'sourceHiddenFields',
            mode: 'local',
            forceSelection: false,
            triggerAction: 'all',
            editable: true,
            fieldLabel: t('source_hidden_fields'),
            store: this.sourceHiddenFieldsStore,
            value: this.datax.sourceHiddenFields,
            displayField: 'key',
            valueField: 'key',
            change: function (field, value, oldValue) {
                this.datax.sourceHiddenFields = value;
            }.bind(this)
        });
    },
    // Don't use object_types_store because it will filter source class
    getBridgeClassStore: function () {
        return new Ext.data.Store({
            model: 'pimcore.model.objecttypes',
            autoLoad: true
        })
    },
    /**
     * @returns {Ext.form.ComboBox}
     */
    getBridgeClassCombo: function () {
        return Ext.create('Ext.form.ComboBox', {
            disabled: true,
            allowBlank: false,
            minWidth: 500,
            typeAhead: true,
            triggerAction: 'all',
            store: this.bridgeClassStore,
            valueField: 'text',
            editable: true,
            displayField: 'text',
            queryMode: 'local',
            mode: 'local',
            anyMatch: true,
            fieldLabel: t('bridge_allowed_class'),
            name: 'bridgeAllowedClassName',
            value: this.datax.bridgeAllowedClassName,
            forceSelection: true,
            listeners: {
                select: function (combo, record, index) {
                    this.datax.bridgeAllowedClassName = record.data.text;
                    if (this.datax.bridgeAllowedClassName) {

                        this.bridgeClassFieldSelect.clearValue();
                        this.bridgeFieldSelect.clearValue();
                        this.bridgeHiddenFieldsSelect.clearValue();

                        this.bridgeClassFieldStore.load({
                            params: {
                                name: this.datax.bridgeAllowedClassName
                            }
                        });

                        this.bridgeFieldStore.load({
                            params: {
                                name: this.datax.bridgeAllowedClassName
                            }
                        });
                    } else {
                        this.bridgeClassFieldSelect.setDisabled(true);
                        this.bridgeFieldSelect.setDisabled(true);
                    }
                }.bind(this)
            }
        });
    },

    /**
     * @returns {Ext.data.Store}
     */
    getBridgeClassFieldStore: function () {
        return new Ext.data.Store({
            proxy: {
                type: 'ajax',
                url: '/admin/object-helper/grid-get-column-config',
                extraParams: {
                    no_brick_columns: 'true',
                    gridtype: 'all',
                    name: this.datax.bridgeAllowedClassName
                },
                reader: {
                    type: 'json',
                    rootProperty: "availableFields"
                }
            },
            fields: ['key', 'label'],
            autoLoad: false,
            forceSelection: true,
            listeners: {
                load: function () {
                    this.bridgeClassFieldSelect.setDisabled(false);
                    this.bridgeHiddenFieldsSelect.setDisabled(false);
                }.bind(this)
            }
        });
    },
    /**
     * @returns {Ext.form.field.Tag}
     */
    getBridgeClassFieldSelect: function () {
        return new Ext.form.field.Tag({
            queryMode: 'local',
            disabled: true,
            allowBlank: false,
            minWidth: 500,
            name: 'bridgeVisibleFields',
            triggerAction: 'all',
            forceSelection: true,
            editable: true,
            fieldLabel: t('bridge_visible_fields'),
            store: this.bridgeClassFieldStore,
            value: this.datax.bridgeVisibleFields,
            displayField: 'key',
            valueField: 'key',
            listeners: {
                select: function (field, records) {
                    this.bridgeHiddenFieldsSelect.clearValue();
                    this.bridgeHiddenFieldsSelect.getStore().loadData(records, false);
                }.bind(this),
                change: function (field, value, oldValue) {
                    this.datax.bridgeVisibleFields = value;
                }.bind(this)
            }
        });
    },

    /**
     * @returns {Ext.data.Store}
     */
    getBridgeHiddenFieldsStore: function () {
        return new Ext.data.Store({
            proxy: {
                type: 'ajax',
                url: '/admin/object-helper/grid-get-column-config',
                extraParams: {
                    no_brick_columns: "true",
                    gridtype: 'all',
                    name: this.datax.bridgeAllowedClassName
                },
                reader: {
                    type: 'json',
                    rootProperty: 'availableFields'
                }
            },
            fields: ['key', 'label'],
            autoLoad: false,
            listeners: {
                load: function () {
                    this.bridgeHiddenFieldsSelect.setDisabled(false);
                }.bind(this)
            }
        });
    },
    /**
     * @returns {Ext.form.field.Tag}
     */
    getBridgeHiddenFieldsSelect: function () {
        return new Ext.form.field.Tag({
            queryMode: 'local',
            disabled: true,
            allowBlank: true,
            minWidth: 500,
            name: 'bridgeHiddenFields',
            mode: 'local',
            forceSelection: false,
            triggerAction: 'all',
            editable: true,
            fieldLabel: t('bridge_hidden_fields'),
            store: this.bridgeHiddenFieldsStore,
            value: this.datax.bridgeHiddenFields,
            displayField: 'key',
            valueField: 'key',
            change: function (field, value, oldValue) {
                this.datax.bridgeHiddenFields = value;
            }.bind(this)
        });
    },


    /**
     * @returns {Ext.data.Store}
     */
    getBridgeFieldStore: function () {
        return new Ext.data.Store({
            proxy: {
                type: 'ajax',
                url: '/admin/object-helper/grid-get-column-config',
                extraParams: {
                    types: 'manyToOneRelation',
                    no_system_columns: true,
                    name: this.datax.bridgeAllowedClassName
                },
                reader: {
                    type: 'json',
                    rootProperty: 'availableFields'
                }
            },
            fields: ['key', 'label'],
            autoLoad: false,
            forceSelection: true,
            listeners: {
                load: function () {
                    var store = this.bridgeFieldSelect.store;

                    store.data.each(function (record) {
                        var objStore = pimcore.globalmanager.get('object_types_store');
                        var sourceClassDef = objStore.findRecord('text', this.datax.sourceAllowedClassName);
                        // Remove all fields href's that are not linked to source object
                        if (
                            !record.data.layout.classes || !record.data.layout.classes[0] || !sourceClassDef || !sourceClassDef.data ||
                            record.data.layout.classes[0].classes !== sourceClassDef.data.text
                        ) {
                            store.remove(record);
                        }
                    }, this);
                    this.bridgeFieldSelect.setDisabled(false);
                }.bind(this)
            }
        });
    },
    /**
     * @returns {Ext.form.field.ComboBox}
     */
    getBridgeFieldCombo: function () {
        return new Ext.form.ComboBox({
            queryMode: 'local',
            disabled: true,
            allowBlank: false,
            minWidth: 500,
            typeAhead: true,
            triggerAction: 'all',
            store: this.bridgeFieldStore,
            displayField: 'key',
            valueField: 'key',
            editable: true,
            fieldLabel: t('bridge_field'),
            name: 'bridgeField',
            value: this.datax.bridgeField,
            forceSelection: false,
            change: function (field, value, oldValue) {
                this.datax.bridgeField = value;
            }.bind(this)
        });
    },
    /**
     * Sets defaults
     * @param source
     */
    applySpecialData: function (source) {
        if (source.datax) {
            if (!this.datax) {
                this.datax = {};
            }
            Ext.apply(this.datax, {
                width: source.datax.width,
                height: source.datax.height,
                maxItems: source.datax.maxItems,
                relationType: source.datax.relationType,
                autoResize: source.datax.autoResize,
                maxWidthResize: source.datax.maxWidthResize,
                newLineSplit: source.datax.newLineSplit,
                sourceAllowedClassName: source.datax.sourceAllowedClassName,
                sourceVisibleFields: source.datax.sourceVisibleFields,
                sourceHiddenFields: source.datax.sourceHiddenFields,
                bridgeAllowedClassName: source.datax.bridgeAllowedClassName,
                bridgeVisibleFields: source.datax.bridgeVisibleFields,
                bridgeHiddenFields: source.datax.bridgeHiddenFields,
                bridgeField: source.datax.bridgeField,
                bridgeFolder: source.datax.bridgeFolder,
                allowCreate: source.datax.allowCreate,
                allowDelete: source.datax.allowDelete,
                decimalPrecision: source.datax.decimalPrecision,
                sourcePrefix: source.datax.sourcePrefix,
                bridgePrefix: source.datax.bridgePrefix,
                remoteOwner: source.datax.remoteOwner,
                lazyLoading: source.datax.lazyLoading,
                disableUpDown: source.datax.disableUpDown,
                classes: source.datax.classes
            });
        }
    }

});
