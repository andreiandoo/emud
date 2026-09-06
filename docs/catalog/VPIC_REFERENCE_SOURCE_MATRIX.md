# vPIC reference API acquisition matrix

| Dataset | vPIC method | Local target | Default mode | Request shape |
|---|---|---|---|---|
| Manufacturers | `GetAllManufacturers` | `vehicle_manufacturers` + identifiers | `catalog` | paged, 100 rows/page |
| Makes | `GetAllMakes` | `vehicle_makes` + identifiers/aliases | `catalog` | one global request |
| Models | `GetModelsForMakeId/0` | `vehicle_models` + identifiers/aliases | `catalog` | one global, potentially slow request |
| Manufacturer -> make | `GetMakeForManufacturer/{manufacturerId}` | `vehicle_make_manufacturers` | `manufacturer_links` | one request/manufacturer |
| WMI | `GetWMIsForManufacturer/{manufacturerId}` | `vehicle_wmis` + WMI identifiers | `manufacturer_links` | one request/manufacturer |
| Make -> vehicle type | `GetVehicleTypesForMakeId/{makeId}` | `vehicle_make_types` | `vehicle_types` | one request/make |
| Model-year membership | `GetModelsForMakeIdYear/makeId/{makeId}/modelyear/{year}` | `vehicle_model_years` | `model_years` | one request/make/year |

NHTSA documents automated traffic rate control for vPIC APIs. The high-request modes therefore use persistent checkpoints, small parent batches and configurable inter-request delays rather than parallel fan-out.

The source is US-market-oriented: vPIC data represents vehicles intended for sale or importation into the United States. It complements, rather than replaces, EEA European homologation data.
