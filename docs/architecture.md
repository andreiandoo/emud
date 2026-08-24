# Arhitectură eMUD

## Catalog canonic

Produsele, variantele, brandurile, media și categoriile sunt independente de furnizori. Categoria este un arbore cu adâncime nelimitată (`parent_id`, `full_path`, `depth`). Taxonomia inițială reproduce categoriile și subcategoriile publice de pe harta eMudding din 24 august 2026, dar denumirile pot fi ulterior schimbate fără modificarea codului.

Filtrele sunt definite în `attributes`, configurate per categorie în `attribute_category` și stocate tipizat în `product_attribute_values`. Un filtru poate fi obligatoriu, comparabil, vizibil în listare sau definitoriu pentru variante. Filtrele de preț, brand, disponibilitate și compatibilitate auto se calculează din tabelele lor dedicate.

## Vehicle garage și fitment

Ierarhia auto este `make → model → generation → engine/configuration`. `product_fitments` descrie reguli compacte: marcă, model, generație, motor, interval de ani și restricții JSON. Nu se generează inutil câte un rând produs × fiecare VIN posibil.

Clientul poate salva mai multe mașini în `customer_vehicles`. Căutarea va aplica implicit mașina principală, dar utilizatorul va putea comuta sau dezactiva filtrul.

## Supplier ingestion

Fiecare furnizor are protocol, endpointuri separate, credentiale criptate, mapare declarativă și opțional un connector PHP dedicat. Importul rulează asincron în coada Redis `imports`, cu lock pe furnizor și tip de sincronizare. Fiecare rând este procesat într-o tranzacție scurtă. Feedul brut, hash-ul și timestampul ultimei apariții rămân auditabile. Un feed poate crea automat produse în status `review`; publicarea rămâne controlată.

## Pricing și fulfillment

`BestSupplierOffer` exclude ofertele expirate și furnizorii opriți, apoi ordonează după disponibilitate, prioritatea contractuală și cost. `RetailPriceCalculator` separă costul furnizorului de prețul public și aplică markup, TVA și regula de rotunjire comercială.

La checkout, furnizorul ales și costul se copiază în `order_items`. O comandă cu produse de la mai mulți furnizori poate fi împărțită ulterior în purchase orders separate fără schimbarea modelului de comandă client.

`carts` și `cart_items` păstrează selecția temporară, iar `CheckoutService` creează adresele și snapshoturile comenzii într-o tranzacție. Inițierea plății este idempotentă și separată de tranzacția bazei de date. Confirmarea se face exclusiv din webhook/IPN verificat, nu din pagina de retur a clientului.

Procesatoarele implementează același contract. Providerul implicit este ales din admin dintre Stripe și NETOPIA, iar credentialele sunt criptate. `payment_transactions` și `payment_webhook_events` oferă audit și deduplicare.

Livrarea folosește un contract separat de curier. Prima implementare este FAN Courier: adminul configurează tokenul, clientul și serviciul, iar din comandă poate genera AWB și actualiza evenimentele de tracking. Endpointurile sunt configurabile pentru a permite alinierea la versiunea SelfAWB activată pe contul contractual.

## Personalizare și comunitate

`saved_searches`, `product_alerts` și `notification_preferences` susțin alerte de stoc/preț și newslettere pe mașinile salvate. Alertele folosesc stare de tranziție (`condition_met`), ca să nu trimită același mesaj la fiecare sincronizare.

Evenimentele online și offline sunt stocate în `community_events`, iar înscrierea poate include mașina cu care participă clientul.

## Flux de date

1. Schedulerul pune un job în Redis.
2. Connectorul descarcă și normalizează feedul într-un `SupplierRecord`.
3. Importerul face upsert în staging, încearcă maparea canonică și actualizează oferta.
4. Schimbarea se arhivează în istoricul ofertei.
5. Pentru produsul afectat se reevaluează alertele active.
6. Adminul vede numărul de produse nemapate și jurnalul fiecărei rulări.

## Limite intenționate ale acestei etape

Checkoutul are serviciul de domeniu, persistența și integrarea procesatoarelor; interfața publică va fi proiectată în etapa de storefront. Facturarea fiscală și purchase orders automate către furnizori rămân module separate. Credentialele live și validarea contractuală Stripe/NETOPIA/FAN Courier nu pot fi finalizate fără conturile comerciantului.
