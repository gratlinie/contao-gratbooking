(() => {
    // Terminauswahl ohne Neuladen: Links im Buchungsbereich (Monat, Tag, Uhrzeit, Umbuchen, Absagen) laden nur den
    // Bereich neu und ersetzen ihn. Die Adresse wird mitgeführt (Zurück/Neuladen funktionieren). Ohne JavaScript
    // bleiben es normale Links.
    const selector = '.gb[id^="gb-"]';
    const busyClass = 'is-loading';

    const focusTarget = (container, kind) => {
        const target = {
            month: '.gb-month__title',
            day: '.gb-times__title',
            time: '.gb-form__title',
        }[kind];

        return (target && container.querySelector(target)) || container.querySelector('h2, h3, .gb-summary, .gb-done');
    };

    const kindOf = (link) => {
        if (link.matches('.gb-month__nav')) {
            return 'month';
        }

        if (link.matches('.gb-day')) {
            return 'day';
        }

        if (link.matches('.gb-time')) {
            return 'time';
        }

        return 'other';
    };

    const swap = async (container, url, kind, push) => {
        container.classList.add(busyClass);
        container.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = doc.getElementById(container.id);

            if (!fresh) {
                throw new Error('missing');
            }

            container.replaceWith(fresh);

            if (push) {
                history.pushState({ gb: fresh.id }, '', url);
            }

            const heading = focusTarget(fresh, kind);

            if (heading) {
                heading.setAttribute('tabindex', '-1');
                heading.focus({ preventScroll: true });

                // Nur scrollen, wenn der neue Inhalt außerhalb des Fensters liegt (z. B. das Formular nach der Uhrzeit)
                const rect = heading.getBoundingClientRect();

                if (rect.top < 0 || rect.bottom > window.innerHeight) {
                    heading.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                }
            }
        } catch {
            window.location.href = url;
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest(`${selector} a[href]`);

        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        const container = link.closest(selector);

        // Nur Links auf dieselbe Seite (Kalender, Umbuchen, Absagen) – Zoom-Link usw. bleiben normal
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) {
            return;
        }

        event.preventDefault();
        url.hash = '';
        swap(container, url.toString(), kindOf(link), true);
    });

    window.addEventListener('popstate', () => {
        const container = document.querySelector(selector);

        if (container) {
            swap(container, window.location.href, 'other', false);
        }
    });
})();
