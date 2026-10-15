# Proxon System Control Monitoring

This type 3 module reads extended FWT 2.0/3.0 plant values through the connected ModBus gateway. It adds electrical power and consumption estimates, compressor status, fault registers, inverter counters and runtime counters.

The estimated energy counter integrates the total electrical power register from the first successful sample. It persists locally and skips long gaps; it is not a meter reading and contains no consumption from before the first sample. The module never enables archive logging.

Fault reset and controller restart are available only from the instance configuration page and require a confirmation popup. They write the documented FWT service registers. They are not part of normal polling and are not frontend variables.

Electrical consumption values are estimates based on measured power and optional archive aggregates; they are not meter readings.

The monitoring instance exposes a readable `FaultSummary`, a `FaultPresent` marker and `FaultStatusComplete`. The summary only groups documented fault indicators; it does not invent text for unknown codes or bits. Raw fault registers and `FaultDetails` remain available in the backend and are hidden from visualizations. `FaultDetails` lists the read values, unavailable registers and set bit positions in status/flag registers; bit meanings remain unresolved. Without an active Modbus parent, the summary reports that the fault status is unavailable.

No measured airflow value is exposed: Symcon reports 0 for both 4x0026 and the checked 3x0398 source. The 4x0026 `Airflow` variable was removed because its register meaning, scaling and measurement point are still unresolved.

See [REGISTERS.md](REGISTERS.md) for the tile candidate values, variable idents, units, register sources and unresolved register meanings. It covers values from this monitoring module and the standard System Control instance. No visualization files are part of this module documentation.

## Setup

Use **Create Monitoring Instance** in the standard System Control configuration page. The new instance is connected to the same ModBus gateway. Choose a polling interval that fits the project. The module does not change existing ModBus devices or their logging settings.
