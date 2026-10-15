# Proxon T300 Control

The control instance handles the T300 Holding Registers. It polls the documented settings every 60 seconds by default and exposes an Update Values action.

Registers 2000-2003 are the only settings with write support confirmed by the existing Symcon configuration. Their actions are enabled by default. The other ten workbook write candidates stay read-only unless **Enable Advanced Controls** is explicitly selected. Their hardware write behavior has not been verified.

Use the **Create Monitoring Instance** action to add the separate read-only FC4 module beside this instance. Remove an older T300 Control instance before switching to this split module layout so its previous measurement variables do not remain in the object tree.

## Register mapping

| Variable | Register | Range / conversion |
| --- | ---: | --- |
| Operating Mode | 2002 | 0 = Off, 1 = Demand, 2 = LF1, 3 = LF2 |
| Normal Water Temperature | 2000 | 20-55 °C, raw value ÷ 10 |
| Electric Heater Enabled | 2001 | 0 = off, 1 = on |
| Electric Heater Temperature | 2003 | 20-70 °C, raw value ÷ 10 |
| Daylight Saving Time | 2008 | 0 = off, 1 = on; advanced |
| Screen Timeout | 2009 | 0-240; advanced, unit unspecified |
| Legionella Function / PV Function | 2025 / 2010 | Boolean; advanced |
| Hour / Minute / Weekday / Day / Month / Year | 2150-2155 | Clock fields; advanced |

The workbook marks fifteen rows R/W at write-permission level 1 (some). Fourteen have a nontrivial allowed range, and all fourteen are implemented with matching limits. Register 2006 (Language) is omitted because its documented range is fixed at 0. The inspected manual Symcon configuration confirms FC6 writes only for 2000-2003; these four are enabled by default. The other ten stay read-only until Advanced Controls is selected. Their hardware write behavior has not been verified, and this module has sent no writes.

## Compatibility

- IP-Symcon 8.1 or newer
- A compatible Proxon Modbus parent
- T300 Modbus write access enabled for control actions

## Exported functions

- `PROXONT300_RequestStatus(int $id)`: reads the configured Holding Registers once.
- `PROXONT300_CreateMonitoringInstance(int $id)`: creates the read-only Monitoring sibling instance.
