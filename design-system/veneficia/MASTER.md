# Design System Master File

> **LOGIK:** Når du bygger en bestemt side, så tjek først `design-system/veneficia/pages/[side].md`.
> Findes filen, **overskriver** dens regler denne Master-fil. Ellers følger du reglerne herunder.
> Den levende udgave af alle komponenter står på `/styleguide`. Nye komponenter skal tilføjes både her og dér.

---

**Projekt:** Veneficia Webshop (DDU, Teknikfag A, HTX)
**Oprettet:** 2026-11-24
**Kategori:** E-handel · wellness/spiritualitet (Wicca)
**Designfilosofi:** Material Design 3, tilpasset med Material Theming (egen palet, typografi og former)
**Stemning:** organisk, rolig og mystisk: bløde runde former, jordfarver, masser af luft og ét stærkt lilla accent
**Stack:** PHP 8 + MySQL og ren CSS (`@layer`). Ingen frameworks, ingen CDN'er, ingen JS-biblioteker, ingen ikon-fonte.
**Design-dials** (svarer til skillets dials): Variance 3/10 (centreret/rolig) · Motion 3/10 (diskrete mikrointeraktioner) · Density 3/10 (luftig)

---

## Målgruppe → design

| Hvem | Behov | Svar i designet |
|---|---|---|
| **Henriette** (primær, leder en coven, "teknologi dum") | Overskueligt, nemt, en beskrivelse af hvert produkt | Kendt webshop-mønster (Jakobs lov), én primær handling pr. skærm, store klikmål, beskrivelsen i body-large |
| **Sabrina** (sekundær, WitchTok) | "Æstetisk pæn", diskret, stemningsfuld | Mosgrøn ramme, ametyst-glød, Lora-overskrifter, roligt tempo, ingen pop-ups |
| Konkurrent Hekate.dk: "kantet og skarpt" | – | Runde, bløde former (shape-skala, fuldt runde knapper og chips, buen) |
| Konkurrent Wicca.dk: alt på én lang side | – | Tydeligt hierarki, sticky top app bar, brødkrummer, kategorier som chips |
| Problemanalysen | Jordlige farver, natur, tydelig produktinfo, årstider og månens cyklus | Jordpalet (60-30-10), billedregler for renders, månefase-skillelinje, købsboks |

---

## Global Rules

### Farvepalet

Kun lys tilstand. **Rå hex står kun i `:root`.** Komponenter bruger kun de semantiske tokens.

**Råpalet**

| Navn | Hex | Rolle |
|---|---|---|
| Pergament | `#F5F0E6` | 60 %: sidebaggrund |
| Lærred | `#FFFCF5` | 60 %: kort, felter og flader |
| Sand | `#DED5C3` | 60 %: kun pynt (skillelinjer, dekorative kanter) |
| Mosgrøn | `#3B4D2E` | 30 %: top app bar, footer, hero og billedbaggrunde |
| Ametyst | `#733B9B` | 10 %: primærknapper, links, fokus på lys bund, aktiv tilstand |
| Ametystskær | `#EFE7F6` | 10 %-familien: tonale knapper, valgte chips, info-flader |
| Lavendel | `#CFB8E8` | 10 % på mørk bund: links, fokus, knapper og badges på mosgrøn |
| Nattegrøn | `#1F2619` | brødtekst og overskrifter |
| Oliven | `#5B6250` | sekundær tekst, feltkanter og chip-kanter |
| Fejl | `#B3261E` | fejl |
| Succes | `#297044` | succes og "på lager" |

**Semantiske tokens (M3-navne)**

| Token | Værdi | Note |
|---|---|---|
| `--color-background` / `--color-on-background` | Pergament / Nattegrøn | |
| `--color-surface` / `--color-on-surface` | Lærred / Nattegrøn | |
| `--color-surface-variant` / `--color-on-surface-variant` | Sand / Oliven | Oliven **aldrig** som tekst på Sand (4,35) |
| `--color-outline` | Oliven | feltkanter og chips (≥ 3:1) |
| `--color-outline-variant` | Sand | kun dekorative kanter (kort, skillelinjer) |
| `--color-primary` / `--color-on-primary` | Ametyst / `#FFFFFF` | |
| `--color-primary-container` / `--color-on-primary-container` | Ametystskær / Ametyst | |
| `--color-secondary` / `--color-on-secondary` | Mosgrøn / Pergament | |
| `--color-secondary-accent` | Lavendel | accent på mosgrøn |
| `--color-error` / `--color-on-error` | Fejl / `#FFFFFF` | |
| `--color-error-container` | `color-mix(Fejl 10 %, Lærred)` ≈ `#F7E7E0` | fejlbanner |
| `--color-success` / `--color-on-success` | Succes / `#FFFFFF` | |
| `--color-success-container` | `color-mix(Succes 12 %, Lærred)` ≈ `#E5EBE0` | succesbanner |

