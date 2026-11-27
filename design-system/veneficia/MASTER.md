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
**Stack:** PHP 8 + MySQL og ren CSS (`@layer`). Ingen frameworks, ingen CDN'er, ingen JS-biblioteker, ingen ikon-fonte. JavaScript kun i små, egne stykker, hvor CSS ikke kan (se "Ændret efter designkritikken").
**Design-dials** (svarer til skillets dials): Variance 3/10 (centreret/rolig) · Motion 3/10 (diskrete mikrointeraktioner) · Density 3/10 (luftig)

---

## Målgruppe → design

| Hvem | Behov | Svar i designet |
|---|---|---|
| **Henriette** (primær, leder en coven, "teknologi dum") | Overskueligt, nemt, en beskrivelse af hvert produkt | Kendt webshop-mønster (Jakobs lov), én primær handling pr. skærm, store klikmål, beskrivelsen i body-large |
| **Sabrina** (sekundær, WitchTok) | "Æstetisk pæn", diskret, stemningsfuld | Mosgrøn ramme, ametyst-glød, Lora-overskrifter, roligt tempo, ingen pop-ups |
| Konkurrent Hekate.dk: "kantet og skarpt" | – | Runde, bløde former (shape-skala, fuldt runde knapper og chips, buen) |
| Konkurrent Wicca.dk: alt på én lang side | – | Tydeligt hierarki, sticky top app bar, brødkrummer, kategorier som chips |
| Problemanalysen | Jordlige farver, natur, tydelig produktinfo, årstider og månens cyklus | Jordpalet (60-30-10), papirstruktur, billedregler for renders, **dagens månefase** i hero'en, faktaafsnit med fragtpriser, købsboks |

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
| Mosgrøn | `#3B4D2E` | 30 %: top app bar, footer og billedbaggrunde |
| Ametyst | `#733B9B` | 10 %: primærknapper, links, fokus på lys bund, aktiv tilstand |
| Ametystskær | `#EFE7F6` | 10 %-familien: tonale knapper, valgte chips, info-flader |
| Lavendel | `#CFB8E8` | 10 % på mørk bund: links, fokus, knapper og badges på mosgrøn |
| Nattegrøn | `#1F2619` | brødtekst og overskrifter, og bunden under forsidens hero-render |
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
| `--color-inverse-surface` | Nattegrøn | bunden under forsidens hero-render (M3 inverse surface). Teksten bruger `.on-secondary` |
| `--color-error` / `--color-on-error` | Fejl / `#FFFFFF` | |
| `--color-error-container` | `color-mix(Fejl 10 %, Lærred)` ≈ `#F7E7E0` | fejlbanner |
| `--color-success` / `--color-on-success` | Succes / `#FFFFFF` | |
| `--color-success-container` | `color-mix(Succes 12 %, Lærred)` ≈ `#E5EBE0` | succesbanner |

