# Model de date

| Domeniu | Entități principale | Rol |
|---|---|---|
| Catalog | `categories`, `attributes`, `products`, `product_variants` | Taxonomie, filtre și produs canonic |
| Fitment | `vehicle_*`, `product_fitments`, `customer_vehicles` | Compatibilitate structurată și garaj client |
| Dropshipping | `suppliers`, `supplier_products`, `supplier_offers`, `supplier_sync_runs` | Staging, mapare, ofertă curentă și audit |
| Comerț | `orders`, `order_items`, `addresses` | Comanda client și snapshot pe furnizor |
| Personalizare | `saved_searches`, `product_alerts`, `notification_preferences` | Alerte și comunicare pe vehicul |
| Comunitate | `community_events`, `community_event_registrations` | Evenimente online/offline și participare |

PostgreSQL folosește B-tree pentru chei, stări, date și valori numerice; GIN pentru payloadurile JSONB; `pg_trgm` pentru căutare tolerantă în numele produselor. Pentru un catalog foarte mare, următorul pas este Meilisearch/OpenSearch alimentat din evenimente, fără schimbarea modelului canonic.
