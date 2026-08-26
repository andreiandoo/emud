# eMUD — handoff pentru Claude Code și lista completă de lucru rămasă

> Ultima actualizare: 26 august 2026  
> Repository: `andreiandoo/emud`  
> Stack obligatoriu: PHP 8.4, Laravel 12, Livewire 4, Blade, Tailwind CSS 4, Alpine.js, PostgreSQL 17, Redis 8  
> Tip proiect: magazin online 4x4, off-road, mudding și overlanding, fără stoc propriu, bazat pe dropshipping multi-furnizor

## 1. Scopul acestui document

Acesta este documentul principal de handoff pentru continuarea dezvoltării în Claude Code, în mediul local.

Documentul trebuie folosit ca backlog executabil și actualizat după fiecare etapă. O bifă `[x]` înseamnă că funcționalitatea există în repository și a fost verificată cel puțin prin teste locale. O bifă `[ ]` înseamnă că funcționalitatea lipsește, este doar parțială sau există numai ca structură de date/prototip.

Important: existența unei migrații, a unui model sau a unei clase gateway nu înseamnă că fluxul este finalizat. Stripe, NETOPIA și FAN Courier sunt în prezent schelete funcționale testate cu răspunsuri HTTP simulate. Nu sunt încă integrări sandbox end-to-end și nu trebuie prezentate drept integrări de producție.

## 2. Viziunea și cerințele nemodificabile

- Magazinul vinde exclusiv produse și accesorii pentru 4x4, off-road, mudding și overlanding.
- Magazinul nu se bazează pe stoc propriu. Catalogul, costurile, prețurile recomandate și stocurile sunt preluate automat de la furnizori.
- Un produs eMUD este canonic și poate avea oferte de la mai mulți furnizori.
- Furnizorii nu trebuie să suprascrie direct conținutul editorial aprobat al produsului canonic.
- Stocul, prețul și disponibilitatea trebuie reverificate periodic și înainte de plasarea comenzii.
- Comenzile sunt dropshipping și pot conține produse provenite de la furnizori diferiți.
- Clientul trebuie să poată căuta după mașină, model, generație, an, motor și caracteristici tehnice.
- Clientul își poate salva una sau mai multe mașini și poate personaliza rezultatele pentru mașina activă.
- Clientul poate salva căutări și poate primi alerte de stoc și preț.
- Newsletterele trebuie să poată fi personalizate după mașinile clientului, interese și consimțământ.
- Magazinul include o comunitate online/offline și evenimente cu înscriere.
- Interfața publică finală se proiectează ulterior, dar serviciile de domeniu și fluxurile trebuie să fie complete și testabile înainte de design.
- Adminul rămâne Blade + Livewire + Tailwind + Alpine.js. Nu se introduce un SPA separat fără o decizie explicită.
- PostgreSQL este sursa de adevăr; Redis este folosit pentru cozi, cache, lock-uri, sesiuni și scheduler.
- Toate credentialele externe sunt criptate și nu apar în Git, loguri, excepții sau răspunsuri către browser.

## 3. Ce există deja în repository

### 3.1 Infrastructură

- [x] Proiect Laravel cu PHP 8.4.
- [x] Livewire, Blade, Tailwind CSS și Alpine.js.
- [x] PostgreSQL, Redis, Nginx, PHP-FPM și containere pentru aplicație, worker și scheduler.
- [x] `composer.lock` pentru builduri PHP reproductibile.
- [x] Login separat pentru administrare și middleware de administrator.
- [x] Teste Laravel de bază și Laravel Pint.

### 3.2 Catalog și compatibilitate auto

- [x] Modele și tabele pentru branduri, categorii ierarhice, atribute, opțiuni, produse, variante și media.
- [x] Categorii cu `parent_id`, adâncime, ordine, cale completă, descriere, imagine și câmpuri SEO de bază.
- [x] CRUD admin de bază pentru categorii și subcategorii.
- [x] CRUD admin de bază pentru filtre/atribute și opțiuni.
- [x] Asocierea atributelor la categorii.
- [x] CRUD admin de bază pentru branduri.
- [x] Editor de produs cu date generale, categorii, brand, variante, atribute, media, fitment și SEO.
- [x] Structură auto `make → model → generation → engine/configuration`.
- [x] Structură de compatibilitate produs/variantă cu mașina.
- [x] Structură pentru garajul clientului.

### 3.3 Furnizori și dropshipping

- [x] Modele pentru furnizori, produse furnizor, oferte, istoric de ofertă, mapări și rulări de sincronizare.
- [x] Connector generic HTTP pentru JSON, XML și CSV.
- [x] Job de sincronizare în coadă și scheduler pentru catalog, preț și stoc.
- [x] Staging separat de catalogul canonic.
- [x] Credentiale furnizor criptate la nivel de model.
- [x] Importer de bază și test de import simulat.

### 3.4 Comerț, conținut și comunitate

- [x] Modele pentru coș, comenzi, linii de comandă, adrese și istoric de status.
- [x] Modele pentru furnizori de plată, tranzacții și evenimente webhook.
- [x] Modele pentru curieri, metode de livrare, expedieri și evenimente de tracking.
- [x] Modele și admin de bază pentru articole și categorii editoriale.
- [x] Bibliotecă media de bază.
- [x] Modele pentru căutări salvate, alerte de produs și preferințe de notificare.
- [x] Modele pentru evenimentele comunității și înscrieri.
- [x] Panouri admin de bază pentru comenzi și setări comerciale.

### 3.5 Defecte și riscuri cunoscute în implementarea actuală

Acestea trebuie tratate ca buguri sau datorie tehnică prioritară, nu ca funcționalități finalizate:

