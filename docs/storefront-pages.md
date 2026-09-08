# Storefront and customer account — page inventory

Status as of the money-exactness work. "Built" means the page exists, is routed and is covered
by tests. "Missing" means it does not exist yet.

## Public storefront

| Page | Route | Status |
|---|---|---|
| Home with vehicle picker | `/` | Built |
| Category listing | `/categorie/{full_path}` | Built |
| Product detail | `/produs/{slug}` | Built |
| Search | `/cauta` | Built |
| Cart | `/cos` | Built |
| Checkout | `/finalizare` | Built |
| Order confirmation | `/comanda/{token}` | Built |
| Guides index / article | `/ghiduri`, `/ghiduri/{slug}` | Built |
| Contact | `/contact` | Built |
| Static/legal pages | `/{slug}` | Built |
| sitemap.xml, robots.txt | — | Built |
| **Brand page** | `/brand/{slug}` | **Missing** |
| **Vehicle landing** (make/model/generation → compatible parts) | `/masina/{make}/{model}` | **Missing** |
| Service directory | `/service-auto` | Built |
| Service detail | `/service-auto/{slug}` | Built |
| **Comparison** | `/compara` | **Missing** |
| **Offers / promotions** | `/oferte` | **Missing** |

## Customer account

| Page | Route | Status |
|---|---|---|
| Register / sign in / sign out | `/cont/inregistrare`, `/cont/autentificare` | Built |
| Password recovery + reset | `/cont/parola-uitata`, `/cont/reseteaza-parola/{token}` | Built |
| Dashboard | `/cont` | Built |
| Garage list | `/cont/garaj` | Built |
| Order history | `/cont/comenzi` | Built |
| Profile and password | `/cont/date` | Built |
| Vehicle detail (technical data, service plan, parts history) | `/cont/garaj/{vehicleId}` | Built |
| Maintenance reminders (ITP, RCA, oil, filters, timing belt) | part of vehicle detail | Built |
| Wishlist, global and per vehicle | `/cont/favorite` | Built |
| **Addresses** | `/cont/adrese` | **Missing** |
| **Saved searches and alerts** | `/cont/alerte` | **Missing** |
| **Returns / RMA** | `/cont/retururi` | **Missing** |
| **Consents and data export** (GDPR) | `/cont/confidentialitate` | **Missing** |

## Admin

Built: catalog explorer, database schema, quality, sources, imports, conflicts, unresolved
relations, supplier matching, catalog API, products, categories, attributes, brands, media,
vehicles, suppliers, supplier syncs, static pages, articles, orders, commerce settings.

Also built: service directory management, article block editor.

Still missing: **wishlist/alert insight**, **returns handling**, **customer records**.

---

# What the missing customer-account pages need

## Vehicle detail — `/cont/garaj/{vehicle}`

The garage currently stores make, model, generation, year, nickname and plate. A vehicle page
turns that into something a customer returns to.

**Technical data.** Read from the canonical catalogue for the linked configuration: engine,
capacity, power, fuel, drivetrain, body. Nothing is invented; where the catalogue has no
configuration linked, the page says so rather than showing blanks.

**Service plan.** Dated reminders the customer sets or that are derived from mileage: ITP, RCA,
oil and filters, timing belt, brake fluid, coolant. Each carries a due date or a due mileage,
and the page ranks them by urgency. Overdue items must read as overdue, not as a quiet row.

**Parts history.** Every order line the customer bought while this vehicle was their active one,
so "what oil filter did I put on last time" has an answer. This needs the active vehicle to be
recorded on the order line at checkout, which it currently is not.

**Wishlist for this vehicle.** Saved products scoped to the car rather than to the account, so a
customer with two vehicles does not get one undifferentiated list.

## Wishlist — `/cont/favorite`

Global list plus per-vehicle lists. A saved product keeps the compatibility verdict it had when
saved, so a later catalogue change that makes it incompatible is visible rather than silent.

## Service directory — `/service-auto`

A public directory of Romanian workshops, filterable by county, city, speciality (4x4,
suspension, diagnostics, tyres) and by whether they fit parts bought here. Positions and
recommendations are sold, so the model needs an explicit promotion tier and an audit of who paid
for what — a paid placement that cannot be distinguished from an editorial one is both a legal
and a trust problem.

---

# Interface work

## Mega menu — built

The catalogue used to render as a flat row of top-level categories. It is now one mega menu
covering the whole parts catalogue, pyramidal, at least three levels: a top-level column list,
second-level groups under each, third-level links. Driven by the existing category tree, with
`is_visible_in_menu` and `position` respected, and cached, because rendering three levels on
every page load is a query per level otherwise.

## Article builder — built

Articles used to hold one HTML blob. They now carry ordered, typed blocks:

- rich text
- image and gallery
- video embed (YouTube/Vimeo, resolved to a privacy-preserving embed)
- **parts carousel**, inserted automatically from a category, a manual product list, or from the
  vehicle the article is linked to
- callout / warning
- step list for how-to guides

An article also needs to be **linked to a vehicle model or generation**, so a guide about the
Duster can surface on that vehicle's landing page, and so its parts carousel can resolve to
parts compatible with that model. Both the editor and the renderer are new work.

---

# Remaining after this pass

- addresses, saved searches and alerts, returns/RMA, GDPR consents and data export in the
  customer account;
- brand pages, vehicle landing pages, comparison and offers on the storefront;
- returns handling and customer records in the admin;
- known commerce defects still open in the handoff backlog: attribute options rebuilt on every
  save, the variant matrix, media primary/reordering.
