(function () {
    if (typeof wp === 'undefined' || !wp.blocks) {
        return;
    }

    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, SelectControl } = wp.components;
    const { __ } = wp.i18n;

    function getMaps() {
        const data = window.PurimImageMapBlock;
        if (!data || !Array.isArray(data.maps)) {
            return [];
        }
        return data.maps.map(function (map) {
            return {
                id: parseInt(map.id, 10) || 0,
                title: map.title || '',
            };
        });
    }

    registerBlockType('purim/popup-image-map', {
        title: __('Mappa popup interattiva', 'purim'),
        description: __('Mostra un’immagine con punti cliccabili che aprono i popup configurati.', 'purim'),
        icon: 'location',
        category: 'widgets',
        supports: {
            html: false,
        },
        attributes: {
            mapId: {
                type: 'number',
                default: 0,
            },
        },
        edit: function (props) {
            const maps = getMaps();

            const options = [
                { label: __('Seleziona una mappa', 'purim'), value: 0 },
            ].concat(
                maps.map(function (map) {
                    return {
                        label: map.title || ('#' + map.id),
                        value: map.id,
                    };
                })
            );

            const selected = maps.find(function (map) {
                return map.id === props.attributes.mapId;
            });

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: __('Impostazioni mappa popup', 'purim'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Mappa da visualizzare', 'purim'),
                            value: props.attributes.mapId || 0,
                            options: options,
                            onChange: function (value) {
                                props.setAttributes({ mapId: parseInt(value, 10) || 0 });
                            },
                        })
                    )
                ),
                selected
                    ? el(
                        'div',
                        { className: 'purim-image-map-block-preview' },
                        el('p', { className: 'description' }, __('La mappa selezionata verrà mostrata sul sito.', 'purim')),
                        el('strong', null, selected.title || ('#' + selected.id))
                    )
                    : el(
                        'div',
                        { className: 'purim-image-map-block-preview' },
                        el('p', { className: 'description' }, __('Seleziona una mappa popup dal pannello laterale.', 'purim'))
                    )
            );
        },
        save: function () {
            return null;
        },
    });
})();