- [ ] `PaymentService` face apelul HTTP extern în interiorul unei tranzacții DB; mută I/O-ul extern în afara tranzacției și păstrează idempotency/recovery.
- [ ] `CheckoutService` marchează coșul `converted` înainte ca inițierea plății să fie garantată; adaugă stare recuperabilă și retry sigur.
- [ ] Verificarea IPN NETOPIA este insuficientă și trebuie înlocuită cu validarea criptografică oficială.
- [ ] Deduplificarea webhook NETOPIA nu trebuie făcută numai după `ntpID`; același payment poate primi mai multe actualizări legitime de status.
- [ ] Parserul manual Stripe Signature trebuie înlocuit cu SDK-ul oficial și trebuie să accepte rotația/mai multe semnături valide.
- [ ] Adapterul FAN folosește endpointuri/payloaduri presupuse și nu reprezintă contractul SelfAWB verificat.
- [ ] Testele Stripe și FAN folosesc `Http::fake`; nu există test NETOPIA complet și nici teste webhook.
- [ ] Salvarea unui atribut șterge/recreează opțiunile, ceea ce poate rupe referințele valorilor existente.
- [ ] Atributele definitorii de variantă există ca flag, dar editorul nu construiește și nu salvează matricea de atribute per variantă.
- [ ] Categoriile editoriale au câmpuri suplimentare în DB, dar UI-ul admin editează aproape numai numele.
- [ ] Media produsului nu are încă selecție primary, reordonare și editarea completă a metadatelor.
- [ ] Tabelele permit unele inconsistențe: mai mulți provideri `is_default`, mai multe mașini `is_primary` și duplicate de cart item când `variant_id` este `NULL`; adaugă constrângeri/indexuri PostgreSQL adecvate.
- [ ] Verifică tipurile coloanelor pentru toate casturile `encrypted:array`; ciphertextul trebuie stocat într-o coloană text sigură, nu tratat ca structură JSON interogabilă.
- [ ] Câmpurile de bani și calculele folosesc în mai multe locuri conversii `float`.
- [ ] HTML-ul articolelor și produselor nu are încă o politică completă de sanitizare.
- [ ] Loginul admin nu are încă rate limiting explicit, 2FA sau permisiuni granulare.
- [ ] README-ul descrie unele capabilități ca fiind disponibile deși sunt numai infrastructură/prototip; corectează documentația odată cu implementarea.

## 4. Reguli de lucru pentru Claude Code

1. Citește mai întâi `README.md` și toate documentele din `docs/`.
2. Verifică starea reală a codului înainte de a bifa un element. Nu considera README-ul dovadă de implementare.
3. Nu modifica migrațiile deja aplicate pe medii partajate; creează migrații incrementale.
4. Folosește PostgreSQL în testele de integrare care depind de JSONB, GIN, `pg_trgm`, locking sau comportament SQL specific.
5. Folosește bani în mod determinist. Nu efectua aritmetică financiară cu `float`.
6. Orice apel extern trebuie să aibă timeout, retry controlat, idempotency, log sanitizat și tratare explicită a erorilor.
7. Orice webhook trebuie să păstreze raw body, să verifice semnătura înainte de procesare și să fie idempotent.
8. Nu șterge payloadurile brute ale furnizorilor și istoricul ofertelor fără o politică explicită de retenție.
9. Nu publica automat produse importate. Produsele noi intră implicit în `review`.
10. Nu activa automat un procesator, curier sau furnizor după salvarea credentialelor.
11. Adaugă teste pentru happy path, failure path, duplicate/retry și concurență.
12. Rulează înaintea fiecărui commit: teste, Pint, analiză statică și build frontend.
13. Fă commituri mici, tematice, cu mesaje clare.
14. Actualizează acest document după fiecare etapă și notează commitul relevant în secțiunea 22.

## 5. Etapa 0 — audit local și stabilizarea mediului

- [ ] Clonează/pull ultima versiune din `main` și verifică să nu existe modificări locale necunoscute.
- [ ] Verifică și aliniază versiunile reale: PHP 8.4, extensii PHP, Composer, Node, PostgreSQL și Redis.
- [ ] Rulează `composer install` și `npm ci` exclusiv din lockfiles.
- [ ] Rulează `npm run build` și elimină orice dependență de un manifest Vite simulat.
- [ ] Rulează `php artisan migrate:fresh --seed` pe PostgreSQL, nu doar SQLite.
- [ ] Rulează workerul Redis și schedulerul și confirmă procesarea joburilor.
- [ ] Verifică volumele Docker, permisiunile pentru `storage/`, linkul public și uploadurile.
- [ ] Adaugă un script/comandă unică pentru setup local reproductibil.
- [ ] Adaugă `.env.testing.example` fără secrete.
- [ ] Confirmă că `.env`, certificatele, cheile și fișierele de credentiale sunt ignorate de Git.
- [ ] Adaugă verificare de health pentru PostgreSQL, Redis, queue worker și scheduler.
- [ ] Adaugă analiză statică PHPStan/Larastan la nivel rezonabil și include-o în CI.
- [ ] Configurează CI să ruleze Composer validate, migration, seed, tests, Pint, analiză statică și build Vite.

### Criteriu de acceptanță

Un developer poate porni proiectul de la zero folosind documentația, poate rula toate serviciile și obține aceeași suită verde local și în CI.

## 6. Etapa 1 — închiderea completă a administrării catalogului

### 6.1 Categorii și subcategorii

- [x] Arbore cu adâncime nelimitată.
- [x] Creare, editare, mutare și ordonare de bază.
- [x] Descriere, imagine și SEO de bază.
- [ ] Compară seederul actual cu taxonomia de referință cerută de owner (`emudding.ro/map` și subcategoriile sale), documentează diferențele și obține aprobarea structurii canonice înainte de importul primului catalog.
- [ ] Previne mutarea unei categorii sub ea însăși sau sub un descendent, inclusiv în condiții concurente.
- [ ] Regenerează atomic `full_path` și `depth` pentru întreg subarborele.
- [ ] Definește comportamentul slugului când categoria este mutată sau redenumită.
- [ ] Păstrează redirecturi 301 pentru URL-urile publicate anterior.
- [ ] Adaugă imagine principală cu alt text, titlu, focal point și posibilitate de eliminare/înlocuire.
- [ ] Adaugă imagine Open Graph separată și preview social.
- [ ] Adaugă icon controlat, nu text liber nesanitizat.
- [ ] Adaugă stare vizibilă: draft/activ/inactiv și moștenirea vizibilității de la părinte.
- [ ] Adaugă preview pentru URL și SEO.
- [ ] Adaugă acțiuni bulk: activare, dezactivare, mutare, export.
- [ ] Adaugă import/export CSV pentru taxonomie.
- [ ] Definește ștergerea: blocare dacă are copii/produse sau mutare explicită; fără pierderi accidentale.
- [ ] Testează arbori adânci, mutări, coliziuni de slug și ștergeri.

### 6.2 Filtre și atribute

- [x] Tipuri: text, număr, boolean, select, multiselect și culoare.
- [x] Opțiuni, unitate, help text și asociere la categorii.
- [x] Flaguri pentru filtrare, comparare, obligativitate și definirea variantei.
- [ ] Nu mai șterge și recrea toate opțiunile la fiecare salvare; păstrează ID-urile opțiunilor folosite de produse.
- [ ] Adaugă CRUD individual și reordonare pentru opțiuni.
- [ ] Adaugă sinonime și valori normalizate pentru maparea feedurilor furnizorilor.
- [ ] Adaugă metadata pentru culoare: HEX, imagine/textură și denumire publică.
- [ ] Adaugă validări configurabile: min, max, pas, regex, lungime și precizie.
- [ ] Adaugă suport explicit pentru intervale numerice și unități convertibile, unde este necesar.
- [ ] Definește moștenirea atributelor de la categoriile părinte.
- [ ] Detectează și rezolvă conflictele când produsul are mai multe categorii.
- [ ] Separă atributele produsului de atributele fiecărei variante.
- [ ] Implementează matricea de variante pe atributele `is_variant_defining`.
- [ ] Previne combinațiile duplicate de variante.
- [ ] Adaugă validare că atributele obligatorii sunt completate înainte de publicare.
- [ ] Adaugă preview al filtrelor publice și numărul de produse per valoare.
- [ ] Testează schimbarea tipului unui atribut deja utilizat.

