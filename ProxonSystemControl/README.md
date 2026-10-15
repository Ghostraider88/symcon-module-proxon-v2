# Proxon System Control

This instance controls and monitors system-wide functions of PROXON FWT 2.0 and 3.0 through the configured Modbus parent. It is not assigned to a room or control panel.

## Operating controls

| Function | Register | Use |
| --- | --- | --- |
| Operating mode | 4x0016 | 0 Off, 1 Summer, 2 Winter, 3 ECO Comfort, 4 Stove, 6 Emergency |
| Fan stage | 4x0022 | Selects fan stage 1 to 4 |
| Cooling enabled | 4x0062 | Enables or disables demand-based cooling |
| Intensive ventilation duration | 4x0133 | Selectable duration from 0 to 1440 minutes |
| PTC thresholds and global release | 4x0317, 4x0318, 4x0325 | Configures system-wide auxiliary heater operation |
| Heat pump limits | 4x0034, 4x0041–4x0046, 4x0048, 4x0052, 4x0134 | Start/stop thresholds, cooling outdoor temperature, compressor temperature limits, continuous-operation outdoor threshold and power limits |
| Ventilation airflow | 4x0002–4x0009, 4x0011–4x0012 | Airflow settings for each fan stage and fan limits |
| Temperature source | 4x0115–4x0116 | Selects panel or internal board sensor for zone 1 and zone 2 |
| CO2/RF control | 4x0117–4x0118, 4x0394–4x0397, 4x0466 | Sets thresholds and assigns up to five sensors to rooms |
| Filter service settings | 4x0460–4x0462 | Filter lifetime and recirculation filter presence |

The room assignment values for CO2/RF sensors are 0 unassigned, 1 central panel room, 2 HNBP room and 3–21 NBP 1–19.

## Status values

The instance displays the actual operating and heat pump modes, fan speeds and stages, fresh/supply/extract/exhaust air temperatures, connected panels, CO2/RF readings for the configured sensor count, and device/recirculation filter runtime.

The configurator already uses the NBP connection bitfields at 4x0210–4x0211 to list panels present in the project. It does not create instances automatically.

## Configuration

| Property | Default | Use |
| --- | --- | --- |
| Interval | 30 seconds | Poll interval for control and status values |
| CO2/RF sensor count | 1 | Number of installed sensors to show, from 1 to 5 |

The plant must already allow Modbus writes. The instance does not change the write permission register.

## Installation

Create one instance through the Proxon Configurator for each Modbus connection. The instance is system-wide. Create a separate Proxon Zone instance for each installed HNBP or NBP.

## Compatibility

IP-Symcon 8.1 or newer and the Proxon Modbus parent connection are required. T300 functions are not included.
