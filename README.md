# Proxon for IP-Symcon

This library integrates PROXON FWT 2.0 and 3.0 Modbus systems.

## Modules

- **Proxon System Control** (`ProxonSystemControl`): standard plant controls and status, including bypass state and readable damper position.
- **Proxon System Control Advanced** (`ProxonSystemControlAdvanced`): protected heat pump, compressor, airflow tuning, bypass threshold, and panel temperature correction settings. Create it from the System Control configuration after enabling the advanced controls switch.
- **Proxon Central** (`ProxonCentral`): the room assigned to the central TFT control panel.
- **Proxon Zone** (`ProxonZone`): individual HNBP and NBP room controls, including room setpoint, PTC release, and panel key lock.
- **Proxon Configurator** (`ProxonConfigurator`): discovers the central panel and configured room panels and offers the system control instance.

The T300 communication setting is not included in the current implementation.

## Setup

Connect the Proxon Configurator to the Proxon Modbus parent. Use its configuration list to create the central room instance, the system control instance, and each detected HNBP/NBP room instance.

## Migration note

The central instance no longer owns the `OperatingMode` and `FanStage` variables. After updating the library, add the new System Control entry through the Configurator. Applying changes to the old central instance removes its managed mode and fan variables; update any scripts or visualizations that refer to those old variable IDs. Advanced controls previously shown in System Control and the room temperature correction previously shown in Proxon Zone are hidden and locked, while their old variables are retained. The new Advanced instance owns these controls.
