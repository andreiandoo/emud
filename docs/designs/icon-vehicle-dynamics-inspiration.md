# Inspirație: ICON Vehicle Dynamics

Sursa: https://iconvehicledynamics.com/ (Shopify, temă custom), analizată pe 2026-09-10 din HTML, CSS și capturi
la 1440px și 390px (homepage, colecția Suspension, pagina de produs 58450DJ).

Valorile de mai jos sunt cele reale din CSS-ul lor. Luăm limbajul vizual, nu identitatea: logo-ul, fotografiile,
textele și combinația exactă galben #FFC62F + Russo One sunt marca lor.

---

## 1. Caracterul

Tehnic, dur, sigur pe el. Aproape negru + un singur accent cald, colțuri drepte peste tot, titluri în majuscule
cu litere largi, fotografie de teren pe toată lățimea. Secțiunile alternează negru / alb, iar negrul poartă o
textură discretă de curbe de nivel (hartă topografică) care leagă totul de off-road.

## 2. Culori

| Rol | Valoare | Unde apare |
|---|---|---|
| Negru de bază | `#000104` | text, header, secțiuni închise, butoane outline (cea mai folosită culoare: 149 apariții) |
| Alb | `#FFFFFF` | fundal pagină, carduri |
| Accent (primar) | `#FFC62F` | buton principal, bara de anunț, badge „sale”, CTA în finder |
| Accent hover | `#D9A828` | hover pe butonul principal |
| Accent activ | `#BF9423` | apăsat, link hover pe fundal deschis |
| Accent închis | `#A6811F` | link activ pe fundal deschis |
| Scala accentului | `#FFF6E0` `#FFEEC1` `#FFE397` `#FFD463` `#FFC62F` `#F2BC2D` `#D9A828` `#BF9423` `#A6811F` | 100 → 900 |
| Gri | `#F2F2F2` `#E5E6E6` `#D9D9D9` `#CCCCCD` `#B2B3B4` `#99999B` `#808081` `#666768` `#4D4D4F` | 100 → 900 |
| Gri închis | `#333436` `#26272A` `#1A1A1D` `#141518` | 1000 → 1300, pentru straturile secțiunilor negre |
| Bordură | `#E5E6E6` | carduri, separatoare |
| Pericol | `#DE3535` | erori |
| Succes | `#198754` | badge „In stock” (verde) |

Observație: textul pe accent e mereu negru `#000104`, niciodată alb. Galbenul are contrast slab cu albul,
iar ei au ținut regula.

Umbre (aproape nefolosite, designul se sprijină pe borduri, nu pe umbre):
`0 .125rem .25rem rgba(0,0,0,.075)` · `0 .5rem 1rem rgba(0,0,0,.15)` · `0 1rem 3rem rgba(0,0,0,.175)`

Suprapunere peste fotografii: `linear-gradient(180deg, transparent 67%, rgba(0,0,0,.5) 100%)`, adică se
închide doar partea de jos, unde stă textul.

## 3. Fonturi

| Rol | Font | Detalii |
|---|---|---|
| Titluri | **Russo One** 400 | MAJUSCULE, `letter-spacing: .05em`, `line-height: 1.1` |
| Text | **Roboto** 300–900 | 15px pe mobil, 16px pe desktop, `line-height: 1.5` |
| Butoane și linkuri de acțiune | Roboto 600 | MAJUSCULE, 14px, `letter-spacing: .1em` |
| Badge-uri | Roboto 600 | MAJUSCULE, 11–13px, `letter-spacing: .05em` |

Scala titlurilor (mobil / tabletă / desktop):

| | sm | md | lg |
|---|---|---|---|
| Display (hero) | 30px | 55px | 65px, `line-height: 1`, `letter-spacing: .03em` |
| H1 | 32px | 36px | 42px |
| H2 | 24px | 30px | 34px, `line-height: 1.2` |
| H3 | 20px | 30px | 26px, `letter-spacing: .06em` |
| H4 | 18px | 21px | 22px |
| H5 | 16px | 19px | 18px |
| H6 (eyebrow) | 13px | 16px | 14px, Roboto 600, `letter-spacing: .1em` |

## 4. Forme, spațiere, mișcare