**Kontekstvariabler (`--ctx-*`).** Komponenterne læser deres tekst-, link-, fokus- og knapfarve fra kontekstvariabler. `:root` sætter dem til lys bund. Klassen **`.on-secondary`** (bruges på navbar, footer, hero og admin-bar) sætter dem til mosgrøn bund. Så får alt indeni automatisk Pergament tekst og Lavendel accent.

| Variabel | Lys bund (`:root`) | Mosgrøn (`.on-secondary`) |
|---|---|---|
| `--ctx-text` | Nattegrøn | Pergament |
| `--ctx-text-muted` | Oliven | Sand |
| `--ctx-link` | Ametyst | Lavendel |
| `--ctx-focus` | Ametyst | Lavendel |
| `--ctx-action` / `--ctx-on-action` | Ametyst / hvid | Lavendel / Nattegrøn |
| `--ctx-outline` | Oliven | Sand |
| `--ctx-state` (state-layer-farve) | Nattegrøn | Pergament |

**60-30-10 i praksis**
- **Ametyst (10 %)** bruges til det, man kan klikke på, og til den ene primære handling pr. skærm. Det bruges aldrig til pynt (Von Restorff).
- **Mosgrøn (30 %)** rammer siden ind (navbar og footer), bærer forsidens hero og ligger bag produktbillederne.
- **Pergament og Lærred (60 %)** er resten.

### Kontrast (WCAG-formlen, rundet ned, genberegnet 2026-11-24)

| Forgrund på baggrund | Kontrast | Brug |
|---|---|---|
| Nattegrøn på Pergament / Lærred / Sand / Ametystskær | 13,70 / 15,18 / 10,67 / 12,91 | ✓ tekst overalt |
| Oliven på Pergament / Lærred / Ametystskær | 5,58 / 6,19 / 5,26 | ✓ sekundær tekst |
| Oliven på Sand | 4,35 | ✗ ikke til normal tekst |
| Mosgrøn på Pergament / Lærred | 8,08 / 8,96 | ✓ statuskoder og ikoner |
| Ametyst på Pergament / Lærred / Ametystskær / Sand | 6,53 / 7,24 / 6,16 / 5,09 | ✓ links og tonale knapper |
| Ametyst på tonal hover `#E5D9EF` | 5,48 | ✓ |
| Ametyst på outlined hover (8 % Ametyst på Lærred / Pergament) | 6,43 / 5,83 | ✓ |
| Hvid på Ametyst / hover `#7E4BA3` / pressed `#814FA5` | 7,42 / 6,13 / 5,84 | ✓ primærknap |
| Fejl på Lærred / Pergament; hvid på Fejl | 6,37 / 5,75; 6,53 | ✓ |
| Fejl / Nattegrøn på error-container `#F7E7E0` | 5,43 / 12,93 | ✓ fejlbanner |
| Succes på Lærred / Pergament / Ametystskær; hvid på Succes | 5,86 / 5,28 / 4,98; 6,00 | ✓ |
| Succes / Nattegrøn på success-container `#E5EBE0` | 4,94 / 12,81 | ✓ succesbanner |
| Oliven-kant mod Lærred / Pergament | 6,19 / 5,58 | ✓ felter og chips (≥ 3:1) |
| Sand mod Pergament / Lærred | 1,28 / 1,42 | kun pynt, aldrig kant på et felt |
| Pergament / Lærred på Mosgrøn | 8,08 / 8,96 | ✓ tekst på mosgrøn |
| Sand på Mosgrøn | 6,30 | ✓ sekundær tekst på mosgrøn |
| Lavendel på Mosgrøn | 5,10 | ✓ links, fokusring og aktiv tilstand |
| Nattegrøn på Lavendel / lavendel-hover `#C1ACD7` | 8,65 / 7,52 | ✓ knap og badge på mosgrøn |
| Pergament på mosgrøn hover (8 % Pergament) `#4A5A3D` | 6,55 | ✓ nav-hover |
| **Lavendel på mosgrøn hover `#4A5A3D`** | **4,13** | ✗ **hover-flader på mosgrøn har kun Pergament-tekst.** Aktiv tilstand vises med Lavendel på ren Mosgrøn plus en indikator. |
| **Ametyst på Mosgrøn** | **1,23** | ✗ aldrig |
| **Oliven på Mosgrøn** | **1,44** | ✗ aldrig |

