# Proxon Room Zone

Create one instance for each installed HNBP or NBP room control panel. The Proxon Configurator lists connected panels and assigns IDs 1 to 19 to NBPs and ID 20 to the HNBP.

Each instance provides current room temperature, an absolute room setpoint, the room's PTC release and the key lock state. For HNBP and NBP panels, the room target is represented in Modbus as a middle temperature plus an offset.

| Panel | Current temperature | Middle temperature | Setpoint offset | PTC release | Key lock |
| --- | --- | --- | --- | --- | --- |
| HNBP (ID 20) | 3x0590 | 4x0233 | 4x0213 | 4x0253 | 4x0273 |
| NBP n (ID 1–19) | 3x0590 + 3n | 4x0233 + n | 4x0213 + n | 4x0253 + n | 4x0273 + n |

The target offset range is ±3 °C. Panel sensor temperature corrections are managed centrally by the separate Proxon System Control Advanced instance and require its enable switch.
