# Proxon for IP-Symcon

This library integrates PROXON FWT 2.0/3.0 and T300 Modbus systems.

## Modules

- **Proxon System Control** (`ProxonSystemControl`): standard plant controls and status, including bypass state and readable damper position.
- **Proxon System Control Advanced** (`ProxonSystemControlAdvanced`): protected heat pump, compressor, airflow tuning, bypass threshold, and panel temperature correction settings. Create it from the System Control configuration after enabling the advanced controls switch.
- **Proxon Central** (`ProxonCentral`): the room assigned to the central TFT control panel.
- **Proxon Zone** (`ProxonZone`): individual HNBP and NBP room controls, including room setpoint, PTC release, and panel key lock.
- **Proxon Configurator** (`ProxonConfigurator`): discovers the central panel and configured room panels and offers the system control instance.
- **Proxon T300 Control** (`ProxonT300Control`): controls documented Holding Registers; only registers 2000-2003 have write actions enabled by default. Its configuration can create a separate read-only Monitoring instance.
- **Proxon T300 Monitoring** (`ProxonT300Monitoring`): reads 57 T300 Input Register measurements and status values over FC4 without write actions or automatic archive logging.

T300 read-only input values use FC4, matching the workbook and existing Symcon configurations. Holding-register actions are enabled by default only for registers 2000-2003; ten other candidate writes require an explicit advanced-controls switch and remain unverified on hardware.

## Setup

Connect the Proxon Configurator to the Proxon Modbus parent. Use its configuration list to create system and room instances for an FWT, or the T300 Control instance for a T300. Then use the T300 Control configuration action to create its separate Monitoring instance if needed.

## Migration note

The central instance no longer owns the `OperatingMode` and `FanStage` variables. After updating the library, add the new System Control entry through the Configurator. Applying changes to the old central instance removes its managed mode and fan variables; update any scripts or visualizations that refer to those old variable IDs. Advanced controls previously shown in System Control and the room temperature correction previously shown in Proxon Zone are hidden and locked, while their old variables are retained. The new Advanced instance owns these controls.
