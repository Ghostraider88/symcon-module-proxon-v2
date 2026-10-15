# Proxon module roadmap

## Initial plant and room functions

- **Implemented:** system status for operating modes, fan speeds/stages, air temperatures, connected room panels, sensor values and filter runtimes.
- **Implemented:** plant controls for operating mode, ventilation, heat pump limits, compressor power, PTC settings, sensor selection, CO2/RF thresholds, airflow settings and filter service values.
- **Already present:** configurator reads the connected NBP/HNBP bitfields and offers only detected room panels.
- **Implemented:** CO2/RF room assignment for up to five sensors and per-room sensor temperature correction.
- **Evidence executed:** read-only review of the manually configured FWT Modbus devices in Symcon and comparison with the supplied register sheets. The corresponding function and register groups are now represented in the module.
- **Register conflict found:** manual Symcon instance 39235 labels register 48 as the minimum outdoor temperature for cooling. The FWT 2.0 workbook defines register 46 for cooling enable temperature (5–20 °C) and register 48 for compressor operation independent of heat demand (−15–5 °C). The module now exposes both documented controls; the live manual instance was left untouched.
- **Status mapping corrected:** the live FWT instance reads heat pump mode from FC3/register 69. The module status now uses that source instead of the previously assumed FC4/register 241.
- **Open:** apply the local module update in Symcon and confirm the newly displayed values and write actions against the installation. No writes were issued to the existing live Modbus devices during this review.

## Later work

- Diagnostics and fault handling.
- Additional comfort and automation features.
- T300 support.
