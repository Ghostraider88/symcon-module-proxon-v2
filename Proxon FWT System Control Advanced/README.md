# Proxon FWT System Control Advanced

This instance contains settings that can alter protective limits, compressor output, air distribution, or panel temperature readings. It is available only after the **Enable Advanced Controls** switch is enabled in the associated Proxon FWT System Control instance.

The association is normally set when the Advanced instance is created from the System Control configuration page. The Advanced instance reads and writes through the same Modbus parent connection.

## Advanced settings

- Compressor and heat pump thresholds, temperatures and power limits
- Bypass temperature threshold
- PTC thresholds and global release
- Fan minimum/maximum airflow and stage airflow values
- Temperature source selection for zones 1 and 2
- Temperature correction for HNBP and NBP panels

Panel correction variables use `Panel1TemperatureOffset` through `Panel19TemperatureOffset` for NBP 1–19 and `Panel20TemperatureOffset` for the HNBP. Only panels reported as connected by the plant are shown and can be changed.

The current fan stage and measured fan RPM stay in the standard System Control instance. Bypass status and damper position are read-only standard status values.

Disabling Advanced Controls in the associated System Control instance blocks all writes from this instance; read-only polling continues. Existing advanced variables previously shown in System Control or Proxon FWT Room Panel are retained but hidden and made read-only when those modules are updated; their history is not intentionally deleted. Scripts and visualizations that refer to the old variable IDs need to be updated to point to the variables in this instance.

## Compatibility

IP-Symcon 8.1 or newer and a Proxon Modbus parent connection are required. T300 functions are not included.
