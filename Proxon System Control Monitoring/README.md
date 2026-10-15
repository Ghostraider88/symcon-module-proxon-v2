# Proxon System Control Monitoring

This type 3 module reads extended FWT 2.0/3.0 plant values through the connected ModBus gateway. It adds current electrical power, controller-reported COP/JAZ registers, compressor status, fault registers, inverter counters and runtime counters.

The estimated energy counter integrates the total electrical power register from the first successful sample. It persists locally and skips long gaps; it is not a meter reading and contains no consumption from before the first sample. The module never enables archive logging.

Fault reset and controller restart are available only from the instance configuration page and require a confirmation popup. They write the documented FWT service registers. They are not part of normal polling and are not frontend variables.

COP/JAZ values are reported by the controller. They are not independently validated against measured thermal energy. The FWT register sheet marks total JAZ over 365 days and its age as not implemented, so the module does not expose those two registers.

The monitoring instance exposes a readable `FaultSummary`, a `FaultPresent` marker and `FaultStatusComplete`. The summary only groups documented fault indicators; it does not invent text for unknown codes or bits. Raw fault registers and `FaultDetails` remain available in the backend and are hidden from visualizations. `FaultDetails` lists the read values and any unavailable fault registers for backend diagnosis. Without an active Modbus parent, the summary reports that the fault status is unavailable.

See [REGISTERS.md](REGISTERS.md) for the tile candidate values, variable idents, units, register sources and unresolved register meanings. It covers values from this monitoring module and the standard System Control instance. No visualization files are part of this module documentation.

## Setup

Use **Create Monitoring Instance** in the standard System Control configuration page. The new instance is connected to the same ModBus gateway. Choose a polling interval that fits the project. The module does not change existing ModBus devices or their logging settings.