- **Colțuri: 0px peste tot**: butoane, carduri, badge-uri, inputuri. E cea mai vizibilă decizie a lor.
- Container maxim 1350px, padding lateral 15px.
- Butoane: `padding: 13px 24px`, bordură 1px.
- Carduri: bordură 1px `#E5E6E6`, padding 10px pe mobil și 20px de la 768px în sus.
- Tranziții scurte: `.2s ease-out` pe carduri, `.2s–.25s ease-in-out` pe linkuri și dropdown-uri, `.35s` pe acordeoane
  (animat prin `grid-template-rows`).
- Hover pe cardul de produs: `translateY(-5px)` și bordura se închide la `#D9D9D9`. La imagine face swap pe a doua poză.
- Link cu săgeată: săgeata se mută 2px la dreapta la hover.
- Header sticky care se ascunde la scroll în jos și reapare la scroll în sus (`translateY(-100%)`, `.15s`).

## 5. Componente, pagină cu pagină

### Header
1. Bara de anunț pe accent: text negru mic, majuscule („FREE SHIPPING ON ORDERS $199+ | LEARN MORE”). În dreapta:
   telefon expert și „Locate a Dealer”.
2. Header negru: logo, meniu în majuscule (Shop, Resources, ICON Tech, Dealers, News), apoi un **buton „Select Vehicle”
   cu iconiță de mașină și chenar**, apoi un câmp de căutare lat pe gri închis, apoi iconițele cont și coș.
3. O bandă subțire pentru finanțare, care se poate închide.

### Homepage (ordinea secțiunilor)
1. **Hero pe fotografie** cu titlul display pe 2 rânduri și **finder-ul de vehicul integrat în hero**: tab-uri
   „Shop by vehicle / Shop by VIN / Shop by license plate”, apoi 4 select-uri numerotate (1 Year, 2 Make, 3 Model,
   4 Submodel) și un buton accent „Shop your vehicle”. Pe fundal negru semi-transparent, cu colțuri drepte.
2. **„Choose your ride”**: carduri foto înalte, câte unul pe marcă (Toyota, Dodge Ram, Ford, GM, Jeep), cu numele
   jos, în majuscule, peste gradient. Carusel orizontal.
3. **Două plăci mari de categorie** (Suspension / Wheels): fotografie pe toată înălțimea, eyebrow „SHOP”, apoi numele
   categoriei mare.
4. **Top Sellers**: carusel de carduri de produs pe fundal alb.
5. **„Real Owners. Real Upgrades.”**: testimoniale cu poza clientului, citatul, handle-ul de Instagram și modelul
   mașinii. Pe fundal negru.
6. **„Shop by surface”**: 4 plăci foto (Desert, Rock, Trail, Street). E navigare pe stil de folosire, nu pe produs.
7. Banner de brand pe fotografie: eyebrow, titlu, paragraf și CTA accent.
8. **„ICON Tech”**: tehnologiile proprii (imagine mică și nume), apoi articolele (News & Events).
9. Footer.

### Pagina de colecție
1. Hero cu fotografie, breadcrumb deasupra și numele colecției în display.
2. Sub hero, o bandă neagră „Let's find what you need”: 3 butoane accent (by vehicle / VIN / license plate).
3. **Sidebar de filtre** în stânga: grupuri cu titlu mic, majuscule, cu literele distanțate (Availability, Price,
   Sub category, Part type, Lift height, Shock size, Color). Fiecare grup are ± pentru pliere, checkbox-uri pătrate
   cu **numărul de rezultate aliniat la dreapta** și „+ Show more” după primele ~10 opțiuni.
4. Deasupra grilei: „Showing 1–24 of 2338 results” și „Sort by” pe select gri.
5. Grilă de 3 coloane de carduri.

### Cardul de produs
Bordură 1px, colțuri drepte, badge „IN STOCK” (verde, majuscule) sus-stânga. Imaginea e pătrată, pe alb, cu produsul
decupat. Urmează titlul (Roboto 600, 3–4 rânduri), **SKU mic, gri**, apoi **prețul mare, bold** și rata de finanțare.

### Pagina de produs
- Stânga: galerie cu miniaturi pe verticală, apoi secțiunile de conținut: **Overview** (cu „Read more”), **Key
  features** (listă), butoane mici pentru Warranty și Installation guide, **Specifications** (tabel cu 2 coloane pe
  rânduri separate), **Technical notes** (listă de compatibilități), **Fitment** (acordeon).
