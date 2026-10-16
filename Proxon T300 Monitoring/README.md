# Proxon T300 Monitoring

The read-only Monitoring instance polls the documented T300 Input Registers with Modbus function 4 (FC4), grouped into 11 contiguous blocks. It exposes 57 register values, counters, relay states and operating/fault status values, plus a readable fault description and an availability flag for the optional external sensor. The poll interval defaults to 60 seconds. Use 0 to disable automatic polling while keeping manual updates available, or choose 5 to 86400 seconds.

The variables do not have actions, and the module does not enable archive logging. `ReadQuality` contains per-block JSON details, including last attempts, last successful timestamps, age and current, stale, unknown or failed status. Values become stale after max(300 seconds, three times the poll interval). Instance status 202 indicates a failed read or missing parent, 203 an invalid interval, and 104 automatic polling disabled. Debug output includes each FC4 block's duration and result.

Create the instance from the **Create Monitoring Instance** action in Proxon T300 Control after its Modbus parent is active.

## Register groups

| Addresses | Contents |
| --- | --- |
| 811-820 | Temperatures, pressures and expansion-valve position |
| 824-830 | Relay states and derived evaporator readings |
| 832 | Hot-air restriction duration |
| 834-836 | Emergency operation, defrost restriction and heat-pump state duration |
| 838-842 | Fan percentage and operating timers |
| 844-846 | Compressor temperature, fan and P14 pressure error durations |
| 861-862 | Raw fault code and fan speed in rpm |
| 868-888 | Emergency heater permission, setpoint, firmware, tank/superheat readings and timers |
| 890 | Maximum superheat after defrost |
| 892 | Defrost start temperature |
| 898-900 | PV tank level and active heater/heat-pump states |

### Fault code 861

`FaultCode` retains the raw numeric register value. `FaultDescription` maps the existing Symcon fault-code associations; values outside this list are displayed as unknown codes.

| Code | Description |
| ---: | --- |
| 0 | No fault |
| 1 | Modbus |
| 2 | Filter |
| 3 | Compressor |
| 4 | T9 |
| 5 | T11 |
| 6 | T13 |
| 7 | T21 |
| 8 | T20 |
| 9 | T6 |
| 10 | T5 |
| 11 | P19 |
| 12 | P14 |
| 13 | Compressor temperature |
| 14 | Fan |
| 15 | Low pressure |
| 16 | Pressure switch |

Scaling and offsets follow the supplied T300 register workbook and the existing Symcon FC4 configuration. Values that previously caused an undefined `offset` warning now safely default to zero when the workbook does not specify an offset. Numeric presentations use supported `SUFFIX` settings only.

The external sensor at input register 817 is optional. <code>ExternalSensorTemperatureAvailable</code> is false when the controller reports the sensor as absent; the scaled numeric value remains unchanged for compatibility and must be ignored while the availability flag is false.

## Compatibility

- IP-Symcon 8.1 or newer
- A compatible Proxon Modbus parent

## Exported functions

- `PROXONT300MON_RequestStatus(int $id)`: reads all configured FC4 register groups once.
- `PROXONT300MON_RefreshReadQuality(int $id)`: recalculates per-block freshness and age without Modbus reads.
