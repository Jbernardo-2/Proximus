const notificationCenter = document.querySelector('[data-notification-center]');

if (notificationCenter) {
    const button = notificationCenter.querySelector('[data-notification-toggle]');
    const panel = notificationCenter.querySelector('[data-notification-panel]');
    const badge = notificationCenter.querySelector('[data-notification-badge]');
    const list = notificationCenter.querySelector('[data-notification-list]');
    const markAllButton = notificationCenter.querySelector('[data-notification-read-all]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    let knownUnreadIds = new Set(
        [...list.querySelectorAll('[data-notification-id]:not([data-notification-read="true"])')]
            .map((item) => item.dataset.notificationId),
    );
    let initialized = false;

    const requestHeaders = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
    };

    const severityClass = (severity) => ({
        success: 'bg-emerald-500',
        warning: 'bg-amber-500',
        important: 'bg-red-500',
        info: 'bg-sky-500',
    }[severity] ?? 'bg-sky-500');

    const updateBadge = (count) => {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.classList.toggle('hidden', count === 0);
        button.classList.toggle('animate-pulse', count > 0);
        button.setAttribute('aria-label', `Notificaciones: ${count} sin leer`);
    };

    const createNotification = (notification) => {
        const link = document.createElement('a');
        link.href = notification.url;
        link.dataset.notificationId = notification.id;
        link.dataset.notificationRead = String(notification.read);
        link.dataset.readUrl = notificationCenter.dataset.readUrl.replace('__ID__', notification.id);
        link.className = `flex gap-3 px-4 py-3 transition hover:bg-stone-50 ${notification.read ? 'opacity-65' : 'bg-mint-50/60'}`;

        const dot = document.createElement('span');
        dot.className = `mt-1.5 size-2.5 shrink-0 rounded-full ${severityClass(notification.severity)}`;
        const content = document.createElement('span');
        content.className = 'min-w-0';
        const title = document.createElement('span');
        title.className = 'block truncate text-sm font-bold text-ink-950';
        title.textContent = notification.title;
        const message = document.createElement('span');
        message.className = 'mt-0.5 block text-xs text-ink-600';
        message.textContent = notification.message;
        const time = document.createElement('span');
        time.className = 'mt-1 block text-[10px] font-semibold text-ink-500';
        time.textContent = notification.created_at;
        content.append(title, message, time);
        link.append(dot, content);

        return link;
    };

    const showToast = (notification) => {
        const toast = notificationCenter.querySelector('[data-notification-toast]');
        toast.querySelector('[data-toast-title]').textContent = notification.title;
        toast.querySelector('[data-toast-message]').textContent = notification.message;
        toast.href = notification.url;
        toast.classList.remove('hidden');
        window.setTimeout(() => toast.classList.add('hidden'), 7000);
    };

    const bindNotificationLinks = () => {
        list.querySelectorAll('[data-notification-id]').forEach((link) => {
            if (link.dataset.bound === 'true' || link.dataset.notificationRead === 'true') {
                return;
            }

            link.dataset.bound = 'true';
            link.addEventListener('click', (event) => {
                event.preventDefault();
                fetch(link.dataset.readUrl, { method: 'POST', headers: requestHeaders, keepalive: true })
                    .finally(() => { window.location.assign(link.href); });
            });
        });
    };

    const refresh = async () => {
        if (document.visibilityState === 'hidden') {
            return;
        }

        try {
            const response = await fetch(notificationCenter.dataset.feedUrl, { headers: requestHeaders });

            if (! response.ok) {
                return;
            }

            const payload = await response.json();
            const nextUnreadIds = new Set(payload.notifications.filter((item) => ! item.read).map((item) => item.id));
            const newNotification = initialized
                ? payload.notifications.find((item) => ! item.read && ! knownUnreadIds.has(item.id))
                : null;
            knownUnreadIds = nextUnreadIds;
            initialized = true;
            updateBadge(payload.unread_count);
            list.replaceChildren(...payload.notifications.map(createNotification));
            bindNotificationLinks();

            if (newNotification) {
                showToast(newNotification);
            }
        } catch {
            // La siguiente consulta volverá a intentar sin interrumpir el trabajo del usuario.
        }
    };

    button.addEventListener('click', () => {
        const willOpen = panel.classList.contains('hidden');
        panel.classList.toggle('hidden', ! willOpen);
        button.setAttribute('aria-expanded', String(willOpen));
    });
    document.addEventListener('click', (event) => {
        if (! notificationCenter.contains(event.target)) {
            panel.classList.add('hidden');
            button.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            panel.classList.add('hidden');
            button.setAttribute('aria-expanded', 'false');
        }
    });
    markAllButton?.addEventListener('click', async () => {
        const response = await fetch(notificationCenter.dataset.readAllUrl, { method: 'POST', headers: requestHeaders });

        if (response.ok) {
            updateBadge(0);
            knownUnreadIds.clear();
            list.querySelectorAll('[data-notification-id]').forEach((item) => {
                item.dataset.notificationRead = 'true';
                item.classList.add('opacity-65');
                item.classList.remove('bg-mint-50/60');
            });
        }
    });

    bindNotificationLinks();
    window.setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            void refresh();
        }
    });
}
