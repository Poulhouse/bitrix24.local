(function() {
    BX.namespace('BX.Crm.Activity');

    if (typeof BX.Crm.Activity.KplabCreateRequisiteActivity === 'undefined')
    {
        BX.Crm.Activity.KplabCreateRequisiteActivity = function(params)
        {
            this.formName = params.formName;
            this.presetFieldsMap = params.presetFieldsMap || {};
            this.currentValues = params.currentValues || {};
        };

        BX.Crm.Activity.KplabCreateRequisiteActivity.prototype = {
            init: function()
            {
                this.bindEvents();
                this.renderFieldsMap();
            },

            bindEvents: function()
            {
                const presetElement = BX('id_preset_id');
                if (presetElement) {
                    BX.bind(presetElement, 'change', BX.delegate(this.onPresetChange, this));
                }
            },

            onPresetChange: function()
            {
                this.renderFieldsMap();
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

                    for (var fieldId in fieldsMap)
                    {
                        if (fieldsMap.hasOwnProperty(fieldId))
                        {
                            var field = fieldsMap[fieldId];
                            var fieldContainer = BX.create('tr', {
                                attrs: {className: 'crm-activity-popup-field-container'},
                                children: [
                                    BX.create('td', {
                                        text: field.Name,
                                        attrs: {align: "right", width: "40%", className: 'adm-detail-content-cell-l'}
                                    }),
                                    this.renderFieldControl(fieldId, field)
                                ]
                            });

                            container.appendChild(fieldContainer);
                        }
                    }
                }
            },

            renderFieldControl: function(fieldId, field)
            {
                var control;

                switch (field.Type)
                {
                    case 'string':
                    case 'integer':
                        control = BX.create('td', {
                            attrs: {width: "60%", class:"adm-detail-content-cell-r"},
                            children: [
                                BX.create('input', {
                                    attrs: {
                                        type: 'text',
                                        name: 'requisite_fields[' + fieldId + ']',
                                        value: this.currentValues[fieldId] || ''
                                    }
                                }),
                                BX.create('input', {
                                    attrs: {
                                        type: 'button',
                                        value: '...',
                                        onclick: 'BPAShowSelector("requisite_fields[' + fieldId + ']", "string");'
                                    }
                                })
                            ]
                        });
                        break;

                    case 'bool':
                        control = BX.create('td', {
                            attrs: {width: "60%", class:"adm-detail-content-cell-r"},
                            children: [
                                BX.create('input', {
                                    attrs: {
                                        type: 'checkbox',
                                        name: 'requisite_fields[' + fieldId + ']',
                                        checked: this.currentValues[fieldId] === 'Y'
                                    }
                                })
                            ]
                        });
                        break;

                    case 'list':
                        control = BX.create('td', {
                            attrs: {width: "60%", class:"adm-detail-content-cell-r"},
                            children: [
                                BX.create('select', {
                                    attrs: {name: 'requisite_fields[' + fieldId + ']'},
                                    children: field.Options.map(function(option) {
                                        return BX.create('option', {
                                            attrs: {
                                                value: option.VALUE,
                                                selected: this.currentValues[fieldId] === option.VALUE
                                            },
                                            text: option.NAME
                                        });
                                    }, this)
                                })
                            ]
                        });
                        break;

                    default:
                        control = BX.create('td', {
                            attrs: {width: "60%", class:"adm-detail-content-cell-r"},
                            children: [
                                BX.create('input', {
                                    attrs: {
                                        type: 'text',
                                        name: 'requisite_fields[' + fieldId + ']',
                                        value: this.currentValues[fieldId] || ''
                                    }
                                }),
                                BX.create('input', {
                                    attrs: {
                                        type: 'button',
                                        value: '...',
                                        onclick: 'BPAShowSelector("requisite_fields[' + fieldId + ']", "string");'
                                    }
                                })
                            ]
                        });
                        break;
                }

                return control;
            }
        };
    }
})();
