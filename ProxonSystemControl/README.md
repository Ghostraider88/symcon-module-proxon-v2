# Proxon System Control

This instance controls system-wide functions of PROXON FWT 2.0 and 3.0 through the configured Modbus parent.

## Functions

| Variable | Register | Use |
| --- | --- | --- |
| Operating Mode | 4x0016 | 0 Off, 1 Summer, 2 Winter, 3 ECO Comfort, 4 Stove, 6 Emergency |
| Fan Stage | 4x0022 | Selects fan stage 1 to 4 |
| Cooling Enabled | 4x0062 | Enables or disables demand-based cooling |
| Intensive Ventilation Duration | 4x0133 | User-selectable intensive ventilation duration in minutes (0 to 1440) |

The T300 communication setting is intentionally not included yet.

## Installation

Create one instance through the Proxon Configurator for each Modbus connection. The instance is system-wide and is not assigned to a room or control panel.

## Compatibility

IP-Symcon 8.1 or newer and the Proxon Modbus parent connection are required.