### Typografi

**Lora** (overskrifter og produktnavne) + **Raleway** (brødtekst og UI). Det er skillets par "Wellness Calm" (calm, wellness, natural, organic). Serif + sans forbinder tradition (Wicca, Book of Shadows) med moderne læsbarhed.

**Cinzel** (logoets font) bruges **kun som brand-font**: wordmarket "Veneficia" og de store statuskoder på fejlsiderne. Cinzel er en versal-font, der er tegnet efter romerske indskrifter. Den giver det højtidelige, "gamle" præg fra logoet, men den er svær at læse i længere tekst (små bogstaver bliver kapitæler). Derfor bruges den ikke til overskrifter, produktnavne eller brødtekst. Lora bærer den samme klassiske stemning i læsbar form.

- Lora og Raleway er selv-hostet i `src/assets/fonts/` (woff2, latin + latin-ext, `font-display: swap`). Kun `raleway-latin.woff2` og `lora-latin.woff2` preloades.
- Cinzel hentes som en **tekst-subset** fra Google Fonts' CSS2-API (`text=` med kun tegnene i "Veneficia" og cifrene 0–9). Den gemmes som `cinzel-wordmark.woff2` (få kB) + `OFL-Cinzel.txt`, med `font-display: swap` og **ingen preload**. Kommer der nye tegn i et brand-element, skal subsettet hentes igen.
- Fallbacks: `--font-brand: "Cinzel", "Lora", Georgia, serif`, `--font-serif: "Lora", Georgia, serif` og `--font-sans: "Raleway", system-ui, sans-serif`.
- `font-variant-numeric: lining-nums` på `body`. Raleways standardtal er gammeldags tal.
- Raleway har ingen tabeltal, så tal i tabelkolonner højrestilles.
- Brug ikke Raleway under vægt 400. Brug ikke kursiv Lora.
- `text-wrap: balance` på overskrifter og `text-wrap: pretty` på afsnit.

| Rolle | Klasse | Font | Størrelse / linjehøjde | Brug |
|---|---|---|---|---|
| brand | `.wordmark` | Cinzel 600 | 1.375rem (navbar) til 2rem (log ind), +0.04em | wordmarket "Veneficia", statuskoder |
| display | `.type-display` | Lora 600 | `clamp(2.25rem, 1.5rem + 3.2vw, 3.5rem)` / 1.08 | forsidens h1 |
| headline-large | `.type-headline-large` (h1) | Lora 600 | `clamp(1.875rem, 1.4rem + 1.8vw, 2.5rem)` / 1.15 | sidens h1 |
| headline-medium | `.type-headline-medium` (h2) | Lora 600 | `clamp(1.5rem, 1.25rem + 1vw, 1.875rem)` / 1.2 | h2 |
| title-large | `.type-title-large` (h3) | Lora 600 | 1.25rem / 1.3 | h3 og kortoverskrifter |
| title-medium | `.type-title-medium` | Lora 600 | 1.0625rem / 1.35 | produktnavne på kort |
| body-large | `.type-body-large` | Raleway 400 | 1.0625rem / 1.7, maks 68ch | produktbeskrivelser |
| body | (standard) | Raleway 400 | 1rem / 1.6 | standard |
| label-large | `.type-label-large` | Raleway 600 | 0.9375rem / 1.2, +0.01em | knapper og chips |
| label-medium | `.type-label-medium` | Raleway 500 | 0.875rem / 1.4 | meta, brødkrummer, lagerstatus |
| overline | `.type-overline` | Raleway 700 | 0.75rem / 1.3, versaler, +0.12em | "eyebrows" over overskrifter |

### Afstande (4/8-grid)

