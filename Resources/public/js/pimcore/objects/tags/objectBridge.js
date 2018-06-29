// This is not a dummy declaration but more like a skeleton for this.store
// Fields and idProperty will be added in initialize
// Ext.define(

pimcore.registerNS("pimcore.object.tags.objectBridge");
pimcore.object.tags.objectBridge = Class.create(pimcore.object.tags.objects, {

    type: "objectBridge",
    dataChanged: false,

    initialize: function (data, fieldConfig) {

        this.data = [];
        this.fieldConfig = fieldConfig;
        var classStore = pimcore.globalmanager.get("object_types_store");

        this.sourceClass = classStore.findRecord('text', fieldConfig.sourceAllowedClassName);
        // this.bridgeClass = classStore.findRecord('text', fieldConfig.bridgeAllowedClassName);

        this.sourceClassName = fieldConfig.sourceAllowedClassName;
        this.bridgeClassName = fieldConfig.bridgeAllowedClassName;


        // Leave classes as is because it is used by create and search
        this.fieldConfig.classes = [{classes: this.sourceClassName, id: this.sourceClass.data.id}];

        if (data) {
            this.data = data;
        }

        var fields = [];

        fields.push({
            name: this.sourceClassName + '_id',
            critical: true,
        });
        fields.push({
            name: this.bridgeClassName + '_id',
            critical: true,
        });

        fields.push('inheritedFields');

        var sourceVisibleFields = this.getSourceVisibleFieldsAsArray();

        var i;

        for (i = 0; i < sourceVisibleFields.length; i++) {
            fields.push(this.sourceClassName + '_' + sourceVisibleFields[i]);
        }

        var bridgeVisibleFields = this.getBridgeVisibleFieldsAsArray();

        for (i = 0; i < bridgeVisibleFields.length; i++) {
            fields.push(this.bridgeClassName + '_' + bridgeVisibleFields[i]);
        }

        this.store = Ext.create('Ext.data.JsonStore', {

            model: Ext.create('Ext.data.Model', {
                idProperty: this.sourceClassName + '_id',
                fields: fields,
                proxy: {
                    type: 'memory',
                    reader: 'json'
                }
            }),
            autoDestroy: true,
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
                update: function () {
                    this.dataChanged = true;
                }.bind(this)
            }
        });
    },


    /**
     * Makes field editable if it should be editable
     *
     * @param {string} classNameText
     * @param {{name: string, title:string, fieldtype: string, readOnly: boolean, mandatory:boolean, hidden:boolean, options:array }} layout
     * ex Object {name: "brand", title: "Brand", fieldtype: "href", readOnly: true, allowBlank: true}
     * @param {bool} readOnly, true if user has no permission to edit current object, will overrule layout.readOnly
     * @returns {Ext.grid.column.Column}
     */
    getColumnFromLayout: function (classNameText, layout, readOnly, prefix) {
        var editor = null,
            renderer = null,
            minWidth = 40;

        readOnly = (readOnly || layout.readOnly);
        prefix = ((prefix + ' ') || (''));

        if (layout.fieldtype === "input" && !readOnly) {
            editor = {
                xtype: 'textfield',
                allowBlank: !layout.mandatory
            };
            renderer = this.renderWithValidation;
        }
        else if (layout.fieldtype === "numeric" && !readOnly) {
            renderer = this.renderWithValidation;
            var decimalPrecision = Ext.isNumeric(this.fieldConfig.decimalPrecision) ? this.fieldConfig.decimalPrecision : 2;
            editor = {
                xtype: 'numberfield',
                decimalPrecision: decimalPrecision,
                allowBlank: !layout.mandatory
            };
        }
        else if (layout.fieldtype === "checkbox") {
            // There seems to be a problem with Ext checkbox column
            // As a workaround we will skip the composition of the element and return
            // it as soon as possible meaning all general stuff on bottom of
            // this function will be passed directly to this column

            var checkBoxColumn = Ext.create('Ext.grid.column.Check', {
                text: layout.title,
                width: 40,
                align: 'left',
                hidden: !!layout.hidden,
                sortable: true,
                dataIndex: classNameText + '_' + layout.name
            });

            if (readOnly) {
                checkBoxColumn.setDisabled(true);
            }
            return checkBoxColumn;
        }

        else if (layout.fieldtype === "select" && !readOnly) {
            renderer = this.renderDisplayField;
            editor = Ext.create('Ext.form.ComboBox', {
                allowBlank: !layout.mandatory,
                typeAhead: true,
                forceSelection: true,
                mode: 'local',
                queryMode: 'local',
                valueField: 'value',
                displayField: 'key',
                anyMatch: true,
                store: Ext.create('Ext.data.JsonStore', {
                    proxy: {
                        type: 'memory',
                        reader: 'json'
                    },
                    idProperty: 'value',
                    fields: ['value', 'key'],
                    data: layout.options
                })
            });
        } else if ((layout.fieldtype === "href" || layout.fieldtype === "hrefTypeahead") && !readOnly) {
            renderer = this.renderHrefWithValidation;
            minWidth = 200;
            editor = Ext.create('Ext.form.ComboBox', {
                allowBlank: !layout.mandatory,
                typeAhead: true,
                forceSelection: false,
                minChars: 2,
                hideTrigger: true,
                mode: 'remote',
                queryMode: 'remote',
                valueField: 'id',
                displayField: 'display',
                enableKeyEvents: true,
                onFocus: function(e) {
                    var me = this;
                    me.setValue(null);
                },
                listeners : {
                    keyup: function (e) {
                        var pendingOperations = this.getStore().getProxy().pendingOperations;
                        Ext.Object.eachValue(pendingOperations, function (pendingOperation) {
                            pendingOperation.abort();
                        });
                    }
                },
                store: Ext.create('Ext.data.JsonStore', {
                    autoLoad: false,
                    remoteSort: true,
                    pageSize: 10,
                    proxy: {
                        type: 'ajax',
                        //@TODO Change after plugin has been converter
                        url: '/plugin/PimcoreHrefTypeahead/search/find',
                        reader: {
                            type: 'json',
                            rootProperty: 'data'
                        },
                        extraParams: {
                            fieldName: layout.name,
                            className: classNameText
                        }
                    },
                    fields: ['id', 'dest_id', 'display', 'type', 'subtype', 'path', 'fullpath']
                })
            });
        } else if(layout.fieldtype === "date" && !readOnly) {
            renderer = this.renderDate,
                editor = {
                    xtype: 'datefield',
                    format: 'm/d/Y',
                    allowBlank: !layout.mandatory
                };

        }
        else {
            // Ext.log(layout.fieldtype + ' is not implemented and will be read only');
        }

        //Bug fix for different title sizes (https://github.com/YouweGit/PimcoreObjectBridge/issues/8) still BC
        var title = "";
        if(prefix.length > 1){
            title = prefix + '<br/>' + layout.title;
        }else{
            title = layout.title;
        }

        var column = Ext.create('Ext.grid.column.Column', {
            text: title,
            dataIndex: classNameText + '_' + layout.name,
            editor: editor,
            renderer: renderer,
            sortable: true,
            minWidth: minWidth
        });

        column.hidden = layout.hidden;
        return column;
    },

    createLayout: function (readOnly) {
        var autoHeight = false;
        if (intval(this.fieldConfig.height) < 15) {
            autoHeight = true;
        }

        var cls = 'object_field';
        var i;

        var sourceVisibleFields = this.getSourceVisibleFieldsAsArray();
        var bridgeVisibleFields = this.getBridgeVisibleFieldsAsArray();

        var columns = [];

        // Make id visible only if specifically selected
        var sourceIdCol = {text: 'Source ID', dataIndex: this.sourceClassName + '_id', width: 50, hidden: true};
        var bridgeIdCol = {text: 'Bridge ID', dataIndex: this.bridgeClassName + '_id', width: 50, hidden: true};

        if (in_array('id', sourceVisibleFields)) {
            sourceIdCol.hidden = false;
            // Delete it from array because we already added the column
            delete sourceVisibleFields[sourceVisibleFields.indexOf('id')];
        }

        if (in_array('id', bridgeVisibleFields)) {
            bridgeIdCol.hidden = false;
            // Delete it from array because we already added the column
            delete bridgeVisibleFields[bridgeVisibleFields.indexOf('id')];
        }

        columns.push(sourceIdCol);
        columns.push(bridgeIdCol);

        for (i = 0; i < sourceVisibleFields.length; i++) {
            if (!empty(sourceVisibleFields[i])) {
                var sourceFieldLayout = this.fieldConfig.sourceVisibleFieldDefinitions[sourceVisibleFields[i]];
                if (!sourceFieldLayout) {
                    throw new Error(sourceVisibleFields[i] + ' is missing from field definition, please add it under enrichLayoutDefinition at Pimcore\\Model\\Object\\ClassDefinition\\Data\\ObjectBridge');
                }
                columns.push(this.getColumnFromLayout(this.sourceClassName, sourceFieldLayout, true, this.fieldConfig.sourcePrefix));
            }
        }

        for (i = 0; i < bridgeVisibleFields.length; i++) {
            if (!empty(bridgeVisibleFields[i])) {
                var bridgeFieldLayout = this.fieldConfig.bridgeVisibleFieldDefinitions[bridgeVisibleFields[i]];
                if (!bridgeFieldLayout) {
                    throw new Error(bridgeVisibleFields[i] + ' is missing from field definition, please add it under enrichLayoutDefinition at Pimcore\\Model\\Object\\ClassDefinition\\Data\\ObjectBridge');
                }
                var column = this.getColumnFromLayout(this.bridgeClassName, bridgeFieldLayout, readOnly, this.fieldConfig.bridgePrefix);
                columns.push(column);
            }
        }

        if (!readOnly) {
            if(!this.fieldConfig.disableUpDown) {
                columns.push({
                    xtype: 'actioncolumn',
                    width: 40,
                    items: [
                        {
                            tooltip: t('up'),
                            icon: '/pimcore/static6/img/flat-color-icons/up.svg',
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
                    width: 40,
                    items: [
                        {
                            tooltip: t('down'),
                            icon: '/pimcore/static6/img/flat-color-icons/down.svg',
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
        }

        columns.push({
            xtype: 'actioncolumn',
            width: 40,
            items: [
                {
                    tooltip: t('open'),
                    icon: '/pimcore/static6/img/flat-color-icons/cursor.svg',
                    handler: function (grid, rowIndex) {
                        var data = grid.getStore().getAt(rowIndex);
                        pimcore.helpers.openObject(data.data[this.sourceClassName + '_id'], 'object');
                    }.bind(this)
                }
            ]
        });

        if (!readOnly && this.fieldConfig.allowDelete) {
            columns.push({
                xtype: 'actioncolumn',
                width: 40,
                items: [
                    {
                        tooltip: t('remove'),
                        icon: '/pimcore/static6/img/flat-color-icons/delete.svg',
                        handler: function (grid, rowIndex) {
                            grid.getStore().removeAt(rowIndex);
                        }.bind(this)
                    }
                ]
            });
        }

        var tbarItems = [
            {
                xtype: 'tbspacer',
                width: 20,
                height: 16,
                cls: 'pimcore_icon_droptarget'
            },
            {
                xtype: "tbtext",
                text: "<b>" + this.fieldConfig.title + "</b>"
            }];

        if (!readOnly) {
            tbarItems = tbarItems.concat([
                "->",
                {
                    xtype: "button",
                    iconCls: "pimcore_icon_search",
                    handler: this.openSearchEditor.bind(this)
                }
            ]);
            if (this.fieldConfig.allowDelete) {
                tbarItems.push({
                    xtype: "button",
                    iconCls: "pimcore_icon_delete",
                    handler: this.empty.bind(this)
                })
            }

            if (this.fieldConfig.allowCreate) {
                tbarItems.push(this.getCreateControl())
            }
        }

        var plugins = [
            Ext.create('Ext.grid.plugin.CellEditing', {
                clicksToEdit: 1
            }),'pimcore.gridfilters'
        ];

        this.component = Ext.create('Ext.grid.Panel', {
            store: this.store,
            border: true,
            style: "margin-bottom: 10px",
            enableDragDrop: true,
            ddGroup: 'element',
            trackMouseOver: true,
            selModel: Ext.create('Ext.selection.RowModel', {}),
            columnLines: true,
            stripeRows: true,
            columns: columns,
            viewConfig: {
                markDirty: false,
                forceFit: true,
                listeners: {}
            },
            componentCls: cls,
            width: this.fieldConfig.width,
            height: this.fieldConfig.height,
            tbar: {
                items: tbarItems,
                ctCls: "pimcore_force_auto_width",
                cls: "pimcore_force_auto_width"
            },
            autoHeight: autoHeight,
            bodyCls: "pimcore_object_tag_objects pimcore_editable_grid object_bridge_grid",
            plugins: plugins
        });

        if (!readOnly) {
            this.component.on("cellcontextmenu", this.onCellContextMenu.bind(this));
        }
        if (this.fieldConfig.autoResize) {
            this.component.view.addListener('refresh', this.onRefreshComponent.bind(this));
        }

        this.component.reference = this;

        if (!readOnly) {
            this.component.on('afterrender', function () {

                var dropTargetEl = this.component.getEl();
                var gridDropTarget = new Ext.dd.DropZone(dropTargetEl, {
                    ddGroup: 'element',
                    getTargetFromEvent: function (e) {
                        return this.component.getEl().dom;
                        //return e.getTarget(this.grid.getView().rowSelector);
                    }.bind(this),
                    onNodeOver: function (overHtmlNode, ddSource, e, data) {
                        var record = data.records[0];
                        var fromTree = this.isFromTree(ddSource);

                        if (this.dndAllowed(record.data, fromTree)) {
                            return Ext.dd.DropZone.prototype.dropAllowed;
                        } else {
                            return Ext.dd.DropZone.prototype.dropNotAllowed;
                        }
                    }.bind(this),
                    onNodeDrop: function (target, dd, e, data) {

                        var record = data.records[0];
                        // var data = ;
                        var fromTree = this.isFromTree(dd);

                        if (this.dndAllowed(record.data, fromTree)) {
                            if (!this.objectAlreadyExists(record.data.id)) {
                                this.loadSourceObjectData(record.data.id);
                                return true;
                            }
                        }
                        return false;
                    }.bind(this)
                });
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

    dndAllowed: function (data, fromTree) {
        // check if data is a treenode, if not allow drop because of the reordering
        if (!fromTree) {
            if (data["grid"] && data["grid"] == this.component) {
                return true;
            }
            return false;
        }

        return this.fieldConfig.sourceAllowedClassName == data.className;
    },

    /**
     * @param {Ext.view.Table} grid
     * @param {string} td
     * @param {number} colIndex
     * @param {Ext.data.Model} record
     * @param {string} tr
     * @param {number} rowIndex
     * @param {Ext.event.Event} e
     * @param {{}} eOpts
     */
    onCellContextMenu: function (grid, td, colIndex, record, tr, rowIndex, e, eOpts) {
        var menu = new Ext.menu.Menu();
        var column = grid.getColumnManager().getHeaderAtIndex(colIndex)

        if (this.fieldConfig.allowDelete) {
            menu.add(new Ext.menu.Item({
                text: t('remove'),
                iconCls: "pimcore_icon_delete",
                handler: function (grid, rowIndex, item) {
                    item.parentMenu.destroy();
                    grid.getStore().removeAt(rowIndex);
                }.bind(this, grid, rowIndex)
            }));
        }


        menu.add(new Ext.menu.Item({
            text: t('open'),
            iconCls: "pimcore_icon_open",
            handler: function (record, item) {
                item.parentMenu.destroy();
                pimcore.helpers.openObject(record.data[this.sourceClassName + '_id'], "object");
            }.bind(this, record)
        }));

        menu.add(new Ext.menu.Item({
            text: t('search'),
            iconCls: "pimcore_icon_search",
            handler: function (item) {
                item.parentMenu.destroy();
                this.openSearchEditor();
            }.bind(this)
        }));

        if (column.config.editor) {
            menu.add(new Ext.menu.Item({
                text: t('duplicate'),
                iconCls: "pimcore_icon_copy",
                handler: function (grid, column, colIndex, rowIndex, record, item) {
                    item.parentMenu.destroy();
                    var dataIndex = column.dataIndex,
                        store = grid.getStore();
                    this.duplicateCell(store, record, dataIndex, rowIndex);
                }.bind(this, grid, column, colIndex, rowIndex, record)
            }));

            menu.add(new Ext.menu.Item({
                text: t('clear_cell'),
                iconCls: 'pimcore_icon_delete',
                handler: function (record, column, item) {
                    this.clearCell(record, column.dataIndex);
                }.bind(this, record, column)
            }));

            menu.add(new Ext.menu.Item({
                text: t('clear_all_cells'),
                iconCls: 'pimcore_icon_delete',
                handler: function (grid, column, colIndex, rowIndex, record, item) {
                    item.parentMenu.destroy();
                    var dataIndex = column.dataIndex,
                        store     = grid.getStore();
                    this.clearCells(store, record, dataIndex, rowIndex);
                }.bind(this, grid, column, colIndex, rowIndex, record)
            }));
        }

        e.stopEvent();
        menu.showAt(e.getXY());
    },
    /**
     * @param {Ext.data.Model} record
     * @param {String} dataIndex The key name Class_Property
     */
    clearCell: function (record, dataIndex) {
        record.set(dataIndex, null);
    },
    /**
     * @param {Ext.data.Store} store
     * @param {Ext.data.Model} record
     * @param {String} dataIndex The key name Class_Property
     * @param {Integer} rowIndex
     */
    clearCells: function (store, record, dataIndex, rowIndex) {
        var spliceRecs = Ext.Array.slice(store.getData().items, rowIndex);
        Ext.Array.each(spliceRecs, function (record) {
            record.set(dataIndex, null);
        }, this);
    },
    /**
     * @param store
     * @param {Ext.data.Model}record
     * @param {String} dataIndex The key name Class_Property
     * @param {Integer} rowIndex
     */
    duplicateCell: function (store, record, dataIndex, rowIndex) {
        var value = record.get(dataIndex);
        var spliceRecs = Ext.Array.slice(store.getData().items, rowIndex + 1);
        Ext.each(spliceRecs, function (record) {
            record.set(dataIndex, value);
        }, this);
    },
    /**
     * @param {array} items
     */
    addDataFromSelector: function (items) {
        // When list is empty first element is undefined
        if (items.length > 0 && typeof items[0] !== 'undefined') {
            for (var i = 0; i < items.length; i++) {

                var sourceId = items[i].id;
                if (!empty(sourceId) && !this.objectAlreadyExists(sourceId)) {
                    this.loadSourceObjectData(sourceId);
                }
            }
        }
    },
    objectAlreadyExists: function (id) {

        // check max amount in field
        if (this.fieldConfig["maxItems"] && this.fieldConfig["maxItems"] >= 1) {
            if (this.store.getCount() >= this.fieldConfig.maxItems) {
                Ext.Msg.alert(t('error'), t('limit_reached'));
                return true;
            }
        }

        // check for existing object
        var result = this.store.query(this.sourceClassName + '_id', new RegExp("^" + id + "$"));

        if (result.length < 1) {
            return false;
        }
        return true;
    },
    /**
     * @param {int} sourceId
     */
    loadSourceObjectData: function (sourceId) {
        var sourceVisibleFields = this.getSourceVisibleFieldsAsArray();
        Ext.Ajax.request({
            url: "/admin/object-helper/load-object-data",
            params: {
                id: sourceId,
                'fields[]': sourceVisibleFields
            },
            success: function (response) {
                var rData = Ext.decode(response.responseText);
                var key;

                if (rData.success) {
                    var newObject = {};

                    // Here we add the default values for bridge object field
                    var bridgeVisibleFields = this.getBridgeVisibleFieldsAsArray();

                    for (var i = 0; i < bridgeVisibleFields.length; i++) {
                        if (!empty(bridgeVisibleFields[i])) {
                            var bridgeFieldLayout = this.fieldConfig.bridgeVisibleFieldDefinitions[bridgeVisibleFields[i]];
                            if (!bridgeFieldLayout) {
                                throw new Error(bridgeVisibleFields[i] + ' is missing from field definition, please add it under enrichLayoutDefinition at Pimcore\\Model\\Object\\ClassDefinition\\Data\\ObjectBridge');
                            }
                            if(Ext.isDefined(bridgeFieldLayout.default)){
                                newObject[this.bridgeClassName + '_' + bridgeFieldLayout.name] = bridgeFieldLayout.default;
                            }
                        }
                    }

                    for (key in rData.fields) {
                        if (in_array(key, sourceVisibleFields)) {
                            newObject[this.sourceClassName + '_' + key] = rData.fields[key];
                        }
                        // Force adding the id
                        if (in_array('id', sourceVisibleFields) === false) {
                            newObject[this.sourceClassName + '_id'] = rData.fields['id'];
                        }
                    }
                    this.store.add(newObject);
                }
            }.bind(this)
        });
    },

    onRefreshComponent: function (dataview) {
        var grid = dataview.panel,
            view = grid.getView(),
            maxAutoSizeWidth = this.fieldConfig.maxWidthResize || null;
        grid.suspendLayouts();

        Ext.each(grid.getColumns(), function (column) {
            var maxContentWidth;

            // Flexible columns should not be affected.
            if (column.flex) {
                return;
            }
            maxContentWidth = view.getMaxContentWidth(column);

            if (maxAutoSizeWidth > 0 && maxContentWidth > maxAutoSizeWidth) {
                column.setWidth(maxAutoSizeWidth);
            } else {
                column.autoSize();
            }
        });

        grid.resumeLayouts();
    },
    /**
     * @param {string} value
     * @param {Object} metaData
     * @param {Object} rec
     * @returns {*}
     */
    renderWithValidation: function (value, metaData, rec) {
        var e = metaData.column.getEditor(rec);
        if (e.allowBlank === false && (value === "" || value === null || value === false || typeof value === 'undefined')) {
            metaData.tdCls = 'invalid-td-cell';
        } else {
            metaData.tdCls = '';
        }
        return value;
    },

    renderDate: function (value, metaData, rec) {

        if (value === "" || value === null || value === false || typeof value === 'undefined') {
            return "";
        }

        if(value.date){
            var dt = new Date(value.date);
            return Ext.Date.format(dt, 'm/d/Y');
        }

        if(value){
            var dt = new Date(value);
            return Ext.Date.format(dt, 'm/d/Y');
        }

        return "";
    },

    /**
     *
     * @param {string} value
     * @param {Object} metaData
     * @param {Object} record
     * @returns string
     */
    renderDisplayField: function (value, metaData, record) {
        var e = metaData.column.getEditor(record);
        var storeRecord = e.store.data.findBy(function (record) {
            return record.data[e.valueField] === value;
        });

        if (e.allowBlank === false && (value === "" || value === null || value === false || typeof value === 'undefined')) {
            metaData.tdCls = 'invalid-td-cell';
        } else {
            metaData.tdCls = '';
        }

        if (storeRecord) {
            return storeRecord.data[e.displayField];
        }
    },

    /**
     * Custom rendered that shows a red border if the record field is invalid
     * @param {string} value
     * @param {Object} metaData
     * @param {Ibood.DealImport.DealImportModel} record
     * @returns {string}
     */
    renderHrefWithValidation: function (value, metaData, record) {


        var editor = metaData.column.getEditor(record);

        if (editor.allowBlank === false && (value === "" || value === null || value === false || typeof value === 'undefined')) {
            metaData.tdCls = 'invalid-td-cell';
        } else {
            metaData.tdCls = '';
        }

        var storeRecord = editor.store.data.findBy(function (record) {
            return record.data[editor.valueField] === value;
        });

        if (storeRecord) {
            return storeRecord.data[editor.displayField];
        }

        return record.get(metaData.column.dataIndex + '_display');
    },

    getSourceVisibleFieldsAsArray: function () {
        return this.fieldConfig.sourceVisibleFields.split(",");
    },
    getBridgeVisibleFieldsAsArray: function () {
        return this.fieldConfig.bridgeVisibleFields.split(",");
    }
});
