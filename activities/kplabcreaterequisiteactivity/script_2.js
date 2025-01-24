(function() {
    BX.namespace('BX.Crm.Activity');

    if (typeof BX.Crm.Activity.KplabCreateRequisiteActivity === 'undefined')
    {
        BX.Crm.Activity.KplabCreateRequisiteActivity = function(params)
        {
            this.formName = params.formName;
            this.presetFieldsMap = params.presetFieldsMap || {};
            this.currentValues = params.currentValues || {};
            this.conditionIndex = 0; // Индекс для динамически добавленных условий
        };

        BX.Crm.Activity.KplabCreateRequisiteActivity.prototype = {
            init: function()
            {
                this.bindEvents();
                this.renderFieldsMap(); // Отображаем строки при инициализации
            },

            bindEvents: function()
            {
                const presetElement = BX('id_preset_id');
                if (presetElement) {
                    BX.bind(presetElement, 'change', BX.delegate(this.onPresetChange, this));
                }

                const addConditionButton = BX('id_bca_ccra_add_condition');
                if (addConditionButton) {
                    BX.bind(addConditionButton, 'click', BX.delegate(this.onAddCondition, this));
                }
            },

            onPresetChange: function()
            {
                this.renderFieldsMap(); // Отображаем строки при изменении preset_id
            },

            onAddCondition: function(event)
            {
                event.preventDefault();
                this.addConditionRow(0);
            },

            addConditionRow: function(fieldId = null)
            {
                const presetElement = BX('id_preset_id');
                if (!presetElement) {
                    return;
                }

                const presetId = presetElement.value;
                const fieldsMap = this.presetFieldsMap[presetId] ? this.presetFieldsMap[presetId].fieldsMap : {};
                const fieldIds = Object.keys(fieldsMap);
                if (fieldIds.length === 0) return;

                this.conditionIndex++;
                if (fieldId === null) {
                    fieldId = fieldIds[(this.conditionIndex - 1) % fieldIds.length]; // Зациклить выбор полей
                }

                const fieldName = fieldsMap[fieldId].FieldName;
                const currentValue = this.currentValues[fieldName] || '';

                const container = BX('fields-map-container');

                const fieldContainer = BX.create('tr', {
                    attrs: {id: `id_bca_ccra_row_id_${this.conditionIndex}`, className: 'crm-activity-popup-field-container'},
                    children: [
                        BX.create('td', {
                            attrs: {className: 'select'},
                            children: [
                                this.createSelectElement(fieldsMap, fieldId, this.conditionIndex)
                            ]
                        }),
                        BX.create('td', {
                            text: '='
                        }),
                        BX.create('td', {
                            attrs: {className: 'input'},
                            children: [
                                this.createInputElement(fieldsMap[fieldId]?.FieldName, currentValue, this.conditionIndex)
                            ]
                        }),
                        BX.create('td', {
                            attrs: {className: 'remove'},
                            children: [
                                BX.create('a', {
                                    attrs: {href: '#', onclick: `BX.remove(BX('id_bca_ccra_row_id_${this.conditionIndex}')); return false;`},
                                    text: 'Удалить'
                                })
                            ]
                        })
                    ]
                });

                container.appendChild(fieldContainer);
            },

            createSelectElement: function(fieldsMap, selectedFieldId, index)
            {
                const options = Object.keys(fieldsMap).map(key => {
                    const field = fieldsMap[key];
                    return BX.create('option', {
                        attrs: {value: field.FieldName, selected: field.FieldName === fieldsMap[selectedFieldId].FieldName},
                        text: field.Name
                    });
                });

                const selectElement = BX.create('select', {
                    children: options
                });

                BX.bind(selectElement, 'change', BX.delegate(function() {
                    this.updateTextArea(selectElement, index);
                }, this));

                return selectElement;
            },

            updateTextArea: function(selectElement, index)
            {
                const selectedValue = selectElement.value;
                const tdInputElement = BX(`id_bca_ccra_row_id_${index}`).querySelector('td.input');
                if (tdInputElement && selectedValue) {
                    tdInputElement.querySelector("div > table > tbody > tr > td > input").name = selectedValue;
                    tdInputElement.querySelector("div > table > tbody > tr > td > input").id = `id_${selectedValue}`;
                }
            },

            createInputElement: function(fieldName, value, index)
            {
                return BX.create('div', {
                    children: [
                        BX.create('table', {
                            attrs: {cellpadding: 0, cellspacing: 0, border: 0, width: '100%', style: 'margin: 2px 0'},
                            children: [
                                BX.create('tbody', {
                                    children: [
                                        BX.create('tr', {
                                            children: [
                                                BX.create('td', {
                                                    attrs: {valign: 'top'},
                                                    children: [
                                                        BX.create('input', {
                                                            attrs: {
                                                                name: fieldName,
                                                                type: "text",
                                                                id: `id_${fieldName}`,
                                                                autocomplete: 'off',
                                                                value: value // Установить текущее значение
                                                            }
                                                        }),
                                                        BX.create('input', {
                                                            attrs: {
                                                                type: 'button',
                                                                value: '...',
                                                                onclick: `BPAShowSelector('id_${fieldName}', 'string');`
                                                            }
                                                        })
                                                    ]
                                                })
                                            ]
                                        })
                                    ]
                                })
                            ]
                        })
                    ]
                });
            },

            renderFieldsMap: function()
            {
                const presetElement = BX('id_preset_id');
                if (!presetElement) {
                    return;
                }

                const presetId = presetElement.value;
                var container = BX('fields-map-container');

                container.innerHTML = '';

                if (this.presetFieldsMap[presetId])
                {
                    var fieldsMap = this.presetFieldsMap[presetId].fieldsMap;
                    var currentValuesAdded = false;

                    // Отображаем строки с текущими значениями
                    for (var fieldName in this.currentValues)
                    {
                        if (this.currentValues.hasOwnProperty(fieldName) && this.currentValues[fieldName])
                        {
                            var fieldId = Object.keys(fieldsMap).find(key => fieldsMap[key].FieldName === fieldName);
                            if (fieldId !== undefined)
                            {
                                this.addConditionRow(fieldId);
                                currentValuesAdded = true;
                            }
                        }
                    }
                    // Если нет текущих значений, отображаем одну строку
                    if (!currentValuesAdded) {
                        this.addConditionRow(0);
                    }
                }
            }
        };
    }
})();
