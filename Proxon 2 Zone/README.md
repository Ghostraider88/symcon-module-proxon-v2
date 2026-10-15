# Proxon Room Zone

Create one instance for each installed HNBP or NBP room control panel. The Proxon Configurator lists connected panels and assigns IDs 1 to 19 to NBPs and ID 20 to the HNBP.

Each instance provides current room temperature, an absolute room setpoint, the room's PTC release, the key lock state and a sensor temperature correction. For HNBP and NBP panels, the room target is represented in Modbus as a middle temperature plus an offset.

| Panel | Current temperature | Middle temperature | Setpoint offset | PTC release | Key lock | Sensor correction |
| --- | --- | --- | --- | --- | --- | --- |
| HNBP (ID 20) | 3x0590 | 4x0233 | 4x0213 | 4x0253 | 4x0273 | 4x0190 |
| NBP n (ID 1–19) | 3x0590 + 3n | 4x0233 + n | 4x0213 + n | 4x0253 + n | 4x0273 + n | 4x0190 + n |

The target offset range is ±3 °C. Sensor corrections use 0.1 °C steps and range from -10 to +10 °C. System-wide settings are managed by the separate Proxon System Control instance.