| Token | Værdi | Typisk brug (gestalt: nærhed) |
|---|---|---|
| `--space-1` | 4px | ikon ↔ tekst |
| `--space-2` | 8px | label → felt |
| `--space-3` | 12px | indre luft i små komponenter |
| `--space-4` | 16px | kort-padding (compact), gitter-gap |
| `--space-5` | 24px | felt → felt, gitter-gap (medium og op) |
| `--space-6` | 32px | kort-padding (expanded) |
| `--space-7` | 48px | mellem blokke |
| `--space-8` | 64px | mellem blokke / forsidesektioner |
| `--space-9` | 96px | mellem forsidesektioner (expanded) |

### Former (M3 shape-skala)

| Token | Værdi | Brug |
|---|---|---|
| `--radius-xs` | 4px | små badges og tags i tabeller |
| `--radius-sm` | 8px | felter |
| `--radius-md` | 12px | bannere |
| `--radius-lg` | 16px | kort, købsboks |
| `--radius-xl` | 28px | dialoger og formular-kort (log ind) |
| `--radius-full` | 999px | knapper, chips og badges |
| **Buen** `--shape-arch` | `999px 999px var(--radius-lg) var(--radius-lg)` | **sparsomt:** hero-visual, kategori-ikoner og ordrebekræftelsen |

### Elevation (skygger tonet i Nattegrøn, aldrig rent sort)

| Token | Værdi | Brug |
|---|---|---|
| `--elevation-0` | `none` | kort i hvile (flade med Sand-kant) |
| `--elevation-1` | `0 1px 2px rgb(31 38 25 / .08), 0 1px 3px 1px rgb(31 38 25 / .06)` | formular-kort, sticky bar ved scroll |
| `--elevation-2` | `0 2px 6px rgb(31 38 25 / .10), 0 6px 16px rgb(31 38 25 / .08)` | kort ved hover |
| `--elevation-3` | `0 8px 24px rgb(31 38 25 / .12), 0 2px 6px rgb(31 38 25 / .08)` | dialog, drawer |

### State layers (M3)

Bland "on"-farven (`--ctx-state` eller komponentens forgrund) ind i baggrunden med `color-mix(in srgb, …)`:
- hover 8 %, fokus 10 % og tryk 10 %
- disabled: `opacity: .38` + `cursor: not-allowed`
- tryk på knapper: `transform: scale(.98)`

### Motion

| Token | Værdi |
|---|---|
| `--ease-standard` | `cubic-bezier(.2, 0, 0, 1)` |
| `--ease-decelerate` | `cubic-bezier(.05, .7, .1, 1)` (ind) |
| `--ease-accelerate` | `cubic-bezier(.3, 0, .8, .15)` (ud) |
| `--duration-short` | 150ms (hover og tryk) |
| `--duration-medium` | 250ms (menuer og drawer) |
| `--duration-long` | 400ms (maks) |

- Exit-animationer varer ca. 70 % af entry-animationen.
- Animer kun `transform` og `opacity`.
- **Alt slås fra ved `prefers-reduced-motion: reduce`.**

### Z-index

`--z-sticky: 10` · `--z-header: 20` · `--z-overlay: 40` · `--z-toast: 100`

### Layout og breakpoints (M3 window size classes, mobile-first)

| Klasse | Bredde | Margen | Produktgitter |
|---|---|---|---|
| compact | < 600px | 16px | 2 kolonner |
| medium | ≥ 600px | 24px | 3 kolonner |
| expanded | ≥ 840px | 32px | 4 kolonner, navbaren vises inline |
| large | ≥ 1200px | 32px | 4 kolonner |

- `.container`: `max-width: 1200px` + margen pr. klasse. Brug `min-height: 100dvh`.
- `--header-height: 64px` og `html { scroll-padding-top: calc(var(--header-height) + 16px) }`, så den sticky navbar aldrig skjuler fokus (WCAG 2.4.11).
- Ingen vandret scroll ved 320 px (WCAG 1.4.10). Kun bevidste vandrette scroll-rækker (kategori-chips og galleri på compact) må scrolle, og de ligger inde i containeren.

### CSS-struktur

