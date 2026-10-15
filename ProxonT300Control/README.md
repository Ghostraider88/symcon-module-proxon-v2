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

The fourteen documented candidates are marked R/W in the workbook's write-permission=1 (some) column. Register 2006 is omitted because its only documented value is 0. For registers other than 2000-2003, the workbook has no confirmed write permission and describes 3x as FC4. No writes have been tested against hardware by this module.

## Compatibility

- IP-Symcon 8.1 or newer
- A compatible Proxon Modbus parent
- T300 Modbus write access enabled for control actions

## Exported functions

- `PROXONT300_RequestStatus(int $id)`: reads the configured Holding Registers once.
- `PROXONT300_CreateMonitoringInstance(int $id)`: creates the read-only Monitoring sibling instance.
