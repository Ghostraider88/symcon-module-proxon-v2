# Proxon module roadmap

## Initial plant and room functions

- **Implemented:** system status for operating modes, fan speeds/stages, air temperatures, connected room panels, sensor values and filter runtimes.
- **Implemented:** standard plant controls for operating mode, fan stage, cooling enable, intensive ventilation, CO2/RF thresholds and filter service values. Protective and tuning controls are tracked under the Advanced split below.
- **Already present:** configurator reads the connected NBP/HNBP bitfields and offers only detected room panels.
- **Implemented:** CO2/RF room assignment for up to five sensors. Panel temperature correction is now in the Advanced instance.
- **Evidence executed:** read-only review of the manually configured FWT Modbus devices in Symcon and comparison with the supplied register sheets. The corresponding function and register groups are now represented in the module.
- **Register conflict found:** a manually configured Symcon instance labels register 48 as the minimum outdoor temperature for cooling. The FWT 2.0 workbook defines register 46 for cooling enable temperature (5–20 °C) and register 48 for compressor operation independent of heat demand (−15–5 °C). The module now exposes both documented controls; the live manual instance was left untouched.
- **Status mapping corrected:** the live FWT instance reads heat pump mode from FC3/register 69. The module status now uses that source instead of the previously assumed FC4/register 241.
- **Open:** apply the local module update in Symcon and confirm the newly displayed values and write actions against the installation. No writes were issued to the existing live Modbus devices during this review.

## Standard and Advanced system control

- **Implemented locally:** separated daily controls and routine status values from compressor/heat-pump limits, airflow tuning, temperature-source selection, bypass temperature threshold and HNBP/NBP sensor correction. Advanced writes require the switch in System Control; the instance is created through its configuration button.
- **Implemented locally:** added read-only Bypass Status (FC4/register 222) and text Damper Position (FC4/register 159) to standard System Control. Position labels use the existing Symcon mapping for values 0–4.
- **Evidence executed:** inspected the PDF register entries and the configured read-only Modbus instances in Symcon. The bypass entry specifies `1 = open`; the damper entry identifies EG/OG distribution, while the existing Symcon presentation provides the five position labels. No writes or configuration changes were made to the live Modbus instances.
- **Migration:** variables for advanced controls already owned by System Control and Proxon Zone are retained, hidden and made read-only when those module instances apply the update; the Advanced instance owns the new controls.
- **Fixed locally:** replaced undefined `LOGMESSAGE_*` names with supported Symcon `KL_MESSAGE` and `KL_WARNING` constants in the System Control, Advanced and Zone modules. Source search confirms no `LOGMESSAGE_*` references remain; re-run the Advanced creation action in Symcon after updating the module.
- **Open:** the updated library has not been loaded into Symcon yet. Confirm polling, labels and action lockout after installing the update. Keep Advanced disabled unless these settings are required and understood.

## Later work

- Diagnostics and fault handling.
- Additional comfort and automation features.
- T300 support.