### 6.3 Branduri

- [x] CRUD de bază, descriere, website și logo.
- [ ] SEO complet pentru pagină de brand.
- [ ] Alt text și administrare completă logo.
- [ ] Redirecturi la schimbarea slugului.
- [ ] Blocare/confirmare la ștergerea unui brand utilizat.
- [ ] Câmpuri opționale pentru date producător, țară, garanție implicită și contact suport.

### 6.4 Produse și variante

- [x] Date generale, categorii, brand, descrieri, status, SKU/MPN/EAN, preț, greutate și garanție.
- [x] Variante multiple.
- [x] Atribute de produs.
- [x] Media și fitment de bază.
- [x] SEO de bază.
- [ ] Introdu o mașină de stări explicită: `draft`, `review`, `published`, `hidden`, `discontinued`, `archived`.
- [ ] Separă motivul indisponibilității de starea editorială.
- [ ] Validează toate condițiile înainte de publicare: categorie, variantă activă, SKU, preț, imagine și date obligatorii.
- [ ] Adaugă dimensiuni per produs și variantă în UI.
- [ ] Adaugă clasa de expediere, produs voluminos, fragil, ADR/restricții și număr estimat de colete.
- [ ] Adaugă cod tarifar/HS și țara de origine dacă vor exista furnizori externi.
- [ ] Adaugă politica de garanție și documente/fișe tehnice descărcabile.
- [ ] Adaugă relații: produse similare, alternative, accesorii recomandate, necesare montajului și incompatibilități.
- [ ] Adaugă kituri/bundle-uri fără a pierde trasabilitatea componentelor.
- [ ] Adaugă bulk edit și import/export pentru produse.
- [ ] Adaugă istoric/audit pentru modificările manuale importante.
- [ ] Afișează în admin sursa fiecărui câmp: manual, furnizor, regulă sau calcul.
- [ ] Definește reguli de precedence: ce câmp poate fi actualizat automat și ce câmp rămâne blocat editorial.
- [ ] Implementează preview public înainte de publicare.
- [ ] Adaugă duplicare produs și variantă.
- [ ] Adaugă protecție la SKU/EAN/MPN duplicat și mecanism de rezolvare.
- [ ] Adaugă soft-delete/restore complet pentru produs și variante.
- [ ] Testează publicarea, arhivarea, restaurarea și relațiile produsului.

### 6.5 Media

- [x] Upload și bibliotecă media de bază.
- [ ] Validare MIME reală, dimensiune, extensie, rezoluție și protecție împotriva fișierelor periculoase.
- [ ] Generare asincronă de thumbnailuri și formate responsive WebP/AVIF.
- [ ] Păstrarea originalului și a metadatelor.
- [ ] Imagine principală per produs și imagine opțională per variantă.
- [ ] Reordonare drag-and-drop.
- [ ] Editare alt text, titlu, caption și focal point.
- [ ] Deduplificare pe checksum.
- [ ] Import sigur de imagini remote de la furnizor, cu timeout și validare SSRF.
- [ ] Detectarea linkurilor media rupte.
- [ ] Ștergere doar dacă assetul nu mai este utilizat sau confirmare explicită.
- [ ] Strategie de storage local/S3 compatibil și CDN, configurabilă prin environment.

## 7. Etapa 2 — baza auto și motorul de compatibilitate

- [x] Tabele pentru marcă, model, generație, motor și configurație.
- [x] Fitment la nivel de produs/variantă.
- [ ] CRUD admin complet pentru toate nivelurile auto; ecranul actual nu acoperă administrarea completă.
- [ ] Import/export pentru baza auto.
- [ ] Definirea unei surse de date auto autorizate și a licenței de utilizare.
- [ ] Normalizare pentru denumiri alternative, cod șasiu, facelift, piețe și intervale de fabricație.
- [ ] Câmpuri relevante: caroserie, ampatament, număr uși, transmisie, tracțiune, motor, combustibil și putere.
- [ ] Reguli fitment cu includeri și excluderi.
- [ ] Fitment per variantă, nu doar per produs.
- [ ] Reguli pentru produse universale și condiții de montaj.
- [ ] Semnalizare clară: compatibil, compatibil cu modificări, necunoscut, incompatibil.
- [ ] Detectarea regulilor contradictorii.
- [ ] Motor de căutare `fits(vehicle, product/variant)` cu teste unitare exhaustive.
- [ ] Indexuri PostgreSQL pentru interogările reale de fitment.
- [ ] Pagină admin de diagnostic: de ce un produs este sau nu compatibil cu o mașină.
- [ ] Flux de corectare manuală și audit al sursei fitmentului.
- [ ] Posibilitate de feedback client privind compatibilitatea, moderat înainte de aplicare.

## 8. Etapa 3 — cont client, personalizare și comunitate

### 8.1 Cont client

- [ ] Înregistrare, login, logout și recuperare parolă pentru clienți.
- [ ] Verificare email.
- [ ] Protecție anti-brute-force și rate limiting.
- [ ] Administrarea profilului și a adreselor.
- [ ] Istoric comenzi, plăți, facturi, expedieri și tracking.
- [ ] Repetarea unei comenzi, cu reverificare integrală a produselor.
- [ ] Cereri de retur/RMA din cont.
- [ ] Export date personale și ștergere/anonymizare conform politicii definite.

### 8.2 Garajul clientului

- [x] Structura de date pentru mai multe mașini.
- [ ] UI client pentru adăugare/editare/ștergere.
- [ ] O singură mașină principală per utilizator, garantată tranzacțional.
- [ ] Selectarea mașinii active în sesiune.
- [ ] Aplicarea/dezactivarea filtrului de compatibilitate.
- [ ] Modificări ale mașinii: lift, dimensiune roți, suspensie și alte date utile fitmentului.
- [ ] Validare și protejare VIN/număr de înmatriculare ca date personale.

### 8.3 Alerte și newsletter

- [x] Modele pentru căutări salvate, alerte și preferințe.
- [x] Job de bază pentru evaluarea alertelor.
- [ ] UI pentru salvarea unei căutări și administrarea alertelor.
- [ ] Alerte separate: reapariție în stoc, scădere sub preț, produs nou compatibil și modificare semnificativă de preț.
- [ ] Debounce/cooldown pentru a evita notificările repetate.
- [ ] Linkuri semnate de dezabonare și preference center.
- [ ] Consimțământ separat pentru mesaje tranzacționale și marketing.
- [ ] Integrare ulterioară cu un furnizor de email; să existe contract intern independent de provider.
- [ ] Segmentare newsletter după mașină, categorii, căutări și comportament permis prin consimțământ.
- [ ] Bounce/complaint handling și suppression list.