```
@layer tokens, base, layout, components, pages, utilities;
```
- `mainStyle.css`: tokens, base, layout, fælles komponenter og utilities.
- Side-CSS (`homepage.css`, `products.css`, `usermgnt.css`, `statusCodePage.css`, `admin.css`) ligger i `@layer pages`.
- **Al CSS ligger i et lag.** CSS uden lag vinder ellers over alt.
- Hold specificiteten lav med `:where()`, og brug klassenavne i kebab-case.
- Budget: `mainStyle.css` < ca. 40 kB, hver side-CSS < ca. 15 kB (uminificeret).

---

## Ikoner og billeder

### Ikoner
- Ét sæt: **Material Symbols Rounded** (Apache-2.0, vægt 400). Kun de ikoner, vi bruger, i én sprite `src/assets/icons/icons.svg` (`<symbol id="…">` + `<svg><use href="/assets/icons/icons.svg#…"></svg>`). Licensen ligger ved siden af.
- Størrelser: 20px (inline), 24px (standard), 40px (kategori-buer).
- `fill: currentColor`, så ikonet arver kontekstens farve.
- Dekorative ikoner får `aria-hidden="true"`. Ikon-knapper får en dansk `aria-label`.
- Ingen emoji som ikoner.

### Produktbilleder (regler til Blender-renders)
- **Format:** 1:1, eksportér 1200 × 1200 px som **WebP**, højst ca. **200 kB** pr. billede.
- **Motiv:** mørkt, mystisk nærbillede af én krystal på mos, der lyser indefra. Hold motivet centreret med luft til kanten, for billedet beskæres med `object-fit: cover`.
- **Visning:** altid kvadratisk med `aspect-ratio: 1`, `object-fit: cover` og `width`/`height` på `<img>` (ingen layout-hop). Det første billede på en side får `fetchpriority="high"`, resten `loading="lazy"`.
- **Bagved:** mosgrøn flade med en svag lavendel glød (`.media-frame`), så mørke renders flyder sammen med kortet.
- **Pladsholder** "Intet billede": samme glød og et lille ikon. Den skal se bevidst ud, ikke som en fejl.
- **Alt-tekst:** `altText` fra admin, ellers produktnavnet.

### Signaturelementer (den røde tråd)
1. **Buen** (shape-arch): hero-visual, kategori-ikoner og ordrebekræftelsen.
2. **Månefaser:** en dekorativ række af små faser (inline SVG, `aria-hidden`) som skillelinje i footeren og på forsiden. Den henviser til månens cyklus i Wicca.
3. **Ametyst-glød:** en radial gradient i Lavendel og Ametyst på Mosgrøn bag hero-visualet og produktbillederne. Den er et ekko af krystallerne, der lyser indefra.
4. **Papirstruktur** (valgfri): SVG-støj som data-URI på Pergament med opacitet ≤ 0,04. Kun hvis den ser ren ud og ikke sænker kontrasten mærkbart.

---

## Component Specs

