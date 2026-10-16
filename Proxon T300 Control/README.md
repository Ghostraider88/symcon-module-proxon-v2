# Proxon T300 Control

The control instance reads the documented T300 Holding Registers with Modbus function 3 (FC3) and writes controls with function 6 (FC6). It polls every 60 seconds by default and exposes an Update Values action. Use interval 0 to disable automatic polling while retaining manual updates, or choose 5 to 86400 seconds.

The ten additional settings are documented as writable and have been verified on the T300 display. The four existing controls (registers 2000-2003), PV function and Legionella function are enabled for routine operation. The remaining eight settings are intended for maintenance or initial setup and stay read-only unless **Enable Maintenance and Setup Controls** is selected.

Use the **Create Monitoring Instance** action to add the separate read-only FC4 module beside this instance. Remove an older T300 Control instance before switching to this split module layout so its previous measurement variables do not remain in the object tree.

## Register mapping

| Variable | Register | Range / conversion |
| --- | ---: | --- |
| Operating Mode | 2002 | 0 = Off, 1 = Demand, 2 = LF1, 3 = LF2 |
| Normal Water Temperature | 2000 | 20-55 °C, raw value ÷ 10 |
| Electric Heater Enabled | 2001 | 0 = off, 1 = on |
| Electric Heater Temperature | 2003 | 20-70 °C, raw value ÷ 10 |
| Daylight Saving Time | 2008 | 0 = off, 1 = on; maintenance / setup |
| Screen Timeout | 2009 | 0-240; maintenance / setup, unit unspecified |
| Legionella Function / PV Function | 2025 / 2010 | Boolean; enabled for routine operation |
| Hour / Minute / Weekday / Day / Month / Year | 2150-2155 | Clock fields; maintenance / setup |

The workbook marks fifteen rows R/W at write-permission level 1 (some). Fourteen have a nontrivial allowed range, and all fourteen are implemented with matching limits. Register 2006 (Language) is omitted because its documented range is fixed at 0. The manual Symcon configuration contains FC6 writes for 2000-2003. The additional ten settings are writable and have been verified on the T300 display. PV and Legionella are enabled for routine operation; the other eight are gated for maintenance and initial setup.

`ReadQuality` reports last-attempt and last-success times, age, and current, stale, unknown or failed state for each FC3 block. Values become stale after max(300 seconds, three times the poll interval). Instance status 202 indicates a failed read or missing parent, 203 an invalid interval, and 104 automatic polling disabled. Each measured block's duration and response validity are available in the Symcon debug window.

## Compatibility

- IP-Symcon 8.1 or newer
- A compatible Proxon Modbus parent
- T300 Modbus write access enabled for control actions

## Exported functions

- `PROXONT300_RequestStatus(int $id)`: reads the configured Holding Registers once.
- `PROXONT300_RefreshReadQuality(int $id)`: recalculates per-block freshness and age without Modbus reads.
- `PROXONT300_CreateMonitoringInstance(int $id)`: creates the read-only Monitoring sibling instance.
