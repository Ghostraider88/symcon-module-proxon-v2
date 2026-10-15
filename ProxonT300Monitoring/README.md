# Proxon T300 Monitoring

The read-only Monitoring instance polls the documented T300 Input Registers with Modbus function 4 (FC4), grouped into seven blocks. It exposes 57 measurements, counters, relay states and operating/fault status values, plus an availability flag for the optional external sensor. The poll interval defaults to 60 seconds and can be changed in the instance configuration.

The variables do not have actions, and the module does not enable archive logging. Debug output is available through the Symcon debug window for failed FC4 reads.

Create the instance from the **Create Monitoring Instance** action in Proxon T300 Control after its Modbus parent is active.

## Register groups

| Addresses | Contents |
| --- | --- |
| 811-830 | Temperatures, pressures, valve position and relay states |
| 832-841 | Operating timers and fan speed |
| 842-846 | Compressor, fan and pressure error durations |
| 861 | Fault code |
| 862 | Fan speed in rpm |
| 868-892 | Emergency permission, setpoint, firmware, tank/superheat readings and timers |
| 898-900 | PV tank level and active heater/heat-pump states |

Scaling and offsets follow the supplied T300 register workbook and the existing Symcon FC4 configuration. Values that previously caused an undefined `offset` warning now safely default to zero when the workbook does not specify an offset. Numeric presentations use supported `SUFFIX` settings only.

The external sensor at input register 817 is optional. <code>ExternalSensorTemperatureAvailable</code> is false when the controller reports the sensor as absent; the scaled numeric value remains unchanged for compatibility and must be ignored while the availability flag is false.

## Compatibility

- IP-Symcon 8.1 or newer
- A compatible Proxon Modbus parent

## Exported functions

- `PROXONT300MON_RequestStatus(int $id)`: reads all configured FC4 register groups once.