### 8.4 Comunitate și evenimente

- [x] Structură pentru evenimente online/offline și înscrieri.
- [ ] Admin complet pentru evenimente: imagine, galerie, descriere, locație/hartă, perioadă, capacitate, status și SEO.
- [ ] Publicare programată și anulare eveniment.
- [ ] Înscriere client cu mașina aleasă și număr de invitați.
- [ ] Listă de așteptare și limită de capacitate tranzacțională.
- [ ] Emailuri de confirmare, reminder, modificare și anulare.
- [ ] Check-in opțional și export participanți.
- [ ] Moderare, reguli comunitate și consimțământ pentru imagini/comunicări, dacă este cazul.

## 9. Etapa 4 — commerce foundation înaintea integrărilor externe

Această etapă trebuie închisă înainte ca Stripe, NETOPIA, FAN Courier sau primul furnizor să fie considerate integrate.

### 9.1 Money, TVA și prețuri

- [ ] Elimină aritmetica financiară cu `float`; folosește minor units sau obiect Money/Decimal determinist.
- [ ] Definește dacă prețurile din catalog sunt cu sau fără TVA.
- [ ] Definește cota TVA per produs/ofertă și data de la care se aplică.
- [ ] Calculează și persistă subtotal net, TVA, discount, transport și total.
- [ ] Definește regulile de rotunjire la linie și comandă.
- [ ] Testează diferențele de un ban și cantități multiple.
- [ ] Definește moneda magazinului și comportamentul pentru oferte în alte monede.
- [ ] Adaugă sursă și timestamp pentru cursul valutar, dacă este necesar.
- [ ] Validează că totalul trimis procesatorului este identic cu totalul comenzii.

### 9.2 Coș și checkout

- [x] Persistență de bază pentru coș și `CheckoutService`.
- [ ] API/Livewire public pentru adăugare, modificare cantitate și ștergere.
- [ ] Merge coș anonim cu coșul utilizatorului după autentificare.
- [ ] Expirarea și curățarea coșurilor abandonate.
- [ ] Recalculare server-side; browserul nu este sursă de adevăr pentru preț.
- [ ] Reverificare preț, stoc, furnizor, cantitate minimă și lead time înainte de comandă.
- [ ] Lock/strategie de concurență pentru ultimele bucăți disponibile.
- [ ] Mesaj și aprobare client dacă prețul s-a schimbat.
- [ ] Blocare comandă dacă oferta este expirată sau furnizorul este indisponibil.
- [ ] Validare adresă, telefon, județ, localitate și cod poștal.
- [ ] Persoană fizică/persoană juridică, CUI și date de facturare.
- [ ] Selectarea metodei de livrare pe baza adresei, greutății, dimensiunilor și regulilor.
- [ ] Termeni și condiții, politica de retur și consimțămintele necesare, cu versiunea acceptată.
- [ ] Protecție împotriva submitului dublu.
- [ ] Pagină de retur/succes/eșec care nu marchează singură plata drept reușită.
- [ ] Recuperare sigură a checkoutului întrerupt.
- [ ] Teste end-to-end fără designul final.

### 9.3 Comenzi și state machine

- [ ] Definește enumuri și tranziții permise pentru comandă, plată, fulfillment și dropship.
- [ ] Nu permite tranziții arbitrare din admin.
- [ ] Înregistrează toate schimbările în istoric cu actor, motiv și timestamp.
- [ ] Snapshot complet pe linie: produs, variantă, taxe, preț, cost, furnizor, fitment și promisiune de livrare.
- [ ] Număr de comandă predictibil operațional, dar imposibil de ghicit pentru acces public.
- [ ] Idempotency pentru creare comandă și inițiere plată.
- [ ] Anulare completă și parțială.
- [ ] Refund complet și parțial.
- [ ] Comenzi ramburs și actualizarea plății la livrare/retur.
- [ ] Note interne separate de nota clientului.
- [ ] Emailuri tranzacționale pentru confirmare, plată, expediere, anulare și refund.
- [ ] Admin cu timeline unic pentru comandă, plăți, furnizori și expedieri.

### 9.4 Alocarea furnizorului și purchase orders

- [ ] Selectează oferta eligibilă după stoc, prospețime, cost total, prioritate, SLA și restricții.
- [ ] Nu selecta doar cel mai mic cost dacă transportul/lead time-ul produce o ofertă mai slabă.
- [ ] Persistă decizia și explicația selecției.
- [ ] Permite override manual auditat.
- [ ] Împarte comanda clientului în purchase orders per furnizor.
- [ ] Modele noi recomandate: `supplier_orders`, `supplier_order_items`, `supplier_order_events`.
- [ ] Statusuri: draft, queued, submitted, acknowledged, partially_confirmed, confirmed, rejected, shipped, cancelled.
- [ ] Transmitere idempotentă către furnizor prin API/email/fișier, conform capabilităților acestuia.
- [ ] Confirmare stoc după comandă și flux de excepție dacă furnizorul refuză.
- [ ] Re-alocare la alt furnizor cu recalcularea marjei și aprobări.
- [ ] Gestionare split shipment și tracking multiplu.
- [ ] Reconciliere între costul estimat și factura furnizorului.
- [ ] Alertă pentru marjă negativă sau sub prag.

### 9.5 Discounturi, promoții și reguli comerciale

- [ ] Modele pentru promoții și coduri de discount, cu interval, stare și prioritate.
- [ ] Discount fix/procentual, pe comandă, categorie, brand, produs sau variantă.
- [ ] Prag minim, utilizare unică, limită totală și limită per client.
- [ ] Excluderi și prevenirea combinațiilor nepermise.
- [ ] Transport gratuit ca regulă comercială, nu doar câmp static pe metoda de livrare.
- [ ] Preț promoțional cu început/sfârșit și revenire automată.
- [ ] Persistă explicația fiecărui discount pe comandă.
- [ ] Recalculează discountul la orice modificare de coș și înainte de plată.
- [ ] Testează rotunjirea, cumularea, expirarea și concurența limitelor de utilizare.

### 9.6 Administrare comercială și clienți

