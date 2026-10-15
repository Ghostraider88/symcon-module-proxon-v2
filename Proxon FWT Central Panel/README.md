# Proxon FWT Central Panel

This instance represents the room assigned to the central TFT control panel. It provides the room temperature, room setpoint, and the room's PTC release.

| Variable | Register | Use |
| --- | --- | --- |
| Current Temperature | 3x0263 | Current temperature of zone 1 (EG) |
| Target Temperature | 4x0070 | Zone 1 setpoint, 10 to 30 °C |
| PTC Release | 4x0187 | Room heating element release |

System-wide operating mode, fan stage, cooling release, and intensive ventilation are provided by the separate Proxon FWT System Control instance.
