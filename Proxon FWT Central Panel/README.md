# Proxon FWT Central Panel

This instance represents the room assigned to the central TFT control panel. It provides the room temperature, room setpoint, and the room's PTC release.

| Variable | Register | Use |
| --- | --- | --- |
| Current Temperature | 3x0263 | Current temperature of zone 1 (EG) |
| Target Temperature | 4x0070 | Zone 1 setpoint, 10 to 30 °C |
| PTC Release | 4x0187 | Room heating element release |

System-wide operating mode, fan stage, cooling release, and intensive ventilation are provided by the separate Proxon FWT System Control instance.

The setpoint range is 10–30 °C. The panel accepts whole-degree setpoints; fractional inputs from a script or API are rounded to the nearest whole degree before the FC6 write. The Modbus register still stores hundredths of a degree for readback.

## Exported functions

- `PROXON_RequestStatus(int $id)`: reads the central-panel room values once.
- `PROXON_SetTemperature(int $id, mixed $value)`: validates and writes a room setpoint in °C.
- `PROXON_SetPTC(int $id, mixed $released)`: changes the room PTC release.
- `PROXON_RefreshReadQuality(int $id)`: recalculates read freshness without Modbus reads.
