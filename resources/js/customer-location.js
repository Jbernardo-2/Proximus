const TILE_SIZE = 256;
const DEFAULT_CENTER = { latitude: 14.0723, longitude: -87.1921 };

document.querySelectorAll('[data-customer-location]').forEach((container) => {
    const map = container.querySelector('[data-location-map]');
    const tiles = container.querySelector('[data-location-tiles]');
    const marker = container.querySelector('[data-location-marker]');
    const latitudeInput = container.querySelector('[data-location-latitude]');
    const longitudeInput = container.querySelector('[data-location-longitude]');
    const status = container.querySelector('[data-location-status]');
    const currentButton = container.querySelector('[data-location-current]');
    let zoom = 15;
    let selected = readSelection();
    let center = selected ?? DEFAULT_CENTER;
    let dragStart = null;
    let dragged = false;

    if (! map || ! tiles || ! marker || ! latitudeInput || ! longitudeInput) {
        return;
    }

    const resizeObserver = new ResizeObserver(render);
    resizeObserver.observe(map);

    container.querySelector('[data-location-zoom-in]')?.addEventListener('click', () => {
        zoom = Math.min(19, zoom + 1);
        render();
    });

    container.querySelector('[data-location-zoom-out]')?.addEventListener('click', () => {
        zoom = Math.max(3, zoom - 1);
        render();
    });

    container.querySelector('[data-location-clear]')?.addEventListener('click', () => {
        selected = null;
        latitudeInput.value = '';
        longitudeInput.value = '';
        setStatus('Punto eliminado. Puedes marcar uno nuevo cuando quieras.');
        renderMarker();
    });

    currentButton?.addEventListener('click', () => {
        if (! navigator.geolocation) {
            setStatus('Este dispositivo no ofrece ubicación. Marca el punto directamente en el mapa.', true);

            return;
        }

        currentButton.disabled = true;
        setStatus('Buscando tu ubicación actual…');
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                selectLocation(coords.latitude, coords.longitude, 'Ubicación actual marcada. Confirma visualmente que el punto sea correcto.');
                currentButton.disabled = false;
            },
            (error) => {
                const messages = {
                    1: 'No se autorizó la ubicación. Puedes permitirla en el navegador o marcar el punto en el mapa.',
                    2: 'No fue posible determinar la ubicación. Intenta de nuevo o marca el punto en el mapa.',
                    3: 'La ubicación tardó demasiado. Intenta de nuevo o marca el punto en el mapa.',
                };
                setStatus(messages[error.code] ?? 'No fue posible obtener la ubicación.', true);
                currentButton.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 },
        );
    });

    [latitudeInput, longitudeInput].forEach((input) => input.addEventListener('change', () => {
        const nextSelection = readSelection();

        if (! nextSelection) {
            selected = null;
            renderMarker();

            return;
        }

        selected = nextSelection;
        center = nextSelection;
        setStatus('Coordenadas manuales aplicadas al mapa.');
        render();
    }));

    map.addEventListener('pointerdown', (event) => {
        if (event.target.closest('button, a')) {
            return;
        }

        map.setPointerCapture(event.pointerId);
        dragStart = { x: event.clientX, y: event.clientY, center: { ...center } };
        dragged = false;
    });

    map.addEventListener('pointermove', (event) => {
        if (! dragStart) {
            return;
        }

        const dx = event.clientX - dragStart.x;
        const dy = event.clientY - dragStart.y;
        dragged ||= Math.abs(dx) + Math.abs(dy) > 5;
        center = pixelsToCoordinates(
            coordinatesToPixels(dragStart.center.latitude, dragStart.center.longitude, zoom).x - dx,
            coordinatesToPixels(dragStart.center.latitude, dragStart.center.longitude, zoom).y - dy,
            zoom,
        );
        render();
    });

    map.addEventListener('pointerup', (event) => {
        if (! dragStart) {
            return;
        }

        const wasDragged = dragged;
        dragStart = null;
        map.releasePointerCapture(event.pointerId);

        if (! wasDragged) {
            const bounds = map.getBoundingClientRect();
            const centerPixel = coordinatesToPixels(center.latitude, center.longitude, zoom);
            const selectedPixel = {
                x: centerPixel.x + event.clientX - bounds.left - bounds.width / 2,
                y: centerPixel.y + event.clientY - bounds.top - bounds.height / 2,
            };
            const coordinates = pixelsToCoordinates(selectedPixel.x, selectedPixel.y, zoom);
            selectLocation(coordinates.latitude, coordinates.longitude, 'Punto de entrega marcado en el mapa.');
        }
    });

    map.addEventListener('keydown', (event) => {
        const offsets = { ArrowUp: [0, -40], ArrowDown: [0, 40], ArrowLeft: [-40, 0], ArrowRight: [40, 0] };

        if (! offsets[event.key]) {
            return;
        }

        event.preventDefault();
        const pixel = coordinatesToPixels(center.latitude, center.longitude, zoom);
        center = pixelsToCoordinates(pixel.x + offsets[event.key][0], pixel.y + offsets[event.key][1], zoom);
        render();
    });

    function selectLocation(latitude, longitude, message) {
        selected = { latitude: clamp(latitude, -85.05112878, 85.05112878), longitude: wrapLongitude(longitude) };
        center = { ...selected };
        latitudeInput.value = selected.latitude.toFixed(7);
        longitudeInput.value = selected.longitude.toFixed(7);
        setStatus(message);
        render();
    }

    function readSelection() {
        if (! latitudeInput?.value.trim() || ! longitudeInput?.value.trim()) {
            return null;
        }

        const latitude = Number(latitudeInput?.value);
        const longitude = Number(longitudeInput?.value);

        if (! Number.isFinite(latitude) || ! Number.isFinite(longitude) || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
            return null;
        }

        return { latitude, longitude };
    }

    function render() {
        const bounds = map.getBoundingClientRect();

        if (! bounds.width || ! bounds.height) {
            return;
        }

        const centerPixel = coordinatesToPixels(center.latitude, center.longitude, zoom);
        const startX = centerPixel.x - bounds.width / 2;
        const startY = centerPixel.y - bounds.height / 2;
        const firstTileX = Math.floor(startX / TILE_SIZE);
        const firstTileY = Math.floor(startY / TILE_SIZE);
        const lastTileX = Math.floor((startX + bounds.width) / TILE_SIZE);
        const lastTileY = Math.floor((startY + bounds.height) / TILE_SIZE);
        const fragment = document.createDocumentFragment();
        const tileLimit = 2 ** zoom;

        for (let tileY = firstTileY; tileY <= lastTileY; tileY += 1) {
            if (tileY < 0 || tileY >= tileLimit) {
                continue;
            }

            for (let tileX = firstTileX; tileX <= lastTileX; tileX += 1) {
                const wrappedTileX = ((tileX % tileLimit) + tileLimit) % tileLimit;
                const image = document.createElement('img');
                image.src = `https://tile.openstreetmap.org/${zoom}/${wrappedTileX}/${tileY}.png`;
                image.alt = '';
                image.draggable = false;
                image.className = 'pointer-events-none absolute max-w-none';
                image.style.width = `${TILE_SIZE}px`;
                image.style.height = `${TILE_SIZE}px`;
                image.style.left = `${tileX * TILE_SIZE - startX}px`;
                image.style.top = `${tileY * TILE_SIZE - startY}px`;
                fragment.append(image);
            }
        }

        tiles.replaceChildren(fragment);
        renderMarker();
    }

    function renderMarker() {
        if (! selected) {
            marker.classList.add('hidden');

            return;
        }

        const bounds = map.getBoundingClientRect();
        const centerPixel = coordinatesToPixels(center.latitude, center.longitude, zoom);
        const markerPixel = coordinatesToPixels(selected.latitude, selected.longitude, zoom);
        marker.style.left = `${bounds.width / 2 + markerPixel.x - centerPixel.x}px`;
        marker.style.top = `${bounds.height / 2 + markerPixel.y - centerPixel.y}px`;
        marker.classList.remove('hidden');
    }

    function setStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('text-red-700', isError);
        status.classList.toggle('text-ink-600', ! isError);
    }

    render();
});

function coordinatesToPixels(latitude, longitude, zoom) {
    const scale = TILE_SIZE * 2 ** zoom;
    const safeLatitude = clamp(latitude, -85.05112878, 85.05112878);
    const sin = Math.sin(safeLatitude * Math.PI / 180);

    return {
        x: (wrapLongitude(longitude) + 180) / 360 * scale,
        y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * scale,
    };
}

function pixelsToCoordinates(x, y, zoom) {
    const scale = TILE_SIZE * 2 ** zoom;
    const longitude = x / scale * 360 - 180;
    const n = Math.PI - 2 * Math.PI * y / scale;

    return {
        latitude: 180 / Math.PI * Math.atan(Math.sinh(n)),
        longitude: wrapLongitude(longitude),
    };
}

function clamp(value, minimum, maximum) {
    return Math.min(maximum, Math.max(minimum, value));
}

function wrapLongitude(value) {
    return ((value + 180) % 360 + 360) % 360 - 180;
}