- [ ] Admin clienți cu profil, mașini, adrese, comenzi, alerte și consimțăminte.
- [ ] Căutare și filtre comenzi după status, plată, fulfillment, furnizor și perioadă.
- [ ] Acțiuni bulk sigure și export CSV pentru operațiuni.
- [ ] Timeline complet al comenzii și acțiuni permise în funcție de status.
- [ ] Vizualizare tranzacții, webhookuri, erori, refunduri și posibilitate controlată de retry/replay.
- [ ] Vizualizare purchase orders și excepții furnizor.
- [ ] Dashboard cu vânzări, marjă, comenzi problematice, produse stale și joburi eșuate.
- [ ] Setări magazin: identitate companie, TVA, monedă, emailuri, politici și numerotări.
- [ ] Templateuri de email editabile/versionate fără a permite cod executabil.

## 10. Etapa 5 — infrastructura comună pentru procesatoare și curieri

- [x] Contracte PHP comune pentru plăți și livrare.
- [x] Credentiale criptate în baza de date.
- [ ] Credentiale distincte pentru sandbox și live; schimbarea modului nu trebuie să reutilizeze accidental cheia celuilalt mediu.
- [ ] Configurare controlată a endpointurilor; URL-urile arbitrare introduse în admin trebuie validate pentru SSRF.
- [ ] Buton `Testează conexiunea` pentru fiecare provider.
- [ ] Nu permite activarea dacă testul de configurare eșuează.
- [ ] Nu permite provider implicit inactiv sau neconfigurat.
- [ ] Permite golirea/rotația explicită a unei credentiale fără afișarea valorii curente.
- [ ] Audit pentru modificarea credentialelor și modului, fără logarea secretelor.
- [ ] Circuit breaker/retry controlat pentru întreruperi externe.
- [ ] Idempotency local și extern pentru toate operațiunile mutabile.
- [ ] Queue dedicată pentru webhooks, plăți și expedieri.
- [ ] Dead-letter/failure handling și posibilitate de replay din admin.
- [ ] Correlation ID între comandă, tranzacție, webhook și shipment.
- [ ] Loguri structurate și sanitizate.
- [ ] Metrici și alerte pentru rată de erori, latență și webhookuri blocate.
- [ ] Health/status vizibil în admin.

## 11. Etapa 6 — Stripe complet

Starea actuală: se creează un Payment Intent prin HTTP și există verificare manuală de webhook. Nu există flux client-side, refund sau validare sandbox end-to-end.

- [ ] Decide și documentează integrarea: Stripe Checkout Sessions sau Payment Element + Payment Intents. Pentru controlul total al checkoutului, recomandarea curentă este Payment Element + Payment Intents.
- [ ] Instalează și folosește SDK-ul oficial Stripe pentru PHP și Stripe.js.
- [ ] Fixează versiunea API Stripe utilizată.
- [ ] Creează endpoint/serviciu sigur pentru client secret.
- [ ] Integrează Payment Element într-o pagină tehnică minimă de checkout, înainte de designul final.
- [ ] Confirmă plata și gestionează 3DS/SCA.
- [ ] Nu loga și nu include `client_secret` în URL.
- [ ] Verifică webhookul folosind SDK-ul oficial și raw body.
- [ ] Acceptă rotația secretului și mai multe semnături `v1` valide.
- [ ] Procesează cel puțin: succeeded, processing, payment_failed, canceled și refund events.
- [ ] Verifică amount, currency, metadata și order înainte de marcarea plății.
- [ ] Tratează evenimente out-of-order și duplicate.
- [ ] Implementează refund complet/parțial din admin cu idempotency.
- [ ] Persistă PaymentIntent, Charge și refund references.
- [ ] Afișează erori utile clientului fără date sensibile.
- [ ] Adaugă reconciliation/status refresh pentru cazurile în care webhookul întârzie.
- [ ] Testează cu Stripe CLI și cardurile de test: succes, refuz, 3DS, processing, duplicate webhook și refund.
- [ ] Rulează un checkout sandbox end-to-end înainte de activare live.

## 12. Etapa 7 — NETOPIA Payments complet

Starea actuală: există request JSON pentru start și o verificare IPN insuficientă bazată pe compararea headerului Authorization cu API key. Aceasta nu este o validare completă a IPN-ului.

- [ ] Creează/configurează Point of Sale sandbox în NETOPIA.
- [ ] Folosește SDK-ul oficial `netopia/payment2` sau implementează riguros specificația oficială curentă.
- [ ] Păstrează separat API key sandbox/live, POS signature și cheia/certificatul public necesar verificării IPN.
- [ ] Implementează colectarea browser data cerută de API.
- [ ] Implementează fluxul complet `start → customerAction/3DS → authorize/return` conform răspunsului.
- [ ] Implementează redirect/3DS fără ca serverul eMUD să primească date brute de card.
- [ ] Validează IPN-ul prin mecanismul oficial: JWT/semnătură, public key, issuer, hash payload și POS signature permisă.
- [ ] Elimină verificarea simplă `Authorization === API key` ca unic mecanism IPN.
- [ ] Mapează toate statusurile NETOPIA într-o stare internă documentată.
- [ ] Verifică amount, currency, orderID, ntpID și POS înainte de actualizarea comenzii.
- [ ] Răspunde IPN-ului în formatul și timpul cerut de NETOPIA.
- [ ] Implementează status query/reconciliation.
- [ ] Implementează anulare/refund complet și parțial dacă sunt disponibile contractual.
- [ ] Tratează duplicate, retry, evenimente out-of-order și timeout.
- [ ] Testează scenariile sandbox oficiale: aprobat, refuzat, 3DS, pending, IPN invalid, duplicate și refund.
- [ ] Cere NETOPIA validarea tehnică finală înainte de activarea POS live.

## 13. Etapa 8 — FAN Courier complet

Starea actuală: există un adapter generic pentru creare AWB și tracking, testat cu `Http::fake`. Endpointurile și payloadurile trebuie înlocuite/aliniate cu documentația SelfAWB aferentă contului contractual real.

- [ ] Obține contractul, accesul SelfAWB și cea mai nouă documentație API din cont.
- [ ] Confirmă metoda reală de autentificare și ciclul tokenului.
- [ ] Implementează login/token refresh fără a expune username/parolă/token.
- [ ] Sincronizează nomenclatoarele FAN: județe, localități, străzi, servicii și puncte FANbox, dacă se folosesc.
- [ ] Validează/normalizează adresa înainte de AWB.
- [ ] Calculează estimarea reală de tarif și suprataxe.
- [ ] Configurează serviciile permise per metodă de livrare.
- [ ] Implementează generarea AWB cu toate câmpurile obligatorii.
- [ ] Previne AWB duplicat pentru aceeași expediere.
- [ ] Implementează descărcarea și stocarea securizată a etichetei PDF.
- [ ] Adaugă print/reprint din admin.
- [ ] Implementează anularea/ștergerea AWB.
- [ ] Implementează comandă de ridicare/pickup dacă este necesară operațional.
- [ ] Implementează ramburs și cont colector conform contractului.
- [ ] Implementează tracking periodic în queue și maparea statusurilor FAN.
- [ ] Actualizează automat `shipped_at`, `delivered_at` și statusul comenzii.
- [ ] Tratează retur la expeditor, refuz, adresă greșită, deteriorare și colet pierdut.
- [ ] Suportă mai multe colete și split shipment.
- [ ] Adaugă FANbox doar dacă businessul îl activează contractual.
- [ ] Testează pe contul de test/contractual: tarif, AWB, label, anulare și tracking.

