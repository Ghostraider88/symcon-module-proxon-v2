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

## Read-only measurements and operating status

The module also reads documented Input Registers with FC4 using five blocks (`811–830`, `832–841`, `862`, `868–892`, and `898–900`) on the configured polling interval. These values have no actions and are not automatically enabled for archive logging.

| Values | Registers | Conversion |
| --- | --- | --- |
| T5, T6, T20, T21, T13, T11 and T9 temperatures | 811–817 | raw ÷ 10, then subtract 100 °C |
| P14 low-side pressure; P19 defrost differential pressure | 818–819 | raw ÷ 100; bar / Pa |
| Expansion valve position and relay states | 820, 824–828 | raw position; 0 = off, nonzero = on |
| Pressure-derived evaporator temperature; suction/evaporator difference | 829–830 | ÷ 10, with documented −100 °C offset only at 829 |
| Restriction, state and defrost timers; fan speed | 832, 835–841 | seconds or percent as documented |
| Fan speed | 862 | rpm |
| Emergency heater permission; setpoint, firmware, tank average and superheat values | 868, 879–886 | FC4; temperature formats follow the workbook |
| Expansion-valve and post-defrost timers; post-defrost superheat and defrost start temperature | 887–888, 890, 892 | seconds or workbook temperature scaling |
| PV tank level and active heater/heat-pump states | 898–900 | percent; 0 = off, nonzero = on |

The existing Symcon T300 ModBus configurations confirm FC4 for these input addresses and apply the documented temperature correction after scaling. The raw ADC channels, error list/counters and undocumented state codes are left for a later diagnostics step.

## Compatibility

- IP-Symcon 8.1 or newer
- A compatible Proxon Modbus parent
- T300 Modbus write access enabled for control actions

The T300 control instance is present in the TEST area. Existing ModBus instances provide live input-register reads; this module's new grouped FC4 polling still needs a runtime update and verification. Holding-register writes have not been tested by this module on hardware.

## Exported functions

- `PROXONT300_RequestStatus(int $id)`: reads the configured T300 values once.