| Komponent | Klasse(r) | Spec | Bruges på |
|---|---|---|---|
| **Top app bar** | `.navbar.on-secondary` | Sticky, Mosgrøn, 64px. Skip-link "Spring til indhold" → `#indhold`. Wordmark "Veneficia" (Cinzel 600, `.wordmark`) + halvmåne. Expanded: Produkter · Log ind/Opret konto eller "Hej, navn"/Log ud · kurv-ikon med Lavendel-badge (`aria-label` "Kurv, 3 varer"). Compact: wordmark, kurv og menuknap → navigation drawer med **Popover API**. Menuikonet bliver et kryds via `:has(#menu:popover-open)`. Aktiv side: `aria-current="page"` + Lavendel indikator. | alle kundesider |
| **Footer** | `.footer.on-secondary` | Månefase-skillelinje, wordmark + tagline, links (Produkter, Kurv, Handelsbetingelser, Privatlivspolitik), ©-år | alle sider |
| **Knapper** | `.button` + `--tonal` `--outlined` `--text` + `--large` `--block` | Fuldt runde, min-højde 44px (48 med `--large`), label-large, state layers, fokusring 2px/2px offset (`--ctx-focus`), tryk `scale(.98)`, disabled. `<a class="button">` = `<button>`. `<button>` uden klasse = filled. Filled på mosgrøn = Lavendel med Nattegrøn tekst. | overalt |
| **Links** | `a` | `--ctx-link`, altid understreget i brødtekst, `text-underline-offset: 3px`, tykkere ved hover. Nav-links uden understregning. | overalt |
| **Tekstfelter** | `.field`, `input`, `select`, `textarea` | M3 outlined: label over feltet, 48px, Lærred, 1px Oliven, radius 8. Hover Nattegrøn kant. Fokus 2px Ametyst (via `box-shadow`, intet layout-skift). `:user-invalid` giver Fejl-kant + hjælpetekst. Påkrævet markeres via `:has(:required)`. `<select>` med egen pil. Antalsfelt ca. 5,5rem. | formularer |
| **Checkbox/radio** | `.check` | `accent-color`, 20px, hele labelrækken er klikbar og ≥ 44px høj | kasse, log ind, opret |
| **Chips** | `.chip` (+ `[aria-current]`, `:checked`) | 36px, fuldt runde, 1px Oliven. Assist/link: Lærred + Nattegrøn. Valgt/aktuel: Ametystskær + Ametyst + check-ikon. | tags, kategorier, varianter |
| **Produktkort** | `.product-card` | M3 outlined → elevated ved hover. Lærred, Sand-kant, radius 16. 1:1-billede i `.media-frame`, overline (kategori, kan mangle), navn i title-medium, pris Raleway 600, lagerstatus med prik + tekst. Hele kortet klikbart (`a::after`), fokusring på kortet via `:has(a:focus-visible)`. Hover: elevation 2, `translateY(-2px)`, billede `scale(1.03)`. | produktliste |
| **Lagerstatus** | `.stock` + `--in` / `--out` | prik + tekst (label-medium). "På lager" Succes, "Udsolgt" rolig Oliven | kort, produktside |
| **Bannere** | `.banner` + `--info` `--success` `--error`, `[role=status]`, `[role=alert]`, `.auth-error`, `.admin-success` | Radius 12, ikon + tekst, Nattegrøn brødtekst. Info: Ametystskær. Succes: success-container. Fejl: error-container + 4px venstrekant. | beskeder overalt |
| **Tabeller** | `.data-table`, `.table-scroll` | Compact: rækker bliver kort (grid, `data-label` + `::before`). Fra 600px: tabel, højrestillede tal, total i `tfoot` (Lora 600 1.25rem). | kurv, kasse, bekræftelse, varianter |
| **Brødkrummer** | `nav.breadcrumbs > ol` | `aria-label="Brødkrumme"`, chevron-separatorer, sidste punkt `aria-current="page"` | produktliste, produktside |
| **Paginering** | `.product-pages` | outlined-knapperne "Forrige side" og "Næste side" + "Side N" | produktliste |
| **Kategorinavigation** | `.category-list` | Compact: én vandret række chips, `scroll-snap`, næste chip kigger frem, kanten fader ud (bevidst valg, så produkterne bliver højt oppe). Fra 600px: chips der ombrydes. | produktliste |
| **Trinindikator** | `ol.steps` | Kurv · Levering · Bekræftelse, `aria-current="step"`, gennemførte trin med check | kurv, kasse, bekræftelse |
| **Tom tilstand** | `.empty-state` | ikon i bue, kort tekst, én handling | tom kurv, ingen produkter |
| **Købsboks** | `.product-add` | Lærred-kort, Sand-kant, radius 16. Varianter som radio-chips i `<fieldset>`, antal, "Læg i kurv" (filled `--large` `--block`). | produktside |
| **Månefaser** | `.moon-phases` | dekorativ række, `aria-hidden` | footer, forside |
| **Dialog** | `dialog.dialog` | radius 28, elevation 3, scrim. *(kommende: mersalg ved kassen)* | styleguide |
| **Søgefelt** | `.search-bar` | M3 search bar, fuldt rund, 56px. *(kommende, kræver backend)* | styleguide |
| **Fri-fragt-bar** | `.free-shipping` | `<progress>` + tekst (goal-gradient). *(kommende)* | styleguide |

---

## Sider (page patterns)