## 14. Etapa 9 — integrarea primului furnizor real

Nu începe importul complet înainte de finalizarea etapelor 0–5. Integrarea poate începe în sandbox/staging în paralel cu plățile doar dacă nu publică produse și nu acceptă comenzi reale.

### 14.1 Onboarding și contract de date

- [ ] Obține documentație, exemple reale și acces de test.
- [ ] Documentează autentificarea, limitele de trafic și ferestrele de mentenanță.
- [ ] Definește identificatorul stabil: external ID, SKU, EAN și MPN.
- [ ] Clarifică dacă prețul este net/brut, TVA, moneda și discounturile contractuale.
- [ ] Clarifică sensul cantității/stării de stoc și lead time.
- [ ] Clarifică produsele retrase și perioada după care devin discontinued.
- [ ] Clarifică imaginile, drepturile de utilizare și hotlinkingul.
- [ ] Clarifică taxonomia, atributele și datele de compatibilitate auto.
- [ ] Clarifică plasarea, confirmarea, anularea și statusul comenzilor dropshipping.
- [ ] Clarifică expedierea: furnizorul folosește AWB-ul eMUD sau propriul contract de curier.
- [ ] Clarifică factura furnizorului, retururile, garanțiile și RMA.

### 14.2 Connector și import

- [ ] Creează un connector dedicat dacă formatul nu este acoperit sigur de cel generic.
- [ ] Adaugă paginare, checkpoint/resume și rate limiting.
- [ ] Validează schema fiecărui rând înainte de import.
- [ ] Izolează erorile pe rând; un produs invalid nu oprește feedul.
- [ ] Păstrează raw payload, hash, timestamps și sursa.
- [ ] Importurile identice sunt idempotente.
- [ ] Produsele dispărute nu sunt șterse imediat.
- [ ] Detectează feed parțial/anormal și nu marchează masiv produse ca indisponibile.
- [ ] Adaugă prag de siguranță pentru variații extreme de stoc/preț.
- [ ] Adaugă retry cu backoff și dead-letter.
- [ ] Protejează împotriva SSRF, fișierelor uriașe și XML entity attacks.
- [ ] Testează catalog, stock-only și price-only separat.

### 14.3 Mapare și catalog canonic

- [ ] Admin complet pentru maparea categoriilor furnizorului.
- [ ] Admin complet pentru maparea atributelor și transformărilor.
- [ ] Cozi de review pentru produse nemapate, ambigue sau duplicate.
- [ ] Matching în ordine controlată: mapping manual, external ID existent, EAN, MPN+brand, SKU și fuzzy doar ca sugestie.
- [ ] Nu face auto-merge pe fuzzy match.
- [ ] Preview/diff înainte de aplicarea mappingului.
- [ ] Bulk approve/reject cu audit.
- [ ] Reguli pentru câmpurile furnizorului care pot actualiza produsul canonic.
- [ ] Fitmentul importat rămâne cu sursă și confidence.
- [ ] Imaginile sunt descărcate și validate, nu hotlinkuite implicit.

### 14.4 Stoc, ofertă și preț

- [ ] Definește TTL/staleness per furnizor și per tip de sincronizare.
- [ ] Exclude automat ofertele stale sau furnizorii suspendați.
- [ ] Istoric complet pentru cost și stoc.
- [ ] Alertă la variații imposibile/negative și cost lipsă.
- [ ] Reguli de markup configurabile per furnizor, brand, categorie și produs, cu precedence clară.
- [ ] Prag minim de marjă și alertă pentru marjă negativă.
- [ ] Preț manual cu posibilitate de blocare față de recalcularea automată.
- [ ] Testează schimbări de TVA, monedă, MOQ și lead time.

### 14.5 Comenzi către furnizor

- [ ] Reverifică oferta imediat înainte de transmitere.
- [ ] Creează purchase order idempotent.
- [ ] Transmite adresa și datele strict necesare, conform politicii GDPR/DPA.
- [ ] Salvează request/response sanitizat și referința furnizorului.
- [ ] Confirmă acceptarea, cantitatea și termenul furnizorului.
- [ ] Gestionează refuz, parțial, backorder și schimbare de preț.
- [ ] Notifică operatorul și clientul conform regulilor definite.
- [ ] Reconciliere periodică a statusurilor.

## 15. Etapa 10 — CMS și SEO complete

### 15.1 Articole

- [x] Articole cu titlu, slug, rezumat, HTML, imagine, categorie, status și SEO de bază.
- [ ] Înlocuiește textarea HTML cu un editor controlat/sanitizat.
- [ ] Sanitizează HTML la salvare și/sau randare după allowlist.
- [ ] Preview înainte de publicare.
- [ ] Publicare/depublicare programată.
- [ ] Revision history și restore.
- [ ] Autor, timp de citire, taguri și articole asociate.
- [ ] Galerie/media embedded fără upload nesigur.
- [ ] Open Graph/Twitter image, canonical și preview social.
- [ ] Redirect 301 la schimbarea slugului.
- [ ] Sitemap și structured data `Article`.

### 15.2 Categorii editoriale

- [x] Tabel cu descriere, poziție, activare și SEO.
- [ ] Formularul admin trebuie să editeze toate câmpurile, nu doar numele.
- [ ] Imagine, alt text și Open Graph.
- [ ] Slug, descriere, ordine și status.
- [ ] SEO title/description/canonical/robots.
- [ ] Redirecturi la schimbarea slugului.

### 15.3 SEO transversal

- [ ] Meta title/description/canonical/robots pentru produse, categorii, branduri, articole și evenimente.
- [ ] Open Graph și Twitter cards.
- [ ] Sitemap index și sitemapuri separate.
- [ ] Structured data pentru Product, Offer, BreadcrumbList, Article, Organization și Event.
- [ ] Breadcrumbs bazate pe categoria principală.
- [ ] URL-uri canonice pentru pagini filtrate și reguli de indexare a facetelor.
- [ ] Redirect manager 301/410.
- [ ] Pagini 404/410 și produse discontinued cu alternative.
- [ ] Prevenirea conținutului duplicat din filtre și compatibilitate auto.
- [ ] Feed Merchant Center ulterior, doar după stabilizarea catalogului.

### 15.4 Pagini statice și navigație

