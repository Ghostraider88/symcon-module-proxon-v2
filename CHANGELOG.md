# Changelog

## 1.1 — Build 7 — 2026-10-15

- Combined outdoor, room/extract and exhaust-air labels with their temperature readings; removed the redundant labels and tile headings.
- Kept the outdoor-air inlet continuously green and moved the damper label clear of the supply-air reading. Distribution captions now say "bevorzugt" and "reduziert".
- Integrated the expansion valve and refrigerant loop into the central plant drawing. Evaporator/condenser roles and coil colors follow heating or cooling mode; compressor speed and power appear at the component.
- Removed empty airflow and generic measurement placeholders. Reworked the heat-pump panel around available electrical readings, fault status, runtimes and energy.

## 1.1 — Build 6 — 2026-10-15

- Decode the actual heat-pump mode from input register 4x0241: 0 = off, 1 = heating, 2 = cooling.
- Pass the confirmed heat-pump mode values to the FWT tile; test/debug register 3x0069 is not used as the actual mode.

## 1.1 — Build 5 — 2026-10-15

- Extended heating/cooling colors from the condenser outlet through the EG/OG branch ducts; ventilation and off are neutral grey.
- Added a fallback that infers heating or cooling from compressor RPM and supply/fresh-air temperatures when no verified heat/cool mode code is available. Inferred states are marked with an asterisk.

## 1.1 — Build 4 — 2026-10-15

- Smoothed airflow temperature colors and increased the visible size of duct and flow markers.
- Connected airflow animation speed and fan rotation to measured fan RPM.
- Integrated schematic evaporator, condenser and compressor graphics into the main airflow overview. Compressor animation and running state follow Monitoring compressor RPM.
- Clarified power and RPM units in the heat-pump panel.

## 1.1 — Build 3 — 2026-10-15

- Enabled drag-and-drop ordering of visualization room rows in the instance configuration.
- The visualization preserves each floor's row order.

## 1.1 — Build 2 — 2026-10-15

- Added Proxon Central as a room mapping source for current and target temperature. Its PTC release value is not treated as actual heater status.
- Added an isolated fixture check for Central room mapping.

## 1.1 — Build 1 — 2026-10-15

- Added configurable visualization rooms with display names, EG/OG assignment and optional PROXON room instances.
- Added independent external CO2, humidity and actual PTC state sources for room cards, with selected-variable updates and unavailable-value handling.
- Removed register and sensor codes from the end-user tile and added separate air-connection labels.
- Added revised compact FWT design proposals. The new equipment layout remains a static proposal; the active tile includes configurable room tables.
- Integrated the existing main-branch FWT register clarifications, monitoring catalogue and T300 control/status work.

Local fixture and browser checks are recorded in ROADMAP.md. Symcon TEST verification of the room configuration and update events is pending.
