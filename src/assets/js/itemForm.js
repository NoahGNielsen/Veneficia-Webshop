// The variant types and variant table on /admin/items. The starting state comes from the page (data-state), so a form
// that failed validation comes back with what was typed. Everything is built with DOM calls (no innerHTML), so typed
// text is never parsed as HTML.
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
                el('td', {}, [el('input', {
                    type: 'radio',
                    name: 'defaultVariant',
                    value: i,
                    checked: i === state.defaultVariant,
                    ariaLabel: 'Standard-variant ' + (i + 1),
                    onchange: () => { state.defaultVariant = i; },
                })]),
                el('td', {}, [field('name', 'name', { maxLength: 150, placeholder: 'Automatisk', ariaLabel: 'Navn, variant ' + (i + 1) })]),
                el('td', {}, [field('price', 'price', {
                    required: true,
                    inputMode: 'decimal',
                    pattern: '\\d{1,8}([.,]\\d{1,2})?',
                    placeholder: '149,95',
                    ariaLabel: 'Pris, variant ' + (i + 1),
                })]),
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
                state.variants.push({ name: '', price: '', values: state.types.map(() => '') });
            }, state.variants.length >= state.maxVariants),
        );
    }

    render();
})();
