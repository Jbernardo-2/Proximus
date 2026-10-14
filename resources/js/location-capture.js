const captures = [...document.querySelectorAll('[data-location-capture]')];

// The position is tracked while the page is open so saving never waits on the GPS:
// whatever was last read travels with the form, and the form submits either way.
if (captures.length > 0) {
    const write = (selector, value) => {
        captures.forEach((capture) => {
            capture.querySelector(selector).value = value;
        });
    };

    captures.forEach((capture) => {
        capture.closest('form')?.addEventListener('submit', () => {
            capture.querySelector('[data-capture-time]').value = new Date().toISOString();
        }, { capture: true });
    });

    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            ({ coords }) => {
                write('[data-capture-latitude]', coords.latitude.toFixed(7));
                write('[data-capture-longitude]', coords.longitude.toFixed(7));
                write('[data-capture-accuracy]', coords.accuracy.toFixed(1));
                write('[data-capture-status]', 'captured');
            },
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    write('[data-capture-latitude]', '');
                    write('[data-capture-longitude]', '');
                    write('[data-capture-status]', 'denied');
                }
            },
            { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 },
        );
    }
}