- [ ] Modul pentru pagini statice: Despre, Contact, Livrare, Plată, Retur, Garanții și pagini legale.
- [ ] Draft, preview, publicare programată, revizii și SEO pentru pagini.
- [ ] Administrarea meniurilor header/footer din admin.
- [ ] Blocuri reutilizabile de conținut și bannere cu perioadă de afișare.
- [ ] Formular de contact cu rate limiting, anti-spam și rutare către suport.
- [ ] Redirect manager pentru URL-uri vechi.

## 16. Etapa 11 — storefront public și căutare

Interfața vizuală finală poate rămâne la sfârșit, dar funcționalitatea trebuie definită acum.

- [ ] Homepage, navigare și mega-menu din taxonomia admin.
- [ ] Pagini categorie/subcategorie.
- [ ] Căutare full-text tolerantă și autocomplete.
- [ ] Filtre dinamice numai pentru valorile relevante categoriei și rezultatului curent.
- [ ] Filtre: preț, brand, disponibilitate, rating viitor, caracteristici și compatibilitate auto.
- [ ] Selector global `Alege mașina` și `Build My Rig`.
- [ ] Pagini marcă/model/an cu piese compatibile.
- [ ] Product listing cu sortare, paginare și URL-uri shareable.
- [ ] Product detail cu variante, fitment, stoc, termen, preț, imagini, documente și produse asociate.
- [ ] Mesaj clar când disponibilitatea este estimată din stocul furnizorului.
- [ ] Coș, checkout, cont client și tracking comandă.
- [ ] Blog/ghiduri și evenimente comunitate.
- [ ] Wishlist/favorite asociate contului și, opțional, sesiunii anonime.
- [ ] Review-uri produse doar de la clienți eligibili, cu moderare și anti-spam.
- [ ] Întrebări și răspunsuri despre produse, cu moderare, dacă se decide activarea lor.
- [ ] Contact/support contextual din produs și comandă.
- [ ] Mobile-first, accesibilitate WCAG 2.2 AA și performanță Core Web Vitals.
- [ ] Cache și invalidare la actualizarea catalogului/stocului.
- [ ] Decide când volumul justifică Meilisearch/OpenSearch; PostgreSQL rămâne sursa de adevăr.

## 17. Etapa 12 — retururi, garanții, facturare și operațiuni

- [ ] Politică și flux de retur în termen legal, inclusiv excepții pentru produse speciale.
- [ ] RMA per produs/furnizor.
- [ ] Statusuri și timeline pentru retur.
- [ ] Etichetă de retur și integrare curier, dacă este disponibilă.
- [ ] Refund legat de cantitatea și valoarea returnată.
- [ ] Garanție, documente și trimitere către furnizor/service.
- [ ] Factură/proformă/storno printr-un modul separat și integrare e-Factura, când se decide providerul.
- [ ] Numerotări și serii configurabile.
- [ ] Reconciliere plăți, rambursuri, cost curier și facturi furnizor.
- [ ] Export contabil.
- [ ] Raport marjă reală pe comandă, furnizor, categorie și produs.
- [ ] Gestionare chargeback/dispute.

## 18. Etapa 13 — securitate, GDPR și conformitate

- [ ] Roluri și permisiuni granulare în admin, nu doar boolean `is_admin`.
- [ ] 2FA pentru administratori.
- [ ] Rate limiting pentru login, checkout, căutare, alerte și webhooks.
- [ ] Audit log pentru acces și operațiuni sensibile.
- [ ] CSP, HSTS, secure cookies, CSRF și headers de securitate.
- [ ] Sanitizare HTML și protecție XSS.
- [ ] Protecție SSRF pentru URL-uri furnizor și import media.
- [ ] Validare upload și scanare malware dacă se acceptă documente.
- [ ] Politică de rotație credentiale și `APP_KEY`.
- [ ] Procedură de backup și restore testată pentru PostgreSQL și media.
- [ ] Minimizare PII în payloaduri și loguri.
- [ ] Retenție pentru coșuri, payloaduri, webhookuri, loguri și date client.
- [ ] Consimțământ marketing, dovadă și retragere.
- [ ] Cookie consent și categorii de tracking înainte de analytics/ads.
- [ ] Termeni, confidențialitate, cookies, livrare, retur și garanții cu versionare.
- [ ] Contracte/DPA cu furnizorii care primesc datele clienților.
- [ ] Export și ștergere/anonymizare date client fără distrugerea documentelor contabile obligatorii.
- [ ] Secret scanning și dependency/security audit în CI.

## 19. Etapa 14 — observabilitate și operare

- [ ] Logging JSON cu correlation ID.
- [ ] Sentry/Bugsnag sau echivalent pentru excepții.
- [ ] Metrici pentru queue depth, failed jobs, sync duration, produse stale, erori API și webhook latency.
- [ ] Dashboard operațional pentru furnizori, plăți și curieri.
- [ ] Alerte la import eșuat, scădere masivă de catalog, stoc stale și marjă negativă.
- [ ] Failed jobs UI/retry controlat.
- [ ] Webhook replay UI cu audit.
- [ ] Runbooks pentru: furnizor indisponibil, procesator indisponibil, webhook blocat, curier indisponibil și feed corupt.
- [ ] Backup automat, retenție și restore drill.
- [ ] Strategie de deployment cu zero/minim downtime și migrații compatibile.
- [ ] Separare clară local/staging/production.
- [ ] Nu folosi credentiale live în local.

### Analytics și măsurarea businessului

- [ ] Analytics first-party pentru funnel: căutare, selectare mașină, produs, coș, checkout și achiziție.
- [ ] GA4/GTM și pixeli publicitari numai după consimțământul corespunzător.
- [ ] Evenimente ecommerce fără PII în payload.
- [ ] Rapoarte pentru zero-results searches și filtre fără rezultate.
- [ ] Rapoarte pentru conversie per mașină, categorie, furnizor și sursă de trafic.
- [ ] UTM attribution și păstrarea sursei pe comandă conform politicii de retenție.
- [ ] Search merchandising: sinonime, boost, pin și reguli auditate, după ce există suficiente date.

## 20. Etapa 15 — strategia completă de testare

Suita actuală este prea mică pentru a valida un magazin dropshipping și integrări financiare.