| Side | Fil → CSS | Mønster |
|---|---|---|
| Forside | `index.php` → `homepage.css` | Hero (Mosgrøn, `.on-secondary`) med overline, display-h1, 1–2 linjer, primærknap "Se alle produkter" + outlined "Find en kategori", bue-visual med glød (SVG-obelisk, kan senere erstattes af `<img>`), månefaser → Kategorier (fliser med ikon i bue) → "Sådan handler du" (3 punkter) |
| Produktliste/kategori | `products.php` → `products.css` | Brødkrumme, h1, beskrivelse (body-large), kategori-chips, gitter 2/3/4, paginering, tom tilstand, tag-filter som valgt chip |
| Produktside | `product.php` → `products.css` | `.product-layout`: galleri (7/12) + sticky info. Compact: vandret galleri, 88 % bredde. Info: brødkrumme, h1, pris, lager, købsboks. Nederst: Beskrivelse, Varianter (tabel), Tags |
| Kurv | `basket.php` → `products.css` | Trin 1, varelinjer + sticky opsummeringskort med "Gå til kassen" (filled `--block`) og "Fortsæt med at handle" |
| Kasse | `checkout.php` → `products.css` | Trin 2, fejlopsummering øverst, leveringsformular + `<aside>` med ordreoversigt og rabatkode. Knappen hedder **"Bestil og betal"** |
| Ordrebekræftelse | `ordreConfimation.php` → `products.css` | Trin 3, bue med måne, "Tak for din ordre, navn!", ordrenummer i Lora, kort med levering + varer, "Shop videre". `@media print` |
| Log ind / Opret | `userMgmt/*.php` → `usermgnt.css` | Wordmark-link + centreret kort (Lærred, radius 28, elevation 1) |
| Fejlsider (10) | `statusCodesPages/*.php` → `statusCodePage.css` | Dansk, statuskode i Cinzel (Mosgrøn), venlig h1, "Til forsiden" + "Se produkterne". **Ingen navbar** (rammer databasen). |
| Juridiske | `legal/*.php` → (kun mainStyle) | Side-skal: navbar, h1, "Teksten er på vej.", footer |
| Admin | `admin/*.php` → `admin.css` | Let hånd: Mosgrøn admin-bar, nye komponenter og tokens, uændret layout |

---

## UX-love → hvor de ses

| Lov / princip | Hvor på sitet |
|---|---|
| **Jakobs lov** | Logo til venstre og kurv med antal øverst til højre (navbar), brødkrummer, produktgitter, kurv → kasse → bekræftelse |
| **Hicks lov / choice overload** | Én primær (ametyst) handling pr. skærm. Varianter som synlige radio-chips. Kategorier som chips, kun topniveau |
| **Fitts' lov** | Primærknapper ≥ 48px og fuld bredde på mobil (`--block`). Hele produktkortet er klikbart. Alle klikmål ≥ 24 × 24px, helst 44–48 |
| **Millers lov / chunking** | Produktsiden er delt i køb, beskrivelse, varianter og tags. Navigationen har højst 5–7 punkter |
| **Von Restorff** | Kun primærhandlinger er Ametyst |
| **Serial position** | "Produkter" står først i navigationen og kurven sidst |
| **Goal-gradient** | Trinindikatoren Kurv → Levering → Bekræftelse. Fri-fragt-baren ligger klar i styleguiden |
| **Zeigarnik** | Badge med antal på kurv-ikonet |
| **Peak-end** | Rolig og varm ordrebekræftelse (bue, måne, tak, tydeligt ordrenummer). Venlige fejlsider med vej videre |
| **Aesthetic-usability** | Gennemført palet, typografi og former på alle sider |
| **Doherty-tærsklen** | State layers giver feedback inden for 150ms. Ingen tunge assets og ingen ny JS |
| **Tesler / Postel** | Systemet regner fragt, rabat og lager ud. Felterne beholder `inputmode` og `autocomplete` |
| **Gestalt** | Nærhed (spacing-skalaen), lighed (samme funktion = samme stil), fælles område (kort og købsboks), figur–grund (Lærred-kort på Pergament i mosgrøn ramme), Prägnanz (enkle former) |
| **Kognitiv belastning** | Formularer i én kolonne med labels over felterne. Fejl ved feltet og i en opsummering øverst |

---

## Afvigelser fra skillet (med begrundelse)

