(function () {
    const adminData = window.PurimImageMapAdmin || {};
    const popups = Array.isArray(adminData.popups) ? adminData.popups : [];
    const strings = adminData.strings || {};

    const root = document.getElementById('purim-image-map-editor');
    if (!root) {
        return;
    }

    const dataInput = root.querySelector('[data-map-input]');
    const previewArea = root.querySelector('[data-preview-area]');
    const altInput = root.querySelector('[data-image-alt]');
    const addHotspotBtn = root.querySelector('[data-add-hotspot]');
    const selectImageBtn = root.querySelector('[data-select-image]');
    const removeImageBtn = root.querySelector('[data-remove-image]');
    const hotspotList = root.querySelector('[data-hotspot-list]');

    let mediaFrame = null;
    let activeUid = null;

    const defaultState = {
        image: {
            id: 0,
            url: '',
            alt: '',
            width: 0,
            height: 0,
        },
        hotspots: [],
    };

    function parseState(raw) {
        if (!raw) {
            return JSON.parse(JSON.stringify(defaultState));
        }

        try {
            const parsed = JSON.parse(raw);
            if (typeof parsed !== 'object' || parsed === null) {
                return JSON.parse(JSON.stringify(defaultState));
            }

            const state = JSON.parse(JSON.stringify(defaultState));

            if (parsed.image && typeof parsed.image === 'object') {
                state.image.id = parseInt(parsed.image.id, 10) || 0;
                state.image.url = typeof parsed.image.url === 'string' ? parsed.image.url : '';
                state.image.alt = typeof parsed.image.alt === 'string' ? parsed.image.alt : '';
                state.image.width = parseInt(parsed.image.width, 10) || 0;
                state.image.height = parseInt(parsed.image.height, 10) || 0;
            }

            if (Array.isArray(parsed.hotspots)) {
                state.hotspots = parsed.hotspots.map(function (item) {
                    return {
                        uid: item.uid ? String(item.uid) : uniqueId(),
                        label: item.label ? String(item.label) : '',
                        popup_id: parseInt(item.popup_id, 10) || 0,
                        x: clampNumber(parseFloat(item.x), 0, 100),
                        y: clampNumber(parseFloat(item.y), 0, 100),
                    };
                });
            }

            return state;
        } catch (err) {
            return JSON.parse(JSON.stringify(defaultState));
        }
    }

    let state = parseState(root.getAttribute('data-config') || dataInput.value || '');

    function uniqueId() {
        return 'hs_' + Math.random().toString(36).slice(2, 11);
    }

    function clampNumber(value, min, max) {
        if (Number.isNaN(value)) {
            return min;
        }
        return Math.max(min, Math.min(max, value));
    }

    function syncDataInput() {
        const serializable = {
            image: Object.assign({}, state.image),
            hotspots: state.hotspots.map(function (item) {
                return {
                    uid: item.uid,
                    label: item.label,
                    popup_id: item.popup_id,
                    x: clampNumber(item.x, 0, 100),
                    y: clampNumber(item.y, 0, 100),
                };
            }),
        };
        dataInput.value = JSON.stringify(serializable);
        dataInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setActive(uid) {
        activeUid = uid;
        refreshActiveState();
        renderPreview();
    }

    function refreshActiveState() {
        const rows = hotspotList.querySelectorAll('.purim-image-map-editor__hotspot-row');
        rows.forEach(function (row) {
            if (row.dataset.uid === activeUid) {
                row.classList.add('is-active');
            } else {
                row.classList.remove('is-active');
            }
        });
    }

    function renderPreview() {
        previewArea.innerHTML = '';

        if (!state.image.id || !state.image.url) {
            const placeholder = document.createElement('div');
            placeholder.className = 'purim-image-map-editor__placeholder';
            placeholder.textContent = strings.imageMissing || 'Seleziona un’immagine per iniziare.';
            previewArea.appendChild(placeholder);
            addHotspotBtn.disabled = true;
            removeImageBtn.disabled = true;
            return;
        }

        addHotspotBtn.disabled = false;
        removeImageBtn.disabled = false;

        const ratio = state.image.width && state.image.height
            ? (state.image.height / state.image.width) * 100
            : 56.25;

        const container = document.createElement('div');
        container.className = 'purim-image-map-editor__preview';
        container.style.setProperty('--purim-map-ratio', ratio + '%');

        const image = document.createElement('img');
        image.src = state.image.url;
        image.alt = state.image.alt || '';
        container.appendChild(image);

        const layer = document.createElement('div');
        layer.className = 'purim-image-map-editor__preview-layer';
        layer.style.position = 'absolute';
        layer.style.inset = '0';
        container.appendChild(layer);

        state.hotspots.forEach(function (spot, index) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'purim-image-map-editor__preview-hotspot';
            button.dataset.hotspotId = spot.uid;
            button.style.left = spot.x + '%';
            button.style.top = spot.y + '%';
            button.textContent = String(index + 1);

            if (spot.uid === activeUid) {
                button.classList.add('is-active');
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                setActive(spot.uid);
            });

            layer.appendChild(button);
        });

        container.addEventListener('click', function (event) {
            const hotspotEl = event.target.closest('[data-hotspot-id]');
            if (hotspotEl) {
                return;
            }

            if (!state.image.id) {
                return;
            }

            const rect = container.getBoundingClientRect();
            if (!rect.width || !rect.height) {
                return;
            }

            const x = ((event.clientX - rect.left) / rect.width) * 100;
            const y = ((event.clientY - rect.top) / rect.height) * 100;
            addHotspot(clampNumber(x, 0, 100), clampNumber(y, 0, 100));
        });

        previewArea.appendChild(container);
    }

    function renderList() {
        hotspotList.innerHTML = '';

        if (!state.hotspots.length) {
            const empty = document.createElement('p');
            empty.className = 'description';
            empty.textContent = strings.addHotspot || 'Clicca sull’immagine per aggiungere il primo punto.';
            hotspotList.appendChild(empty);
            return;
        }

        state.hotspots.forEach(function (spot, index) {
            const row = document.createElement('div');
            row.className = 'purim-image-map-editor__hotspot-row';
            row.dataset.uid = spot.uid;
            if (spot.uid === activeUid) {
                row.classList.add('is-active');
            }

            row.addEventListener('click', function (event) {
                if (event.target.matches('button, button *')) {
                    return;
                }
                setActive(spot.uid);
            });

            const topGrid = document.createElement('div');
            topGrid.className = 'purim-image-map-editor__row-grid';

            const labelField = document.createElement('label');
            labelField.textContent = strings.hotspotLabel || 'Etichetta del punto';
            const labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.className = 'widefat';
            labelInput.value = spot.label || '';
            labelInput.addEventListener('input', function () {
                updateHotspot(spot.uid, { label: labelInput.value });
            });
            labelField.appendChild(document.createElement('br'));
            labelField.appendChild(labelInput);
            topGrid.appendChild(labelField);

            const popupField = document.createElement('label');
            popupField.textContent = strings.selectPopup || 'Popup associato';
            const popupSelect = document.createElement('select');
            popupSelect.className = 'widefat';

            const emptyOption = document.createElement('option');
            emptyOption.value = '0';
            emptyOption.textContent = strings.noPopup || '— Nessun popup —';
            popupSelect.appendChild(emptyOption);

            popups.forEach(function (item) {
                const opt = document.createElement('option');
                opt.value = String(item.id);
                opt.textContent = item.title || ('#' + item.id);
                popupSelect.appendChild(opt);
            });

            popupSelect.value = spot.popup_id ? String(spot.popup_id) : '0';
            popupSelect.addEventListener('change', function () {
                updateHotspot(spot.uid, { popup_id: parseInt(popupSelect.value, 10) || 0 });
            });

            popupField.appendChild(document.createElement('br'));
            popupField.appendChild(popupSelect);
            topGrid.appendChild(popupField);

            row.appendChild(topGrid);

            const coordsGrid = document.createElement('div');
            coordsGrid.className = 'purim-image-map-editor__row-grid purim-image-map-editor__row-grid--coords';

            const xField = document.createElement('label');
            xField.textContent = strings.positionX || 'Posizione X (%)';
            const xInput = document.createElement('input');
            xInput.type = 'number';
            xInput.className = 'small-text';
            xInput.min = '0';
            xInput.max = '100';
            xInput.step = 'any';
            xInput.value = spot.x;
            xInput.addEventListener('input', function () {
                updateHotspot(spot.uid, { x: clampNumber(parseFloat(xInput.value), 0, 100) });
            });
            xField.appendChild(document.createElement('br'));
            xField.appendChild(xInput);
            coordsGrid.appendChild(xField);

            const yField = document.createElement('label');
            yField.textContent = strings.positionY || 'Posizione Y (%)';
            const yInput = document.createElement('input');
            yInput.type = 'number';
            yInput.className = 'small-text';
            yInput.min = '0';
            yInput.max = '100';
            yInput.step = 'any';
            yInput.value = spot.y;
            yInput.addEventListener('input', function () {
                updateHotspot(spot.uid, { y: clampNumber(parseFloat(yInput.value), 0, 100) });
            });
            yField.appendChild(document.createElement('br'));
            yField.appendChild(yInput);
            coordsGrid.appendChild(yField);

            row.appendChild(coordsGrid);

            const footer = document.createElement('div');
            footer.className = 'purim-image-map-editor__row-footer';
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'button-link-delete';
            removeBtn.textContent = strings.remove || 'Rimuovi';
            removeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                removeHotspot(spot.uid);
            });
            footer.appendChild(removeBtn);
            row.appendChild(footer);

            hotspotList.appendChild(row);
        });
    }

    function updateHotspot(uid, changes) {
        const nextHotspots = state.hotspots.map(function (spot) {
            if (spot.uid !== uid) {
                return spot;
            }
            return Object.assign({}, spot, changes);
        });

        state.hotspots = nextHotspots;
        syncDataInput();
        renderPreview();
    }

    function addHotspot(x, y) {
        if (!state.image.id) {
            window.alert(strings.imageMissing || 'Seleziona prima un’immagine.');
            return;
        }

        const newSpot = {
            uid: uniqueId(),
            label: '',
            popup_id: 0,
            x: clampNumber(x, 0, 100),
            y: clampNumber(y, 0, 100),
        };

        state.hotspots.push(newSpot);
        setActive(newSpot.uid);
        syncDataInput();
        renderPreview();
        renderList();
    }

    function removeHotspot(uid) {
        const nextHotspots = state.hotspots.filter(function (spot) {
            return spot.uid !== uid;
        });

        state.hotspots = nextHotspots;

        if (activeUid === uid) {
            activeUid = null;
        }

        syncDataInput();
        renderPreview();
        renderList();
    }

    function resetImage() {
        state.image = JSON.parse(JSON.stringify(defaultState.image));
        state.hotspots = [];
        activeUid = null;
        altInput.value = '';
        syncDataInput();
        renderPreview();
        renderList();
    }

    function setupImageSelection() {
        if (!selectImageBtn) {
            return;
        }

        selectImageBtn.addEventListener('click', function (event) {
            event.preventDefault();

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: strings.chooseImage || 'Seleziona immagine',
                button: {
                    text: strings.chooseImage || 'Usa immagine',
                },
                library: {
                    type: 'image',
                },
                multiple: false,
            });

            mediaFrame.on('select', function () {
                const attachment = mediaFrame.state().get('selection').first();
                if (!attachment) {
                    return;
                }

                const data = attachment.toJSON();
                state.image = {
                    id: data.id || 0,
                    url: data.url || '',
                    alt: data.alt || data.title || '',
                    width: data.width || 0,
                    height: data.height || 0,
                };

                altInput.value = state.image.alt || '';
                syncDataInput();
                renderPreview();
                renderList();
            });

            mediaFrame.open();
        });

        removeImageBtn.addEventListener('click', function (event) {
            event.preventDefault();
            resetImage();
        });
    }

    function setupAltInput() {
        if (!altInput) {
            return;
        }

        altInput.value = state.image.alt || '';
        altInput.addEventListener('input', function () {
            state.image.alt = altInput.value;
            syncDataInput();
        });
    }

    addHotspotBtn.addEventListener('click', function (event) {
        event.preventDefault();
        if (!state.image.id) {
            window.alert(strings.imageMissing || 'Seleziona prima un’immagine.');
            return;
        }
        addHotspot(50, 50);
    });

    setupImageSelection();
    setupAltInput();
    renderPreview();
    renderList();
    syncDataInput();
})();