- Dreapta, **coloana de cumpărare** pe fundal gri deschis: titlu H1 în majuscule, SKU, preț, rate, stoc cu punct
  verde („107 in stock”), „View specs”, apoi **o cutie cu chenar accent „Check if this fits your vehicle / Select
  vehicle”**, un stepper de cantitate (− 1 +) și un buton accent pe toată lățimea, **„Recommended add-ons”** (carduri
  cu checkbox, nume, „+ preț” și poză), iconițele metodelor de plată.

### Footer
Negru, cu textura topografică. Sus: newsletter „Join our pit crew” cu input și buton accent, plus rețelele sociale
în pătrate. Dedesubt: logo, adresă și telefon, apoi 6 coloane de linkuri (Shop pe mărci, Resources, Gallery,
Support, Company). Pe ultimul rând, linkurile legale.

## 6. Ce NU luăm de la ei

- **Popup-ul „Get 5% off”**, deschis pe fiecare pagină chiar la încărcare, peste conținut. În toate capturile acoperă
  cardurile și specificațiile.
- **Bannerul de cookies** peste colțul din dreapta-jos, suprapus peste conținut și peste chat.
- **Trei rânduri de rate Affirm pe fiecare card**. Aglomerează grila și împing prețul în jos. La noi rata merge,
  cel mult, pe pagina de produs.
- **Text gri pe fotografie**: titlul din hero și textul testimonialelor au contrast slab.
- **Mobilul iese din ecran**: la 390px titlul hero, header-ul și bara de anunț se taie la dreapta, deci pagina are
  scroll orizontal.

## 7. Traducerea pentru eMUD

Ce preluăm aproape identic, pentru că sunt decizii de structură, nu de marcă:

1. **Finder-ul în hero**, cu pași numerotați (Marcă → Model → Motorizare → An) și tab-uri pentru alte metode de
   căutare (VIN, număr de înmatriculare, când le avem). Avem deja finder-ul sub hero și `vehicle-picker`, deci îl
   ridicăm în hero și îl facem piesa principală a paginii.
2. **„Alege-ți mașina”**: plăci foto pe mărci.
3. **„După teren”**: navigare pe stil de folosire (off-road, drum, tractare, overland).
4. **Cutia „Se potrivește pe mașina ta?”** în coloana de cumpărare, cu vehiculul salvat deja completat.
5. **Recomandările bifabile** („+ preț”) chiar sub butonul de coș.
6. **Filtrele cu număr de rezultate**, pliabile, cu „Arată mai multe”.
7. **Colțuri drepte, borduri în loc de umbre, butoane în majuscule cu litere distanțate, eyebrow + titlu mare.**
8. **Secțiuni alternante negru / alb** și o textură topografică proprie, desenată de noi.

Ce facem diferit, ca eMUD să nu arate ca o clonă ICON:

- **Accentul**: alegem alt ton decât galbenul lor. Variante de discutat în machete: portocaliu semnal, nisip/khaki
  militar sau un galben mai rece. Păstrăm doar regula: un singur accent, cu text negru pe el.
- **Fontul de titluri**: în loc de Russo One, un font condensat tehnic, de exemplu Barlow Condensed, Saira Condensed
  sau Chakra Petch. Obligatoriu îl verificăm pe ș, ț, ă, î, â înainte să-l alegem. Textul rămâne pe Inter, pe care
  îl avem deja.
- **Fotografia** e a noastră (furnizori, clienți, UGC), nu a lor.

Punctul de pornire tehnic: acum `resources/css/app.css` definește în `@theme` doar fontul, iar culorile sunt clase
`stone-*` scrise direct în view-uri. Primul pas e un set de variabile semantice în `@theme` (fundal, text, accent,
bordură, secțiune închisă, colț), după valorile de mai sus. Abia după asta refacem componentele.

## 8. Tokenii ICON ca referință, în format Tailwind v4

```css
@theme {
    --color-ink: #000104;
    --color-accent: #FFC62F;          /* de înlocuit cu accentul eMUD */
    --color-accent-hover: #D9A828;
    --color-accent-active: #BF9423;
    --color-line: #E5E6E6;
    --color-surface-dark-1: #141518;
    --color-surface-dark-2: #1A1A1D;
    --color-surface-dark-3: #26272A;
    --color-danger: #DE3535;
    --color-success: #198754;

    --font-display: 'Russo One', sans-serif; /* de înlocuit, vezi secțiunea 7 */
    --font-sans: Inter, ui-sans-serif, system-ui, sans-serif;

    --radius-none: 0px;
    --container-shop: 1350px;
}
```
