# Proxon for IP-Symcon

This library integrates PROXON FWT 1.0, FWT 2.0/3.0 and T300 Modbus systems.

## Modules

- **Proxon FWT 1 Room Control** (ProxonFWT1RoomControl): legacy single-room control for FWT 1.0.
- **Proxon FWT Central Panel** (ProxonFWTCentralPanel): room control assigned to the central TFT panel.
- **Proxon FWT Room Panel** (ProxonFWTRoomPanel): one HNBP or NBP room panel instance per room.
- **Proxon FWT Configurator** (ProxonFWTConfigurator): discovers the central panel and configured room panels and offers system controls.
- **Proxon FWT System Control** (ProxonFWTSystemControl): standard plant controls and status, including bypass state and readable damper position.
- **Proxon FWT System Control Advanced** (ProxonFWTSystemControlAdvanced): protected heat-pump, compressor, airflow tuning, bypass threshold and panel temperature correction settings.
- **Proxon FWT System Monitoring** (ProxonFWTSystemMonitoring): extended FWT measurements, fault status and energy estimates.
- **Proxon T300 Control** (ProxonT300Control): T300 Holding Register controls and setup actions.
- **Proxon T300 Monitoring** (ProxonT300Monitoring): read-only T300 Input Register measurements and status.

T300 read-only input values use FC4, matching the workbook and existing Symcon configurations. The T300 Control instance reads Holding Registers with FC3 and writes with FC6. Routine writes cover 2000-2003 plus PV and Legionella; the other eight writable setup settings require the maintenance/setup switch. The ten additional writable settings have been checked on the T300 display.

All modules are listed under the Proxon vendor and have a single catalog name without aliases.

## Setup

Connect the Proxon FWT Configurator to the Proxon Modbus parent. Use its configuration list to create system and room instances for an FWT, or the T300 Control instance for a T300. Use the System Control configuration action to create its Advanced and Monitoring instances. Use the T300 Control configuration action to create the separate T300 Monitoring instance when needed.

The central panel module handles room control. System-wide operating mode and fan stage are handled by Proxon FWT System Control. For HNBP/NBP room setpoint control, the room target is represented in Modbus as a middle temperature plus an offset.

## Migration note

The central instance no longer owns the OperatingMode and FanStage variables. After updating the library, add the new System Control entry through the Configurator. Applying changes to the old central instance removes its managed mode and fan variables; update any scripts or visualizations that refer to those old variable IDs. Advanced controls previously shown in System Control and the room temperature correction previously shown in the room panel are hidden and locked, while their old variables are retained. The new Advanced instance owns these controls.

For the Module Control update and source rollback procedure, see [module update and rollback](docs/module-update-rollback.md).
