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

The instance displays actual operating and heat-pump modes, fan RPM and stages, fresh/supply/extract/exhaust air temperatures, connected panels, CO2/RF readings for the configured sensor count, filter runtimes, bypass status (3x0222) and damper position (3x0159). Actual heat-pump mode comes from 4x0241: 0 = off, 1 = heating, 2 = cooling. Register 3x0069 is a test/debug value and is not used as the actual mode. The damper position is shown as EG, EG preferred, balanced, OG preferred or OG for values 0–4. Both bypass status and damper position are read-only.

## Advanced controls

The configuration switch **Enable Advanced Controls** defaults to off. Enabling it reveals the **Create Advanced Instance** button. That button creates a separate Advanced instance on the same Modbus connection. The switch must remain on for write actions to be accepted.

Advanced includes compressor and heat-pump settings, the bypass temperature threshold (4x0019), PTC thresholds, fan airflow tuning, zone temperature source selection and temperature corrections for HNBP/NBP panels. Incorrect values can impair or damage the plant. The current fan stage and measured fan RPM remain standard values.

Previously exposed advanced variables in an existing System Control instance are made read-only and hidden rather than deleted. The previous per-room sensor temperature correction is also hidden and locked in Proxon Zone. The Advanced instance takes over those registers. Update scripts and visualizations that used the old variable IDs to point to the new variables in Advanced.

## Configuration

| Property | Default | Use |
| --- | --- | --- |
| Interval | 30 seconds | Poll interval for control and status values |
| Heat Pump Monitoring Instance | None | Optional Monitoring instance whose heat-pump readings are shown in the HTML tile |
| Visualization Rooms | Empty list | Room display names, EG/OG assignment, selected PROXON rooms and optional external sensor variables |
| CO2/RF sensor count | 1 | Number of installed sensors to show, from 1 to 5 |
| Enable Advanced Controls | Off | Allows write actions in the associated Advanced instance |

The plant must already allow Modbus writes. This library does not change the write permission register.

## FWT visualization tile

System Control offers a read-only HTML-SDK tile. Add the instance to a Symcon visualization and select the matching Proxon System Control Monitoring instance in its configuration to include heat-pump measurements. The tile uses values already polled by these modules and does not add Modbus requests.

The EG/OG branch thickness and bars visualize the five-position damper preference. They are relative indicators, not measured air volume per floor. Register units and state mappings that are still under review remain unlabeled or show as unavailable. Register addresses and sensor codes are not shown in the tile.

The airflow paths use gradual temperature-color transitions through the crossflow core and larger moving markers. Downstream of the condenser, the supply duct and both room branches are red for heating, blue for cooling and grey for ventilation or off. Their animation speed and both fan symbols follow `SupplyFanRPM` and `ExtractFanRPM`. The main device diagram shows the evaporator, condenser and compressor as a schematic heat-pump circuit. The compressor rotor and running indicator follow the selected Monitoring instance's `CompressorSpeed`; available electrical power, compressor power, speed and evaporator temperature are shown in the adjacent heat-pump panel. An RPM above zero indicates that the compressor is turning; undocumented compressor status codes are not decoded. The confirmed heating code from input register 4x0241 drives red directly. If no verified heat/cool mode code is available while the compressor is running, the tile infers heating or cooling from the sign of the supply-to-fresh-air temperature difference. Inferred modes are marked with an asterisk.

### Room names and sensor mapping

Open **Visualization Rooms** in the instance configuration and add one row per room. Editing a row provides these fields:

| Field | Meaning |
| --- | --- |
| Room Name | Display name, independent of the instance name. When empty, use the selected PROXON room instance's name. A name is required for a room without a PROXON instance. |
| Floor | EG or OG. Drag rows to set their order; the visualization preserves it within each floor. |
| PROXON Room | Optional Panel, Zone or Central instance supplying `CurrentTemperature` and `TargetTemperature`; actual `PTCStatus` is used when that instance provides it. |
| PROXON Air Quality Sensor | Optional sensor 1–5 from this System Control instance supplying CO2 and relative humidity. Select the sensor actually serving the room. |
| External CO2 Variable | Optional integer/float Symcon variable, in ppm. Overrides only the selected room's CO2 value. |
| External Humidity Variable | Optional integer/float Symcon variable, in percent (0–100). Overrides only the selected room's humidity value. |
| Actual PTC Status Variable | Optional boolean Symcon variable for the actual heater state. Overrides the native actual PTC status. |

CO2 and humidity can use different sources. For example, keep PROXON CO2 while supplying humidity from a separate sensor, or add a room with only external air-quality readings. Empty selectors (ID 0) mean no external override. A deleted, invalid or not-yet-updated selected variable displays as unavailable; it does not silently select a different source. Missing readings appear as `—`, and a valid numeric zero remains zero.

The Zone module's `PTCRelease` is permission to heat and is never shown as an active heater. A Panel's actual `PTCStatus`, or an explicitly selected actual boolean status, can supply that indication; otherwise it stays unavailable.

This room mapping is for display and does not change the device's own CO2/RF room assignment or heating controls. Only selected instances, selected variables and this instance's selected native sensors are read. References protect those objects, and variable updates refresh the room cards through a short debounce timer without extra Modbus requests. No external variable is written or archived automatically.

## Installation

Create one System Control instance through the Proxon Configurator for each Modbus connection. Create a separate Proxon Zone instance for each installed HNBP or NBP. Create the Advanced instance only if you need its settings.

## Compatibility

IP-Symcon 8.1 or newer and the Proxon Modbus parent connection are required. T300 functions are not included.
