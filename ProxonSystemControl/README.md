# Proxon System Control

This instance handles daily plant operation and read-only plant status for PROXON FWT 2.0 and 3.0. It is system-wide and connects to the same Modbus parent as the room panels.

## Standard controls

| Function | Register | Use |
| --- | --- | --- |
| Operating mode | 4x0016 | Off, Summer, Winter, ECO Comfort, Stove, Emergency |
| Fan stage | 4x0022 | Selects fan stage 1 to 4 |
| Cooling enabled | 4x0062 | Enables or disables demand-based cooling |
| Intensive ventilation duration | 4x0133 | Selectable duration from 0 to 1440 minutes |
| Filter service settings | 4x0460–4x0462 | Filter lifetime and recirculation filter presence |
| CO2 and humidity thresholds | 4x0117–4x0118 | Normal ventilation thresholds |
| CO2/RF room assignments | 4x0394–4x0397, 4x0466 | Assign up to five sensors to rooms |

Room assignment values for CO2/RF sensors are 0 unassigned, 1 central panel room, 2 HNBP room and 3–21 NBP 1–19.

## Status values

The instance displays actual operating and heat pump modes, fan RPM and stages, fresh/supply/extract/exhaust air temperatures, connected panels, CO2/RF readings for the configured sensor count, filter runtimes, bypass status (3x0222) and damper position (3x0159). The damper position is shown as EG, EG preferred, balanced, OG preferred or OG for values 0–4. Both bypass status and damper position are read-only.

## Advanced controls

The configuration switch **Enable Advanced Controls** defaults to off. Enabling it reveals the **Create Advanced Instance** button. That button creates a separate Advanced instance on the same Modbus connection. The switch must remain on for write actions to be accepted.

Advanced includes compressor and heat-pump settings, the bypass temperature threshold (4x0019), PTC thresholds, fan airflow tuning, zone temperature source selection and temperature corrections for HNBP/NBP panels. Incorrect values can impair or damage the plant. The current fan stage and measured fan RPM remain standard values.

Previously exposed advanced variables in an existing System Control instance are made read-only and hidden rather than deleted. The previous per-room sensor temperature correction is also hidden and locked in Proxon Zone. The Advanced instance takes over those registers. Update scripts and visualizations that used the old variable IDs to point to the new variables in Advanced.

## Configuration

| Property | Default | Use |
| --- | --- | --- |
| Interval | 30 seconds | Poll interval for control and status values |
| CO2/RF sensor count | 1 | Number of installed sensors to show, from 1 to 5 |
| Enable Advanced Controls | Off | Allows write actions in the associated Advanced instance |

The plant must already allow Modbus writes. This library does not change the write permission register.

## Installation

Create one System Control instance through the Proxon Configurator for each Modbus connection. Create a separate Proxon Zone instance for each installed HNBP or NBP. Create the Advanced instance only if you need its settings.

## Compatibility

IP-Symcon 8.1 or newer and the Proxon Modbus parent connection are required. T300 functions are not included.
