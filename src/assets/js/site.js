// Small enhancements on every page (assets/php/header.php loads this with defer). Everything works without it.

// A sent form shows it at once (Doherty threshold): its button gets aria-busy, which mainStyle.css turns into a
// spinner, and a second click doesn't send it again. A cancelled confirm() or itemForm.js's own checks stop the event
// first, so they never get here. The back button (bfcache) and a server that hasn't answered in 10 s free it again.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || form.method === 'dialog') return;
    if (form.dataset.sending) {
        event.preventDefault();
        return;
    }
    form.dataset.sending = '1';
    const button = event.submitter;
    if (button) button.setAttribute('aria-busy', 'true');
    setTimeout(() => {
        delete form.dataset.sending;
        if (button) button.removeAttribute('aria-busy');
    }, 10000);
});
addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('form[data-sending]').forEach((form) => delete form.dataset.sending);
    document.querySelectorAll('[aria-busy="true"]').forEach((button) => button.removeAttribute('aria-busy'));
});
document.addEventListener('DOMContentLoaded', () => {
    // A bar with data-sticky-for="button-id" (the product page's "Læg i kurv", the checkout's "Bestil og betal")
    // shows on phones while that button is out of sight. Without JavaScript there is no bar, only the real button.
    document.querySelectorAll('[data-sticky-for]').forEach((bar) => {
        const target = document.getElementById(bar.dataset.stickyFor);
        if (target && 'IntersectionObserver' in window) {
            new IntersectionObserver(([entry]) => { bar.hidden = entry.isIntersecting; }).observe(target);
        }
    });
    // Every password field gets a "Vis"/"Skjul" button, so a typo can be seen before the form is sent
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        const wrap = document.createElement('span');
        wrap.className = 'password-field';
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-toggle';
        toggle.textContent = 'Vis';
        if (input.id) toggle.setAttribute('aria-controls', input.id);
        toggle.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Skjul' : 'Vis';
        });
        input.before(wrap);
        wrap.append(input, toggle);
    });
    // Suggestions under the search fields while typing (searchSuggestions.php): links to up to six products. Arrow
    // down moves into them, Escape closes them. Without JavaScript the search still works, it just has no list.
    document.querySelectorAll('form[role="search"] input[name="q"]').forEach((input) => {
        const form = input.form;
        const list = document.createElement('ul');
        list.className = 'search-suggestions';
        list.hidden = true;
        const status = document.createElement('p');
        status.className = 'visually-hidden';
        status.setAttribute('role', 'status');
        form.append(list, status);
        input.setAttribute('autocomplete', 'off');
        let timer;
        let controller;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const query = input.value.trim();
                if (query.length < 2) {
                    list.hidden = true;
                    list.replaceChildren();
                    return;
                }
                controller?.abort();
                controller = new AbortController();
                try {
                    const response = await fetch('/searchSuggestions?q=' + encodeURIComponent(query), { signal: controller.signal });
                    const items = await response.json();
                    list.replaceChildren(...items.map((item) => {
                        const link = document.createElement('a');
                        link.href = item.url;
                        const name = document.createElement('span');
                        name.className = 'search-suggestions__name';
                        name.textContent = item.name;
                        const meta = document.createElement('span');
                        meta.className = 'search-suggestions__meta';
                        meta.textContent = [item.category, item.price].filter(Boolean).join(', ');
                        link.append(name, meta);
                        const li = document.createElement('li');
                        li.append(link);
                        return li;
                    }));
                    list.hidden = items.length === 0;
                    status.textContent = items.length ? items.length + ' forslag' : 'Ingen forslag';
                } catch (error) {
                    if (error.name !== 'AbortError') list.hidden = true;
                }
            }, 200);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && !list.hidden) {
                event.preventDefault();
                list.querySelector('a')?.focus();
            } else if (event.key === 'Escape') {
                list.hidden = true;
            }
        });
        list.addEventListener('keydown', (event) => {
            const links = [...list.querySelectorAll('a')];
            const at = links.indexOf(document.activeElement);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                links[Math.min(at + 1, links.length - 1)].focus();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                (at <= 0 ? input : links[at - 1]).focus();
            } else if (event.key === 'Escape') {
                list.hidden = true;
                input.focus();
            }
        });
        form.addEventListener('focusout', (event) => {
            if (!form.contains(event.relatedTarget)) list.hidden = true;
        });
    });
});
