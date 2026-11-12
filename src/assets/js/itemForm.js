// The variant types, variant table and tag suggestions on /admin/item. The starting state comes from the page (data-state), so an item
// being edited shows its saved variants and a form that failed validation comes back with what was typed. Everything is
// built with DOM calls (no innerHTML), so typed text is never parsed as HTML.
(() => {
    const editor = document.getElementById('variantEditor');
    if (!editor) {
        return;
    }
    const state = JSON.parse(editor.dataset.state);

    const el = (tag, props = {}, children = []) => {
        const node = Object.assign(document.createElement(tag), props);
        node.append(...children);
        return node;
    };

    const button = (text, onClick, disabled = false) => el('button', {
        type: 'button',
        textContent: text,
        disabled,
        onclick: () => { onClick(); render(); },
    });

    function typesSection() {
        const rows = state.types.map((type, j) => el('div', { className: 'item-row' }, [
            el('input', {
                type: 'text',
                name: 'typeNames[]',
                value: type,
                maxLength: 50,
                required: true,
                placeholder: 'fx Størrelse',
                ariaLabel: 'Variant-type ' + (j + 1),
                oninput: (e) => {
                    state.types[j] = e.target.value;
                    document.getElementById('typeHead' + j).textContent = e.target.value || 'Type ' + (j + 1);
                },
            }),
            button('Fjern', () => {
                state.types.splice(j, 1);
                state.variants.forEach((v) => v.values.splice(j, 1));
            }),
        ]));
        return el('fieldset', {}, [
            el('legend', { textContent: 'Variant-typer (højst ' + state.maxTypes + ')' }),
            ...rows,
            button('Tilføj type', () => {
                state.types.push('');
                state.variants.forEach((v) => v.values.push(''));
            }, state.types.length >= state.maxTypes),
        ]);
    }

    function variantTable() {
        const head = el('tr', {}, [
            el('th', { textContent: 'Standard' }),
            el('th', { textContent: 'Navn (valgfrit)' }),
            el('th', { textContent: 'Pris (kr.)' }),
            el('th', { textContent: 'Vægt (gram, min. 10)' }),
            ...(state.showStock ? [el('th', { textContent: 'Lager' })] : []),
            ...state.types.map((type, j) => el('th', { id: 'typeHead' + j, textContent: type || 'Type ' + (j + 1) })),
            el('th'),
        ]);

        const rows = state.variants.map((variant, i) => {
            const field = (name, key, props) => el('input', {
                type: 'text',
                name: 'variants[' + i + '][' + name + ']',
                value: variant[key],
                oninput: (e) => { variant[key] = e.target.value; },
                ...props,
            });
            return el('tr', {}, [
                el('td', {}, [
                    el('input', { type: 'hidden', name: 'variants[' + i + '][id]', value: variant.id ?? '' }),
                    el('input', {
                        type: 'radio',
                        name: 'defaultVariant',
                        value: i,
                        checked: i === state.defaultVariant,
                        ariaLabel: 'Standard-variant ' + (i + 1),
                        onchange: () => { state.defaultVariant = i; },
                    }),
                ]),
                el('td', {}, [field('name', 'name', { maxLength: 150, placeholder: 'Automatisk', ariaLabel: 'Navn, variant ' + (i + 1) })]),
                el('td', {}, [field('price', 'price', {
                    required: true,
                    inputMode: 'decimal',
                    pattern: '\\d{1,8}([.,]\\d{1,2})?',
                    placeholder: '149,95',
                    ariaLabel: 'Pris, variant ' + (i + 1),
                })]),
                el('td', {}, [field('weight', 'weight', {
                    required: true,
                    inputMode: 'numeric',
                    pattern: '0*[1-9]\\d{1,6}', // at least 10 g (0,01 kg), checked again on the server
                    title: 'Mindst 10 gram (0,01 kg)',
                    placeholder: '250',
                    ariaLabel: 'Vægt i gram, variant ' + (i + 1),
                })]),
                ...(state.showStock ? [el('td', {}, [field('stock', 'stock', {
                    required: true,
                    inputMode: 'numeric',
                    pattern: '\\d{1,9}',
                    ariaLabel: 'Lager, variant ' + (i + 1),
                })])] : []),
                ...state.types.map((type, j) => el('td', {}, [el('input', {
                    type: 'text',
                    name: 'variants[' + i + '][values][' + j + ']',
                    value: variant.values[j] ?? '',
                    maxLength: 50,
                    required: true,
                    ariaLabel: (type || 'Type ' + (j + 1)) + ', variant ' + (i + 1),
                    oninput: (e) => { variant.values[j] = e.target.value; },
                })])),
                el('td', {}, [button('Fjern', () => {
                    state.variants.splice(i, 1);
                    if (state.defaultVariant >= state.variants.length || state.defaultVariant === i) {
                        state.defaultVariant = 0;
                    } else if (state.defaultVariant > i) {
                        state.defaultVariant--;
                    }
                }, state.variants.length <= 1)]),
            ]);
        });

        return el('div', { className: 'item-variants' }, [
            el('table', { className: 'admin-table' }, [el('thead', {}, [head]), el('tbody', {}, rows)]),
        ]);
    }

    function render() {
        editor.replaceChildren(
            typesSection(),
            variantTable(),
            button('Tilføj variant (' + state.variants.length + '/' + state.maxVariants + ')', () => {
                state.variants.push({ id: null, name: '', price: '', weight: '', stock: '0', values: state.types.map(() => '') });
            }, state.variants.length >= state.maxVariants),
        );
    }

    // While typing in the comma-separated tags field, tags from other items pop up and can be picked. A tag that isn't
    // on the list is just typed out, and the server saves it as a new tag.
    function tagSuggestions() {
        const input = document.getElementById('tags');
        const knownTags = JSON.parse(input.dataset.knownTags);
        if (!knownTags.length) {
            return;
        }

        const list = el('ul', { id: 'tagSuggestions', className: 'item-tag-list', hidden: true });
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', 'Tidligere tags');
        input.after(list);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');
        input.autocomplete = 'off';

        let active = -1;
        let range = { start: 0, end: 0 }; // Where in the field the tag being typed is

        const setActive = (index) => {
            active = index;
            [...list.children].forEach((option, i) => option.setAttribute('aria-selected', String(i === index)));
            if (index >= 0) {
                input.setAttribute('aria-activedescendant', list.children[index].id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        };

        const close = () => {
            setActive(-1);
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        };

        const pick = (tag) => {
            const after = input.value.slice(range.end);
            const text = input.value.slice(0, range.start) + (range.start ? ' ' : '') + tag + (after ? '' : ', ');
            input.value = text + after;
            input.setSelectionRange(text.length, text.length);
            close();
        };

        const update = () => {
            const caret = input.selectionStart;
            const comma = input.value.indexOf(',', caret);
            range = { start: input.value.slice(0, caret).lastIndexOf(',') + 1, end: comma === -1 ? input.value.length : comma };

            const typed = input.value.slice(range.start, range.end).trim().toLowerCase();
            const used = input.value.split(',').map((tag) => tag.trim().toLowerCase());
            const matches = typed === '' ? [] : knownTags
                .filter((tag) => tag.toLowerCase().includes(typed) && !used.includes(tag.toLowerCase()))
                .slice(0, 10);

            list.replaceChildren(...matches.map((tag, i) => {
                const option = el('li', {
                    id: 'tagSuggestion' + i,
                    textContent: tag,
                    onmousedown: (e) => e.preventDefault(), // Keeps focus in the field, so blur doesn't close the list first
                    onclick: () => pick(tag),
                });
                option.setAttribute('role', 'option');
                return option;
            }));
            setActive(-1);
            list.hidden = !matches.length;
            input.setAttribute('aria-expanded', String(!list.hidden));
        };

        input.addEventListener('input', update);
        input.addEventListener('blur', close);
        input.addEventListener('keydown', (e) => {
            if (list.hidden) {
                return;
            }
            const count = list.children.length;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive((active + 1) % count);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(active <= 0 ? count - 1 : active - 1);
            } else if (e.key === 'Enter' && active >= 0) {
                e.preventDefault(); // Picks the tag instead of submitting the form
                pick(list.children[active].textContent);
            } else if (e.key === 'Escape') {
                close();
            }
        });
    }

    render();
    tagSuggestions();
})();