| Skillet anbefaler | Vi gør | Begrundelse |
|---|---|---|
| E-handel: stil "Vibrant & Block-based", farver "Brand primary + Success green" | Rolig M3 med jordpalet og ét lilla accent | Rapporten kræver et roligt, stemningsfyldt design (Sabrina, Henriette). Skillets "Beauty/Spa/Wellness"-række peger også på ro og "Soft UI". |
| Wellness: "Soft UI Evolution + Neumorphism", pasteller + guld | M3 med outlined/elevated kort, ingen neumorfisme | Neumorfisme har svag kontrast på kanter og felter. M3 er vores designfilosofi (krav 4). |
| Wellness: "Hero-Centric + Social Proof", testimonials | Hero + kategorier + "Sådan handler du", ingen social proof | Vi må ikke opdigte anmeldelser eller udtalelser. |
| Wellness anti-pattern: "Dark mode" | Kun lys tilstand | Stemmer overens: vi fjerner mørk tilstand. |
| `number-tabular` (tabeltal til priser) | `lining-nums` + højrestillede talkolonner | Raleway har ingen tabeltal. |
| Ikoner: Phosphor/Heroicons (React-pakker) | Material Symbols Rounded som SVG-sprite | Matcher M3 og kræver hverken React, ikon-font eller CDN. |
| Typografi-rækkens Google Fonts-`@import` | Selv-hostede woff2-filer | Ingen eksterne requests (sidehastighed og privatliv). |
| Ét font-par (to familier) | Lora + Raleway + Cinzel som brand-font | Cinzel er logoets font, så wordmarket matcher logoet. Den er begrænset til en tekst-subset på få kB uden preload, så den koster næsten intet. |
| E-handel effekt "Card hover lift (200ms) + Scale" | Lift `translateY(-2px)` + billede `scale(1.03)` på 150ms | Tilpasset M3's motion-tokens. |
| `--motion`-dialens GSAP-snippets | Ren CSS | Ingen JS-biblioteker. |
| `spring-physics` | M3's cubic-bezier-easing | Ren CSS uden JS. M3 easing er vores motion-system. |
| Stack-råd (React, Tailwind osv.) | Springes over | Ingen af skillets stacks passer til ren PHP + vanilla CSS. |

*`search.py` kunne ikke køre (ingen Python på maskinen). Anbefalingerne ovenfor er hentet direkte fra skillets datafiler (`products.csv`, `ui-reasoning.csv`, `typography.csv`, `styles.csv`, `ux-guidelines.csv`, `landing.csv`).*

---

## Anti-Patterns (brug ikke)

- Rå hex uden for `:root` og gamle variabelnavne (`--text`, `--muted`, `--accent` …)
- Ametyst eller Oliven på Mosgrøn, og Lavendel på en hover-flade på Mosgrøn
- Ametyst brugt som pynt
- Sand som kant på et felt eller en knap
- Information kun via farve (lagerstatus, fejl og succes har altid tekst og ikon)
- Emoji som ikoner, ikon-fonte, CDN'er, frameworks og JS-biblioteker
- WebGL, custom cursors, scroll-jacking, parallax, autoplay-karruseller og tunge intro-animationer
- Animation af andet end `transform` og `opacity`, og animation uden reduced-motion-fallback
- Opdigtet indhold: anmeldelser, stjerner, kundetal, garantier, kontaktoplysninger og udokumenterede påstande
- Fjernet fokusring uden erstatning, og placeholder som eneste label
- Cinzel uden for brand-elementerne (wordmark og statuskoder), fx til overskrifter, knapper eller brødtekst

---

## Pre-Delivery Checklist

- [ ] Kun tokens. Ingen rå hex uden for `:root` og ingen gamle variabelnavne.
- [ ] Alle par af tekst og baggrund står i kontrasttabellen: tekst ≥ 4,5:1, UI og fokus ≥ 3:1.
- [ ] Synlig fokus overalt, logisk tab-rækkefølge, skip-link og `lang="da"`.
- [ ] Korrekt overskriftshierarki (én h1 pr. side).
- [ ] Klikmål ≥ 24 × 24px, primære mål 44–48px.
- [ ] Intet formidles kun med farve.
- [ ] Ingen vandret scroll ved 320px. Layoutet virker ved 360, 768, 1280 og 1440px og ved 200 % zoom.
- [ ] `prefers-reduced-motion` slår animationerne fra.
- [ ] Ingen eksterne requests, to tekst-fontfamilier + Cinzel-subsettet, højst to preloads, CSS-budget overholdt, ingen ny JS.
- [ ] Billeder har `width`/`height`.
- [ ] Formularer har uændret `name`, `action`, `method` og CSRF, og al output er escaped.
- [ ] Skillets Quick Reference §1–9 (i `SKILL.md`) er gennemgået.