- [ ] Unit tests pentru Money, TVA, rotunjire, markup și alegerea ofertei.
- [ ] Unit tests exhaustive pentru fitment.
- [ ] Feature tests pentru fiecare CRUD admin și autorizare.
- [ ] Feature tests pentru variante și atribute definitorii.
- [ ] Feature tests pentru coș, reprice, checkout și schimbarea stocului.
- [ ] Concurrency tests pentru coș, comandă, webhook și ultimele bucăți.
- [ ] Contract tests pentru fiecare connector furnizor.
- [ ] Fixture-uri versionate din payloaduri sanitizate reale.
- [ ] Tests pentru import parțial, feed gol, feed corupt și produse dispărute.
- [ ] Tests Stripe: start, 3DS, webhook valid/invalid, duplicate, out-of-order și refund.
- [ ] Tests NETOPIA: start, browser data, 3DS, IPN criptografic, duplicate și refund.
- [ ] Tests FAN: auth, nomenclatoare, tarif, AWB, label, cancel și tracking.
- [ ] Tests pentru alerte și prevenirea notificărilor duplicate.
- [ ] Tests pentru comunitate și limita de capacitate.
- [ ] Browser tests pentru fluxurile critice admin și checkout.
- [ ] PostgreSQL integration suite în CI.
- [ ] Redis queue/scheduler integration tests pentru joburile critice.
- [ ] Performance tests pentru import mare, filtre și căutare.
- [ ] Security tests pentru upload, XSS, SSRF, autorizare și rate limiting.

## 21. Ordinea recomandată de execuție

1. Etapa 0: mediu local, PostgreSQL/Redis real, CI și analiză statică.
2. Etapa 1: închiderea adminului pentru categorii, filtre, variante, produse, media și CMS.
3. Etapa 2: CRUD auto și motorul de fitment.
4. Etapa 4: money, TVA, checkout, state machines și purchase orders.
5. Etapa 5: infrastructura comună pentru servicii externe.
6. Stripe sandbox complet.
7. NETOPIA sandbox complet și validare tehnică.
8. FAN Courier după documentația contului SelfAWB.
9. Primul furnizor în staging, cu `auto_create_products=false`.
10. Cont client, garaj, alerte și comunitate.
11. Storefront public și design final.
12. Retururi, facturare, rapoarte, hardening și lansare.

Stripe și NETOPIA pot fi dezvoltate în paralel după etapa 5, dar fiecare trebuie să aibă propriile teste și propriul criteriu de activare. Primul furnizor poate fi conectat tehnic în staging mai devreme, dar nu trebuie să publice produse sau să primească comenzi reale până când checkoutul și fluxul dropshipping sunt complete.

## 22. Definition of Done global

Un task poate fi bifat numai dacă:

- codul este implementat, nu doar modelul/migrația;
- validarea și autorizarea sunt implementate;
- happy path și failure paths au teste;
- retry/idempotency sunt acoperite unde există I/O extern;
- logurile nu conțin secrete sau PII inutil;
- documentația este actualizată;
- migrațiile rulează pe PostgreSQL;
- `composer validate`, testele, Pint, analiza statică și buildul Vite sunt verzi;
- funcționalitatea poate fi verificată din admin/API/fluxul tehnic relevant;
- pentru servicii externe există dovadă de test sandbox, nu doar `Http::fake`.

## 23. Definition of Ready pentru lansare

Magazinul nu este pregătit pentru comenzi reale până când toate condițiile de mai jos sunt îndeplinite:

- [ ] Cel puțin un furnizor are sincronizare stabilă de catalog, preț și stoc.
- [ ] Produsele furnizorului sunt mapate și verificate editorial.
- [ ] Stocul și prețul sunt reverificate la checkout.
- [ ] Purchase orderul poate fi transmis și confirmat de furnizor.
- [ ] Cel puțin un procesator a trecut testele sandbox și validarea live.
- [ ] Curierul poate calcula, genera, tipări, anula și urmări AWB-ul.
- [ ] Fluxul complet comandă → plată → furnizor → expediere → livrare a trecut un test end-to-end.
- [ ] Anularea, refundul, returul și excepțiile au flux operațional.
- [ ] Emailurile tranzacționale funcționează.
- [ ] Backup/restore, monitorizarea și alertele sunt funcționale.
- [ ] Documentele legale și consimțămintele sunt publicate și versionate.
- [ ] Security review și performance baseline sunt finalizate.
- [ ] Nu există credentiale de test în production sau credentiale live în repository/local.

## 24. Decizii care trebuie confirmate de owner, dar nu blochează începutul

În lipsa unei alte decizii, Claude Code trebuie să folosească recomandarea notată:

- Strategie Stripe: Payment Element + Payment Intents.
- Monedă inițială: RON; pregătire pentru oferte furnizor în EUR.
- Preț public: preț brut cu TVA inclus.
- Produse importate: `review`, niciodată publicate automat.
- Selectare furnizor: disponibilitate + cost total + SLA + prioritate, nu doar cost minim.
- Comenzi multi-furnizor: o comandă client, mai multe purchase orders și expedieri.
- Search inițial: PostgreSQL; Meilisearch/OpenSearch doar după măsurarea catalogului și latenței.
- Media: storage abstractizat; local în development, S3-compatible + CDN în production.
- Primul curier: FAN Courier.
- Procesator implicit la lansare: se decide numai după testele și condițiile comerciale Stripe/NETOPIA.
- Facturare: modul separat, provider de e-Factura ales ulterior.

## 25. Jurnal de progres

Adaugă o intrare după fiecare etapă importantă:

| Data | Commit | Etapă | Ce s-a finalizat | Teste/rulare | Observații |
|---|---|---|---|---|---|
| 2026-08-24 | `3fbf1ac` | Fundație admin/commerce | Catalog admin, CMS, schelete plăți și livrare | teste simulate | Integrările externe nu sunt validate sandbox |
| 2026-08-24 | `412bd0f` / echivalent local | Stabilizare | Lockfile Composer și formatare | 13 teste, 31 aserțiuni, Pint | Suita rămâne insuficientă pentru producție |

## 26. Comenzi minime de verificare

Adaptează comenzile la setupul Docker/local, dar nu omite verificările:

```bash
composer validate --no-check-publish
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
npm ci
npm run build
php artisan queue:work --stop-when-empty
php artisan schedule:list
```

Pentru testarea finală trebuie să existe și o rulare cu PostgreSQL și Redis reale, nu numai SQLite/sync queue.

## 27. Surse tehnice obligatorii pentru integrările externe

Folosește întotdeauna documentația oficială actuală și versiunea activată în contul comerciantului:

- Stripe Payment Intents: <https://docs.stripe.com/payments/payment-intents>
- Stripe Payment Element: <https://docs.stripe.com/payments/payment-element>
- Stripe webhooks: <https://docs.stripe.com/webhooks>
- NETOPIA Payment API v2: <https://doc.netopia-payments.com/docs/payment-api/v2.x/intro/>
- NETOPIA start payment: <https://doc.netopia-payments.com/docs/payment-api/v2.x/start/start-strc/>
- NETOPIA PHP SDK: <https://doc.netopia-payments.com/docs/payment-sdks/php/>
- FAN Courier eCommerce/API: <https://www.fancourier.ro/en/ecommerce/>
- FAN Courier SelfAWB: documentația descărcată din contul contractual SelfAWB.

Nu copia payloaduri sau endpointuri din bloguri, module vechi sau implementări terțe fără verificare în documentația oficială și în contul real.