**Kontekstvariabler (`--ctx-*`).** Komponenterne læser deres tekst-, link-, fokus- og knapfarve fra kontekstvariabler. `:root` sætter dem til lys bund. Klassen **`.on-secondary`** (bruges på navbar, footer og admin-bar på Mosgrøn og på forsidens hero på Nattegrøn) sætter dem til mørk bund. Så får alt indeni automatisk Pergament tekst og Lavendel accent.

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
- **Mosgrøn (30 %)** rammer siden ind (navbar og footer) og ligger bag produktbillederne. Forsidens hero er selve renderet på Nattegrøn.
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
| Pergament / Sand / Lavendel på Nattegrøn | 13,70 / 10,67 / 8,65 | ✓ hero-teksten under renderet (compact og medium) |
| Pergament / Sand over hero-renderet med Nattegrøn-skyggen (værste pixel under teksten, 320–2560px) | 9,46 / 9,39 | ✓ målt pixel for pixel under hver tekstlinje (2026-11-27) |
| Lavendel på Mosgrøn | 5,10 | ✓ links, fokusring og aktiv tilstand |
| Nattegrøn på Lavendel / lavendel-hover `#C1ACD7` | 8,65 / 7,52 | ✓ knap og badge på mosgrøn |
| Pergament på mosgrøn hover (8 % Pergament) `#4A5A3D` | 6,55 | ✓ nav-hover |
| **Lavendel på mosgrøn hover `#4A5A3D`** | **4,13** | ✗ **hover-flader på mosgrøn har kun Pergament-tekst.** Aktiv tilstand vises med Lavendel på ren Mosgrøn plus en indikator. |
| Ametyst-link på error-container / success-container | 6,17 / 6,11 | ✓ links i bannere |
| Oliven placeholder på Lærred | 6,20 | ✓ `::placeholder` |
| Sand "Intet billede" på billedrammens glød (værste sted) | 5,85 | ✓ |
| Mosgrøn ikon på Sand | 6,30 | ✓ "Sådan handler du" |
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
| **Buen** `--shape-arch` | `999px 999px var(--radius-lg) var(--radius-lg)` | **sparsomt:** kategori-ikoner, tomme tilstande og ordrebekræftelsen (`.arch-icon`) |

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
- **Sideskift:** mellem vores egne sider glider den aktuelle sides pille i menuen (`.nav-link`/`.cart-link[aria-current]`, `view-transition-name: nav-current`) til sin nye plads på 250ms. Selve siden skifter med det samme (`::view-transition-*(root)` uden animation). Det er cross-document view transitions, så browsere uden dem skifter bare side.
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
- `mainStyle.css`: tokens, base, layout, fælles komponenter og utilities. Komponenter, der bruges på flere slags sider: ikoner, skip-link, knapper, felter, chips, kort, produktkort, billedramme, lagerstatus, badge, bannere, tabel-basis, bue-ikonet, månefaser, top app bar og footer.
- `products.css`: komponenter, der kun bruges på butikkens sider, som alle linker `products.css`: `.data-table`, trinindikator, brødkrummer, paginering og tom tilstand.
- `styleguide.css`: kun `/styleguide`, plus de **kommende** komponenter (søgefelt, fri-fragt-bar og dialog). Når en af dem bygges, skal dens CSS flyttes til `mainStyle.css`.
- Side-CSS (`homepage.css`, `products.css`, `usermgnt.css`, `statusCodePage.css`, `admin.css`) ligger i `@layer pages`.
- **Al CSS ligger i et lag.** CSS uden lag vinder ellers over alt.
- Hold specificiteten lav med `:where()`, og brug klassenavne i kebab-case.
- Budget: `mainStyle.css` < ca. 42 kB, hver side-CSS < ca. 15 kB (uminificeret). `admin.css` må fylde ca. 20 kB, fordi kun personalet henter den.

---

## Ikoner og billeder

### Ikoner
- Ét sæt: **Material Symbols Rounded** (Apache-2.0, vægt 400). Kun de ikoner, vi bruger, i én sprite `src/assets/icons/icons.svg` (`<symbol id="…">` + `<svg><use href="/assets/icons/icons.svg#…"></svg>`). Licensen ligger ved siden af.
- I PHP indsættes et ikon med `uiIcon('navn')` (defineret i `header.php`). Spriten får `?v=` til cache-busting.
- Ikonerne i spriten (30 Material): `menu`, `close`, `shopping-bag`, `chevron-left`, `chevron-right`, `delete`, `diamond`, `local-shipping`, `menu-book`, `home`, `search`, `candle`, `psychiatry`, `auto-stories`, `wand-stars`, `experiment`, `star`, `wine-bar`, `local-fire-department` og `category`. Til admin: `space-dashboard`, `receipt-long`, `inventory-2`, `sell`, `account-tree`, `group`, `key`, `logout`, `storefront` og `add`. Dertil kommer de egne symboler `crescent`, `moon-0` til `moon-7` og `logo` (logoets mærke).
- **Kategori-ikoner** vælges ud fra kategoriens slug i `HOME_CATEGORY_ICONS` i `index.php`: krystaller og smykker → `diamond`, røgelse → `local-fire-department`, lys → `candle`, urter og eliksir-ingredienser → `psychiatry`, eliksirer → `experiment`, bøger → `auto-stories`, trylleformularer → `menu-book`, stave → `wand-stars`, rituelle genstande → `wine-bar`, religiøse og andre symboler → `star`, stjernetegn → `crescent`. Alt andet, også nye kategorier, får `category`. Kommer der en ny kategori, så tilføj dens slug.
- Ikoner, som CSS selv tegner (chip-check, brødkrumme-chevron og banner-ikoner), er mask-tokens (`--icon-*`) i `:root`. De har ingen farve og arver `currentColor`. Select-pilen er den eneste med farve i (Oliven).
- Størrelser: 20px (`.icon--20`, inline), 24px (standard), 40px (`.icon--40`).
- `fill: currentColor`, så ikonet arver kontekstens farve.
- Dekorative ikoner får `aria-hidden="true"`. Ikon-knapper får en dansk `aria-label`.
- Ingen emoji som ikoner.

### Logo
Logoet er Augusts tegning: et pentagram med månens faser i en ramme af to firkanter, over ordet VENEFICIA i Cinzel. Farven er Nattegrøn (`#1F2619`), samme farve som i paletten.

