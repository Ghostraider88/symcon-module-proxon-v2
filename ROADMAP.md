# Proxon module roadmap

## Initial plant and room functions

- **Implemented:** system status for operating modes, fan speeds/stages, air temperatures, connected room panels, sensor values and filter runtimes.
- **Implemented:** standard plant controls for operating mode, fan stage, cooling enable, intensive ventilation, CO2/RF thresholds and filter service values. Protective and tuning controls are tracked under the Advanced split below.
- **Already present:** configurator reads the connected NBP/HNBP bitfields and offers only detected room panels.
- **Implemented:** CO2/RF room assignment for up to five sensors. Panel temperature correction is now in the Advanced instance.
- **Evidence executed:** read-only review of the manually configured FWT Modbus devices in Symcon and comparison with the supplied register sheets. The corresponding function and register groups are now represented in the module.
- **Register conflict found:** a manually configured Symcon instance labels register 48 as the minimum outdoor temperature for cooling. The FWT 2.0 workbook defines register 46 for cooling enable temperature (5–20 °C) and register 48 for compressor operation independent of heat demand (−15–5 °C). The module now exposes both documented controls; the live manual instance was left untouched.
- **Status mapping clarified:** the workbook defines Holding Register 3x0069 as the heat pump mode and lists its four codes; the module decodes that register. Input Register 4x0241 is separately named `Akt_WP_Betriebsart` and has no code table, so its relationship remains open.
- **Open:** apply the local module update in Symcon and confirm the newly displayed values and write actions against the installation. No writes were issued to the existing live Modbus devices during this review.

## Standard and Advanced system control

- **Implemented locally:** separated daily controls and routine status values from compressor/heat-pump limits, airflow tuning, temperature-source selection, bypass temperature threshold and HNBP/NBP sensor correction. Advanced writes require the switch in System Control; the instance is created through its configuration button.
- **Implemented locally:** added read-only Bypass Status (FC4/register 222) and text Damper Position (FC4/register 159) to standard System Control. Position labels use the existing Symcon mapping for values 0–4.
- **Evidence executed:** inspected the PDF register entries and the configured read-only Modbus instances in Symcon. The bypass entry specifies `1 = open`; the damper entry identifies EG/OG distribution, while the existing Symcon presentation provides the five position labels. No writes or configuration changes were made to the live Modbus instances.
- **Migration:** variables for advanced controls already owned by System Control and Proxon Zone are retained, hidden and made read-only when those module instances apply the update; the Advanced instance owns the new controls.
- **Fixed locally:** replaced undefined `LOGMESSAGE_*` names with supported Symcon `KL_MESSAGE` and `KL_WARNING` constants in the System Control, Advanced and Zone modules. Source search confirms no `LOGMESSAGE_*` references remain; re-run the Advanced creation action in Symcon after updating the module.
- **Open:** the updated library has not been loaded into Symcon yet. Confirm polling, labels and action lockout after installing the update. Keep Advanced disabled unless these settings are required and understood.

## Later work

- **Implemented locally:** added a read-only FWT HTML-SDK tile with animated fan/airflow paths, a separate EG/OG outlet view driven by damper position, bypass state, air temperatures and selected heat-pump Monitoring values. Added an explicit Monitoring instance selector; the tile reads that instance's direct child variables and does not add Modbus polling.
- **Open:** the Symcon preview endpoint was created for TEST, but this session has no available browser surface to inspect its screenshot; the HTML visualization tile remains visually unverified. EG/OG bars are qualitative preferences only; R26 airflow scope, R190's `ppm` meaning, R162/R223 mappings, the relationship between R69 and R241, and actual PTC relay state remain unverified.
- Diagnostics and fault handling.
- Additional comfort and automation features.
- T300 read-only status and sensor monitoring from its Input Register list.

## T300 control

- **Implemented locally:** added a separate T300 control device with fourteen writable settings from the supplied T300 `Holding Register` sheet, grouped polling, range/type validation, and a Configurator entry. The fixed-value language register is omitted.
- **Evidence:** mapped the sheet's register addresses, raw ranges and scales into the module. No Symcon runtime action or live Modbus write was performed.
- **Open:** the T300 sheet labels these settings `3x` while placing them in `Holding Register`; its interface overview defines `3x` as FC4. The implementation follows the Holding Register group (read FC3, write FC6), pending verification of read function, address base and write permission on a dedicated T300 test device. No visualization is included in this step.

## FWT register catalogue and fault summary

- **Implemented locally:** added `Proxon System Control Monitoring/REGISTERS.md` with tile candidate idents, units, meanings and register sources from Monitoring and standard System Control; ambiguous register semantics are explicitly marked.
- **Implemented locally:** added a generic fault summary, active-marker and poll-completeness variables. Raw fault registers and the backend details string are hidden from visualizations; code/bit mappings remain unresolved where the register sheet does not define them.
- **Validation:** inspected the module mappings and register catalogue diff. No Symcon instance, Modbus device or visualization file was changed; no fault reset/restart was sent.
- **Evidence executed:** the workbook identifies 4x0190 as `Akt.Drehzahl: Kompressor`, scale 1, unit ppm; the local Monitoring presentation now uses that unit. Read-only TEST checks confirmed active polling, reconciliation of the power components with total power, a complete fault poll, and decoding of the 3x0069 mode field. The installed TEST module has not been reloaded with this branch; the physical meaning of ppm, whether 3x0069 is an actual or requested mode, and positive COP/JAZ remain unverified. The compressor was off. No control write, fault reset or restart was sent.
- **Open:** verify airflow scope, error-code/bit mappings, the meaning of ppm at 4x0190, and the relationship between Input Register 4x0241 and Holding Register 3x0069. The HTML tile remains unpreviewed.
