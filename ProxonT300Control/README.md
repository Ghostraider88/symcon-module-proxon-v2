# Proxon T300 Control

The T300 control instance exposes its documented operating mode, water-temperature settings, electric-heater options and clock values as readable and writable variables. It polls the grouped settings every 60 seconds by default and provides an Update Values action in the instance configuration.

The values and raw ranges come from the supplied `Modbus Liste FWT2.0 ver2 T300.xlsx`, sheet `Holding Register`. The language register is omitted because its documented minimum and maximum are both 0.

## Setup

Connect the Proxon Configurator to the Modbus parent and select **T300 Control**. Enable Modbus write access in the T300 controller before using the controls.

## Register mapping

| Variable | Register | Range / conversion |
| --- | ---: | --- |
| Operating Mode | 2002 | 0 = Off, 1 = Demand, 2 = LF1, 3 = LF2 |
| Normal Water Temperature | 2000 | 20–55 °C, raw value ÷ 10 |
| Electric Heater Enabled | 2001 | 0 = off, 1 = on |
| Hour / Minute | 2150 / 2151 | 0–23 / 0–59 |
| Weekday / Day / Month / Year | 2152–2155 | 1–7 / 1–31 / 1–12 / 0–99 |
| Daylight Saving Time | 2008 | 0 = off, 1 = on |
| Electric Heater Temperature | 2003 | 20–70 °C, raw value ÷ 10 |
| Screen Timeout | 2009 | 0–240; 0 = off, unit unspecified in the register list |
| Legionella Function | 2025 | 0 = off, 1 = on |
| PV Function | 2010 | 0 = off, 1 = on |

The table places these values in the `Holding Register` sheet and marks them writable. This module therefore reads them with FC3 and writes individual values with FC6, following the existing Proxon module pattern. The same workbook labels the addresses with a `3x` prefix and describes `3x` as FC4 in its interface overview. That conflict has not yet been resolved against a T300 controller; verify the register function and address convention on a dedicated test device before using this module to write settings.

## Compatibility

- IP-Symcon 8.1 or newer
- A compatible Proxon Modbus parent
- T300 Modbus write access enabled for control actions

The T300 hardware path has not yet been exercised in Symcon.

## Exported functions

- `PROXONT300_RequestStatus(int $id)`: reads the configured T300 values once.