| Fil | Indhold | Bruges |
|---|---|---|
| `icons.svg#logo` | mærket, `currentColor` | `uiIcon('logo')` i `.brand` (top app bar, menu og footer) ved siden af wordmarket "Veneficia" i Cinzel |
| `assets/images/logo.svg` | mærket og ordet, Nattegrøn | `<img alt="Veneficia">` over kortet på log ind og opret konto (128px bred) og på fejlsiderne (112px) |
| `assets/images/favicon.svg` | mærket, kvadratisk | favicon. Nattegrøn, og Pergament når fanelinjen er mørk |
| `appIcon192/512.png`, `appIconMaskable192/512.png`, `appleTouchIcon.png` | mærket i Pergament på Mosgrøn | manifestets ikoner og iOS. Mærket fylder 76 % (any), 66 % (maskable, hele mærket inden for den midterste cirkel på 80 %) og 70 % (iOS) |

- **SVG'erne er tracet** fra logoets PNG (alfa-kanten ved 50 %, forenklet med højst 0,8px afvigelse på 2000px-tegningen). De afviger under 5 % fra originalen målt pixel for pixel, og de er skarpe i alle størrelser. PNG-ikonerne er skaleret direkte ned fra originalens pixels.
- **Farver:** Nattegrøn på lys bund og Pergament på Mosgrøn. Ingen andre farver, skygger eller effekter.
- **Mindste størrelse:** 32px for mærket. Under det forsvinder rammens tynde streger (favicon'et er undtaget).
- **Månen** (`crescent`) er ikke længere logoet. Den bruges kun til stjernetegn og ordrebekræftelsen.

### Produktbilleder (regler til Blender-renders)
- **Format:** 1:1, eksportér 1200 × 1200 px som **WebP**, højst ca. **200 kB** pr. billede.
- **Motiv:** mørkt, mystisk nærbillede af én krystal på mos, der lyser indefra. Hold motivet centreret med luft til kanten, for billedet beskæres med `object-fit: cover`.
- **Visning:** altid kvadratisk med `aspect-ratio: 1`, `object-fit: cover` og `width`/`height` på `<img>` (ingen layout-hop). Det første billede på en side får `fetchpriority="high"`, resten `loading="lazy"`.
- **Bagved:** mosgrøn flade med en svag lavendel glød (`.media-frame`), så mørke renders flyder sammen med kortet.
- **Pladsholder** for en vare uden billede: samme glød og **kategoriens ikon** (`categoryIcon()` i `assets/php/products.php`), ingen tekst. Den skal se bevidst ud, ikke som en fejl, og fortæller, hvad slags vare det er.
- **Alt-tekst:** `altText` fra admin, ellers produktnavnet.

### Hero-render (forsiden)
- **Filer:** `src/assets/images/forside-1920.webp` og `forside-1280.webp` (16:9, 1920 × 1080 og 1280 × 720 px, WebP, højst ca. 100 kB). Et nyt render erstatter dem ved at blive eksporteret med samme filnavne. `?v=` får browserne til at hente det igen.
- **Komposition:** hovedmotivet ca. to tredjedele inde fra venstre (krystallen står ved 67 %). Venstre halvdel skal være rolig og mørk, fordi teksten står der. Hold luft over og under motivet, for på brede skærme beskæres top og bund.
- **Visning:**
  - **Compact:** en 4:5-beskæring øverst med krystallen i midten (`object-position: 80%`). Den toner over i Nattegrøn, og teksten starter på det tonede stykke.
  - **Medium:** hele renderet i 16:9 øverst.
  - **Expanded:** renderet fylder båndet bag teksten. Båndet er højst skærmhøjden minus en stribe, så næste afsnit kan ses. En Nattegrøn-skygge går fra venstre (90 % → 0 % ved 66 %), og teksten holder sig inden for 48 % af bredden.
- **Indlæsning:** `fetchpriority="high"`, `srcset` med 1280w og 1920w og `sizes="(max-width: 599px) 222vw, 100vw"`. På compact vises billedet 2,22 gange bredere end skærmen på grund af beskæringen.
- **Alt-tekst:** "Ametyst-krystaltårn på et alter med pentagram i en tåget skov ved levende lys".
- **Ingen zoom-animation og ingen tekst, der toner ind.** En uendelig zoom er bevægelse uden pauseknap (WCAG 2.2.2), og indtoning udskyder den største tekst (LCP).

### Signaturelementer (den røde tråd)
1. **Buen** (shape-arch): kategori-ikoner, tomme tilstande og ordrebekræftelsen.
2. **Dagens månefase:** månens otte faser på række (`moon-0` til `moon-7` i spriten, `aria-hidden`) med **dagens fase fremhævet** og en linje som "Tiltagende måne i dag. Fuldmåne om 5 dage." Kun i forsidens hero. `index.php` regner fasen ud fra datoen (præcis inden for ca. en dag). Det er sitets ene dristige element og henviser til, at månens cyklus er central i Wicca.
3. **Ametyst-glød:** en radial gradient i Lavendel og Ametyst på Mosgrøn bag produktbillederne og bue-ikonerne. Den er et ekko af krystallen i hero-renderet, der lyser indefra.
4. **Papirstruktur:** SVG-støj (`--texture-paper`) på body'ens Pergament, højst ca. 3 % mørkere, så baggrunden læses som papir og ikke som flad creme. Kort og felter (Lærred) er glatte.

---

## Component Specs

| Komponent | Klasse(r) | Spec | Bruges på |
|---|---|---|---|
| **Top app bar** | `header.navbar.on-secondary` > `.navbar__inner.container`: `.skip-link`, `.brand`, `nav#menu.nav-drawer[popover]` > `.nav-list` > `.nav-link`, `.cart-link` + `.badge`, `.menu-button` | Sticky, Mosgrøn, 64px, svag skygge ved scroll. Skip-link "Spring til indhold" → `#indhold`. Logoets mærke (40px) + wordmark "Veneficia" (Cinzel 600, `.brand`). Expanded: Produkter · Log ind/Opret konto eller "Hej, navn"/Log ud · kurv-ikon med Lavendel-badge (`aria-label` "Kurv, 3 varer"). Compact: wordmark, kurv og menuknap → navigation drawer med **Popover API**. Menuikonet bliver et kryds via `:has(#menu:popover-open)`. Aktiv side: `aria-current="page"` + Lavendel indikator. | alle kundesider |
| **Footer** | `.footer.on-secondary` > `.container`: `.footer__grid`, `.footer__links`, `.footer__copy` | Logoets mærke + wordmark, tagline, links (Produkter, Kurv, Handelsbetingelser, Privatlivspolitik), ©-år | alle sider |
| **Knapper** | `.button` + `--tonal` `--outlined` `--text` + `--large` `--block` | Fuldt runde, min-højde 44px (48 med `--large`), label-large, state layers, fokusring 2px/2px offset (`--ctx-focus`), tryk `scale(.98)`, disabled. `<a class="button">` = `<button>`. `<button>` uden klasse = filled. Filled på mosgrøn = Lavendel med Nattegrøn tekst. | overalt |
| **Links** | `a` | `--ctx-link`, altid understreget i brødtekst, `text-underline-offset: 3px`, tykkere ved hover. Nav-links uden understregning. | overalt |
| **Tekstfelter** | `.field`, `input`, `select`, `textarea` | M3 outlined: label over feltet, 48px, Lærred, 1px Oliven, radius 8. Hover Nattegrøn kant. Fokus 2px Ametyst (via `box-shadow`, intet layout-skift). `:user-invalid` giver Fejl-kant + hjælpetekst. Påkrævet markeres via `:has(:required)`. `<select>` med egen pil. Antalsfelt ca. 5,5rem. | formularer |
| **Checkbox/radio** | `.check` | `accent-color`, 20px, hele labelrækken er klikbar og ≥ 44px høj | kasse, log ind, opret |
| **Chips** | `.chip` (+ `[aria-current]`, `:checked`) | 36px, fuldt runde, 1px Oliven. Assist/link: Lærred + Nattegrøn. Valgt/aktuel: Ametystskær + Ametyst + check-ikon. | tags, kategorier, varianter |
| **Produktkort** | `ul.product-grid` > `li.product-card` > `.media-frame` (+ `--empty`), `.product-card__body` > `__category`, `__name` > `a.product-card__link`, `__price`, `.stock` | M3 outlined → elevated ved hover. Lærred, Sand-kant, radius 16. 1:1-billede i `.media-frame`, kategori (label-medium, kan mangle), navn i title-medium, pris Raleway 600, lagerstatus med prik + tekst. Hele kortet klikbart (`.product-card__link::after`), fokusring på kortet via `:has(.product-card__link:focus-visible)`. Skyggen ligger på `::before`, så kun dens opacity animeres. Hover: elevation 2, `translateY(-2px)`, billede `scale(1.03)`. Kategorien står som label-medium med almindelige bogstaver. | produktliste |
| **Lagerstatus** | `.stock` + `--in` / `--out` | prik + tekst (label-medium). "På lager" Succes, "Udsolgt" rolig Oliven | kort, produktside |
| **Bannere** | `.banner` + `--info` `--success` `--error`, `[role=status]`, `[role=alert]`, `.auth-error`, `.admin-success` | Radius 12, ikon + tekst, Nattegrøn brødtekst. Info: Ametystskær. Succes: success-container. Fejl: error-container + 4px venstrekant. | beskeder overalt |
| **Tabeller** | `.data-table`, `.table-scroll` | Styres af en **container query**: Er tabellens container under 600px, bliver rækkerne til kort (`data-label` + `::before`). Ellers vises en tabel med højrestillede tal (`.num`) og total i `tfoot` (`.total-row`, Lora 600 1.25rem). Containerne er `main`, `.basket-lines`, `.summary` og `.card`, så tabellen i kassens smalle oversigt også stables. | kurv, kasse, bekræftelse, varianter |
| **Brødkrummer** | `nav.breadcrumbs > ol` | `aria-label="Brødkrumme"`, chevron-separatorer, sidste punkt `aria-current="page"` | produktliste, produktside |
| **Paginering** | `.product-pages` | outlined-knapperne "Forrige side" og "Næste side" + "Side N" | produktliste |
| **Kategorinavigation** | `nav.category-list` > `ul.chip-set` > `a.chip` | Først chippen "Alle" (eller "Alle i X"), derefter kategorierne. En kategori uden underkategorier viser sine søskende med sig selv markeret (`aria-current`), så nabokategorien er ét tryk væk. Compact: én vandret række chips, `scroll-snap`, næste chip kigger frem, kanten fader ud (bevidst valg, så produkterne bliver højt oppe). Fra 600px: chips der ombrydes. | produktliste |
| **Trinindikator** | `ol.steps` | Kurv · Levering · Bekræftelse, `aria-current="step"`, gennemførte trin med check | kurv, kasse, bekræftelse |
| **Tom tilstand** | `.empty-state` | ikon i bue, kort tekst, én handling | tom kurv, ingen produkter |
| **Købsboks** | `.product-add` | Lærred-kort, Sand-kant, radius 16. Varianter som radio-chips i `<fieldset>`, antal, "Læg i kurv" (filled `--large` `--block`). | produktside |
| **Månefaser** | `.moon-phases` > `svg.moon-phases__phase` (+ `--today`) | otte faser, dagens 36px i Lavendel med glød, resten 20px dæmpede, `aria-hidden` (teksten ved siden af siger fasen) | forside (hero), styleguide |
| **Dialog** | `dialog.dialog` | radius 28, elevation 3, scrim. *(kommende: mersalg ved kassen)* | styleguide |
| **Sendt-knap** | `button[aria-busy="true"]` | Scriptet i `header.php` sætter `aria-busy` på knappen, der sendte formularen. Den får en snurrende ring efter teksten (stille ved reduced motion), og formularen kan ikke sendes to gange. Frigives efter 10 s og ved tilbage-knappen | alle formularer |
| **Admin-sidebjælke** | `adminBar.php`: `.admin-topbar` + `nav#admin-menu.admin-nav[popover]` > `.admin-nav__head`, `.admin-menu` (`.admin-menu__label` + `.nav-list` > `.nav-link`), `.admin-account` | Mosgrøn, 272px, sticky i fuld højde fra 840px. Under 840px en skuffe fra en topbar (Popover API). Tre grupper: Butik (Overblik, Ordrer, Varer, Kategorier, Rabatkoder), Site (Sitemap, Se butikken), Konto (Medarbejdere, Skift adgangskode). Punkter 44px, 4px imellem, ikon + tekst. Aktiv: lavendel pille. Ordrer har et badge med betalte ordrer. Nederst: initial, brugernavn og "Log ud" | alle admin-sider |
| **Ordrestatus** | `.status` + `--paid` `--packed` `--shipped` `--delivered` `--soldout` | Ordet i en pille med en prik. Betalt: Ametystskær/Ametyst (den eneste i handlingsfarven, for den skal pakkes). Pakket: Sand/Nattegrøn. Afsendt: success-container/Succes. Leveret: Oliven-kant og -tekst. Udsolgt: error-container/Fejl | admin |
| **Nøgletal** | `.stat` (+ `--attention`) > `__label` `__value` `__note` | Lærred-kort, label, tallet i Lora headline-large, en note (fx "12 % flere end de 30 dage før"). Det ene tal, der kræver handling, får Ametyst-kant og Ametystskær | admin-overblik |
| **Panel** | `.panel` > `h2`, `.panel__head`, `.panel__note` | Lærred-kort med titel og evt. link til hele listen | admin-overblik |
| **Søjlediagram** | `ol.bar-chart[style="--max"]` > `.bar-chart__day[style="--value"]` | 14 dage, én serie i Mosgrøn, 4px runde toppe, 4px mellemrum. I dag og den travleste dag har tallet på, resten ved hover. Skjult tekst pr. søjle er tabelvisningen | admin-overblik |
| **Søgefelt** | `.search-bar` | M3 search bar, fuldt rund, 56px. *(kommende, kræver backend)* | styleguide |
| **Fri-fragt-bar** | `.free-shipping` | `<progress>` + tekst (goal-gradient). *(kommende)* | styleguide |

---

## Sider (page patterns)

| Side | Fil → CSS | Mønster |
|---|---|---|
| Forside | `index.php` → `homepage.css` | Hero (Blender-renderet på Nattegrøn, `.on-secondary`, se *Hero-render*): display-h1, én linje, primærknap "Se alle produkter" + outlined "Find en kategori", dagens månefase → "Find en kategori" (enkel liste af links, ikon i lille bue, ingen kort) → "Godt at vide, før du handler" (`<dl>`: fragtpriser fra `SHIPPING_RATES`, beskrivelser, konto, rabatkode) |
| Produktliste/kategori | `products.php` → `products.css` | Brødkrumme, h1, beskrivelse (body-large), kategori-chips, gitter 2/3/4, paginering, tom tilstand, tag-filter som valgt chip |
| Produktside | `product.php` → `products.css` | `.product-layout`: galleri (7/12) + sticky info. Compact: vandret galleri, 88 % bredde. Info: brødkrumme, h1, pris, lager, købsboks. Nederst: Beskrivelse, Varianter (tabel), Tags |
| Kurv | `basket.php` → `products.css` | Trin 1, varelinjer (`.basket-lines`) + sticky opsummeringskort (`.summary`) med "Gå til kassen" (filled `--block`) og "Fortsæt med at handle". "Opdater" er tonal, og "Fjern" er en text-knap med ikon. Begge har varens navn som skjult tekst for skærmlæsere. |
| Kasse | `checkout.php` → `products.css` | Trin 2. Fejlopsummeringen står øverst (`role="alert"`, `tabindex="-1"`, `autofocus`). `<aside class="summary">` med ordreoversigt og rabatkode står først i DOM'en, så ordren læses før man bestiller (og står først på mobil). Fra 840px står leveringsformularen til venstre og oversigten til højre. Totalen gentages lige over knappen **"Bestil og betal"**. En godkendt rabatkode giver et succesbanner med "magisk støv" (`.magic-dust`, 600ms, kun opacity og transform). |
| Ordrebekræftelse | `ordreConfimation.php` → `products.css` | Trin 3, bue med måne, "Tak for din ordre, navn!", ordrenummer i Lora, kort med levering + varer, "Shop videre". `@media print` |
| Log ind / Opret | `userMgmt/*.php` → `usermgnt.css` | Hele logoet som link + centreret kort (Lærred, radius 28, elevation 1) |
| Fejlsider (10) | `statusCodesPages/*.php` → `statusCodePage.css` | Dansk, hele logoet øverst som link til forsiden, statuskode i Cinzel (Mosgrøn), venlig h1, "Til forsiden" + "Se produkterne". **Ingen navbar** (rammer databasen). |
| Juridiske | `legal/*.php` → (kun mainStyle) | Side-skal: navbar, h1, "Teksten er på vej.", footer |
| Admin | `admin/*.php` → `admin.css` (+ `usermgnt.css` til formular-kortene) | Sidebjælke til venstre (se Component Specs), arbejdsfladen på Pergament ved siden af, butikkens footer er skjult. **Overblik** (`admin/index.php`): dato og hilsen efter tid på dagen ("God eftermiddag, august"), hurtige handlinger (filled "Pak ordrer (n)" når der er betalte ordrer, ellers "Ny vare"), og et bento-gitter (`grid-template-areas`, 1/2/4 kolonner efter sin egen bredde med container queries): fire nøgletal (venter på pakning, ordrer og salg de sidste 30 dage mod de 30 før, varer med antal udsolgte varianter), ordrer pr. dag i 14 dage, de fem nyeste ordrer, ordrernes vej (antal pr. status) og udsolgte varianter. Alle tal linker til siden, hvor de håndteres. **Ordrer:** søgefelt i toppen og status som chips med antal (ét klik i stedet for en select og en knap), status som pille i tabellen. Tabeller på Lærred med Pergament-hoved og Sand-linjer, tal højrestillet, og hele rækken er klikbar. "Slet" (`button[name="delete"]`) er outlined i fejlfarven. `itemForm.js`' knapper er tonale. Formularerne i `.auth-form`/`.item-form` har 8px fra label til felt og 20px mellem felterne. Layout og adfærd er uændret. |

---

## UX-love → hvor de ses

| Lov / princip | Hvor på sitet |
|---|---|
| **Jakobs lov** | Logo til venstre og kurv med antal øverst til højre (navbar), brødkrummer, produktgitter, kurv → kasse → bekræftelse |
| **Hicks lov / choice overload** | Én primær (ametyst) handling pr. skærm. Varianter som synlige radio-chips. Kategorier som chips, kun topniveau |
| **Fitts' lov** | Primærknapper ≥ 48px og fuld bredde på mobil (`--block`). Hele produktkortet, hver tabelrække og hver ordre på admin-overblikket er klikbar. Alle klikmål ≥ 24 × 24px, helst 44–48 |
| **Millers lov / chunking** | Produktsiden er delt i køb, beskrivelse, varianter og tags. Navigationen har højst 5–7 punkter |
| **Von Restorff** | Kun primærhandlinger er Ametyst. I admin er det kun det tal og den status, der kræver handling (betalte ordrer) |
| **Serial position** | "Produkter" står først i navigationen og kurven sidst |
| **Goal-gradient** | Trinindikatoren Kurv → Levering → Bekræftelse. Fri-fragt-baren ligger klar i styleguiden |
| **Zeigarnik** | Badge med antal på kurv-ikonet |
| **Peak-end** | Rolig og varm ordrebekræftelse (bue, måne, tak, tydeligt ordrenummer). Venlige fejlsider med vej videre |
| **Aesthetic-usability** | Gennemført palet, typografi og former på alle sider |
| **Doherty-tærsklen** | State layers giver feedback inden for 150ms. En sendt formulars knap viser en snurrende ring med det samme og kan ikke sendes igen. Ingen tunge assets |
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
| *(prompten)* Hero-obelisken som inline SVG i en bue | Augusts Blender-render fylder hele hero'en (se *Hero-render*). `obelisk.svg` er kun demobillede i styleguiden | Renderet viser butikkens stemning (skov, levende lys, ametyst) langt bedre end en tegning, og 16:9 passer ikke i en 4:5-bue |
| *(prompten)* Brødkrummen inde i produktsidens info-kolonne | Brødkrummen står over hele `.product-layout` | På mobil står galleriet først. En brødkrumme under billedet ville se forkert ud, og øverst er hvor folk leder efter den (Jakobs lov). |
| *(prompten)* Al fælles komponent-CSS i `mainStyle.css` | Butiksflowets komponenter i `products.css`, kommende i `styleguide.css` | Så holdes `mainStyle.css` på ca. 40 kB, og besøgende henter ikke CSS, de ikke bruger. |

*`search.py` kunne ikke køre (ingen Python på maskinen). Anbefalingerne ovenfor er hentet direkte fra skillets datafiler (`products.csv`, `ui-reasoning.csv`, `typography.csv`, `styles.csv`, `ux-guidelines.csv`, `landing.csv`).*

---

## Anti-Patterns (brug ikke)

- Eyebrows og labels i VERSALER, meta-tekster bundet sammen med midterprikker ("A · B · C") og en label over hver overskrift
- Feature-rækker med tre ikoner i cirkler og vage sætninger: skriv konkrete fakta i stedet
- Hover-løft på alle kort: kun produktkortene løfter sig
- Gentaget pynt: et signaturelement står ét sted, hvor det betyder noget

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

## Ændret efter designkritikken (2026-11-25)

En gennemgang med `frontend-design`-skillet fandt steder, hvor designet lignede en standardskabelon. August besluttede derfor at gå ud over designpromptens låste valg:

| Før | Nu | Hvorfor |
|---|---|---|
| Månefase-række som pynt i hero og footer | **Dagens rigtige månefase** i hero'en, ingen række i footeren | Ét signaturelement, der betyder noget og er specifikt for Wicca, frem for gentaget pynt |
| Hero-overline "KRYSTALLER · RØGELSE · URTER · BØGER" | Fjernet | Gentog h1 og teksten. Versal-eyebrow med midterprikker er et kendt skabelon-træk |
| Kategori i versaler på produktkort, "Lille · 49,00 kr." i chips | Almindelige bogstaver, navn **fed** og pris normal | Samme information uden skabelon-pynt |
| "Sådan handler du": tre ikoner i cirkler | "Godt at vide": rigtige fragtpriser, konto og rabatkode | Konkrete fakta hjælper Henriette mere end ikoner |
| 15 ens kort med hover-løft | Enkel liste af links med ikon i lille bue | Mindre visuel støj og ikke "SaaS-kort-kittet" |
| Pladsholder: glødende diamant + "Intet billede" | Kategoriens ikon | Informerer i stedet for at gentage sig 48 gange |
| Flad Pergament | Svag papirstruktur | Pergament læses som papir og ikke som generisk "AI-creme" |
| Drawer med exit-animation | Lukker med det samme | Exit-overgangen på `display` fik drawer'en til at blinke, når vinduet krydsede 840px |
| Ingen JavaScript | Ét lille inline-script i `products.php` | Scroller den aktive kategori-chip frem på mobil. Uden JS virker alt stadig, chippen er bare ikke i syne |

## Hero-renderet (2026-11-27)

August lavede renderet i Blender og sendte det med et forslag til en hero (`hero.html`). Her er, hvad der er brugt fra forslaget, og hvad der ikke er:

| Forslaget | Her | Hvorfor |
|---|---|---|
| Renderet fylder hero'en, mørk skygge til venstre, billedet øverst på mobil og toner over i baggrunden | Brugt | Renderet er forsidens stærkeste billede. Skyggen giver mindst 9,39:1 under al tekst |
| Google Fonts (Cinzel og EB Garamond) | Lora og Raleway (egne filer) | Ingen eksterne requests. Cinzel kun til brand-elementer |
| Guld `#d9b26a` til knap og overline | Lavendel-knap (`.on-secondary`) | Guld er ikke i paletten. Lavendel gentager ametysten i renderet |
| Overline "Månens krystal", "Ametyst", pris "xxx kr." og links til et ametysttårn | Forsidens egen tekst | Produktet og prisen findes ikke i databasen. Vi opdigter ikke indhold |
| Uendelig zoom (22 s) og tekst, der toner ind | Udeladt | WCAG 2.2.2 (bevægelse over 5 s kræver en pauseknap) og LCP |
| `<link rel="preload">` til billedet | `fetchpriority="high"` på `<img>` | Billedet står tidligt i HTML'en, så browseren finder det med det samme |

## Ændret efter TikTok-gennemgangen (2026-11-27)

August sendte syv opslag om UI. Her er pointerne, og hvor de er brugt. Det meste fra de generelle opslag var allerede på plads.

| Opslag | Pointe | Her |
|---|---|---|
| Sidebar (ux_dose) | Logo øverst, grupper med label, aktiv- og hover-tilstand, status-badge, konto nederst, ensartede 40–44px punkter, højst to niveauer | Admin-sidebjælken |
| 8 navigationsmenuer (ahmadkhancodes) | Tydelig aktiv og hover-tilstand, ikon + tekst, sidebjælke der bliver til en skuffe på mobil | Admin-sidebjælken. Butikkens navbar havde det i forvejen. Mega-menu, bento-menu og "luksus"-stilen er ikke brugt: shoppen har få punkter, og stilen ligger fast |
| 5 UX-love (ux_snacks) | Fitts, Hick, nærhed, Von Restorff, Doherty | Fitts, Hick, nærhed og Von Restorff var på plads (se UX-love). **Nyt:** Doherty med sendt-knappen, statuschips i admin (Hick) og hele rækker som klikmål i admin (Fitts) |
| Kedeligt design (uiuxmanuel) | Hierarki, kontrast, én skrifttype i flere vægte, luft, brud på mønsteret, noget at se på | Var på plads efter designkritikken og hero-renderet. Admin-overblikket har tydeligt hierarki (store tal i Lora, små labels) |
| Navigationsfaner (devdevil.ui) | Den aktive markering glider mellem punkterne | Sideskift med view transitions (se Motion) |
| Bento-gitter (davidm_ai) | `grid-template-areas`, der skifter med bredden | Admin-overblikket, med container queries, fordi sidebjælken ændrer pladsen |
| Admin-dashboard (paulcodes4) | Sidebjælke med grupper og badges, hilsen, hurtige handlinger, nøgletal med udvikling, graf, liste over nyeste ordrer | Hele det nye admin. Uden glas-effekt, mørk tilstand og løft ved hover: det strider mod stilen og anti-mønstrene |

## Pre-Delivery Checklist

Gennemgået 2026-11-24 i Fase 7. Siderne er testet som statiske mockups, der spejler PHP-skabelonernes output, fordi PHP ikke var installeret. De rigtige PHP-sider er ikke kørt i en browser.

- [x] Kun tokens. Ingen rå hex uden for `:root` og ingen gamle variabelnavne.
- [x] Alle par af tekst og baggrund står i kontrasttabellen: tekst ≥ 4,5:1, UI og fokus ≥ 3:1.
- [x] Synlig fokus overalt, logisk tab-rækkefølge, skip-link og `lang="da"`.
- [x] Korrekt overskriftshierarki (én h1 pr. side).
- [x] Klikmål ≥ 24 × 24px, primære mål 44–48px.
- [x] Intet formidles kun med farve.
- [x] Ingen vandret scroll ved 320px. Layoutet virker ved 360, 768, 1280 og 1440px og ved 200 % zoom.
- [x] `prefers-reduced-motion` slår animationerne fra.
- [x] Ingen eksterne requests, to tekst-fontfamilier + Cinzel-subsettet, højst to preloads, CSS-budget overholdt, ingen JS-biblioteker (kun det lille chip-script i `products.php` og formular-scriptet i `header.php`).
- [x] Billeder har `width`/`height`.
- [x] Formularer har uændret `name`, `action`, `method` og CSRF, og al output er escaped.
- [x] Skillets Quick Reference §1–9 (i `SKILL.md`) er gennemgået.
