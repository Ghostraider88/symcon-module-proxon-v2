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

The 14 usable settings are marked R/W in the write-permission=1 (some) column. Register 2006 has the same mark but allows only raw value 0, so the module omits it. The existing manual configuration confirms FC3 reads and FC6 writes for registers 2000-2003; the other ten controls are implemented but have not been hardware-tested. The workbook shows 0 in its separate write-permission=2 (yes) column for these rows, and its interface overview describes 3x as FC4. Verify the controller permission mode and address convention before using the untested writes.

## Read-only measurements and operating status

The module reads documented Input Registers with FC4 using seven blocks (811-830, 832-841, 842-846, 861, 862, 868-892, and 898-900). These values have no actions and are not automatically enabled for archive logging.

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
| Emergency operation status | 834 | 0 = normal, 1 = emergency operation |
| Compressor/fan/sensor/pressure error durations | 842, 844-846, 869-878 | seconds, per workbook |
| Fault code | 861 | displayed using the 0-16 labels from the existing manual Symcon profile |
| Restart count | 880 | raw integer |

Register 861 is described as binary in the workbook, while the manual Symcon profile presents values 0-16 as named fault codes; the module mirrors that existing profile. Verify it against a nonzero fault before relying on its text. The existing Symcon T300 ModBus configurations confirm FC4 for the manually configured input addresses and apply the documented temperature correction after scaling. The TEST instance previously showed all forty measurement/status values updating. The added diagnostic values cover the remaining manual ModBus entries; raw ADC channels and undocumented state codes remain separate follow-up work.

## Compatibility

- IP-Symcon 8.1 or newer
- A compatible Proxon Modbus parent
- T300 Modbus write access enabled for control actions

The T300 instance in TEST was confirmed active with the original 54 variables before this diagnostic supplement. The new 17 read-only variables still need a library update in Symcon. Holding-register writes have not been tested by this module on hardware.

## Exported functions

- `PROXONT300_RequestStatus(int $id)`: reads the configured T300 values once.
