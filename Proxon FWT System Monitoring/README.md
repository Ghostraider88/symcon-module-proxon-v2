# Proxon FWT System Monitoring

This type 3 module reads extended FWT 2.0/3.0 plant values through the connected ModBus gateway. It adds electrical power and consumption estimates, compressor status, fault registers, inverter counters and runtime counters.

The estimated energy counter integrates the total electrical power register from the first successful sample. It persists locally and skips long gaps; it is not a meter reading and contains no consumption from before the first sample. The module never enables archive logging.

Fault reset and controller restart are available only from the instance configuration page and require a confirmation popup. They write the documented FWT service registers. They are not part of normal polling and are not frontend variables.

Electrical consumption values are estimates based on measured power and optional archive aggregates; they are not meter readings.

The monitoring instance exposes a readable `FaultSummary`, a `FaultPresent` marker and `FaultStatusComplete`. The summary only groups documented fault indicators; it does not invent text for unknown codes or bits. Raw fault registers and `FaultDetails` remain available in the backend and are hidden from visualizations. `FaultDetails` lists the read values, unavailable registers and set bit positions in status/flag registers; bit meanings remain unresolved. Without an active Modbus parent, the summary reports that the fault status is unavailable.

The default poll interval is 60 seconds. Use 0 to disable automatic polling while keeping manual updates available, or choose 5 to 86400 seconds. `ReadQuality` contains per-block JSON details, including last-attempt and last-success times, age and current, stale, unknown or failed state. Values become stale after max(300 seconds, three times the poll interval). Instance status 202 indicates a failed read or missing parent, 203 an invalid interval, and 104 automatic polling disabled. Debug output records each FC3/FC4 block's duration and response validity. Reads are split into contiguous register ranges, so unsupported address gaps are not requested.

No measured airflow value is exposed: Symcon reports 0 for both 4x0026 and the checked 3x0398 source. The 4x0026 `Airflow` variable was removed because its register meaning, scaling and measurement point are still unresolved.

The module can estimate device-filter runtime remaining from the PROXON app. Enter the app's remaining time as total hours in the backend, apply the configuration, then run **Capture filter runtime reference**. The module reads 4x0469 once and estimates total filter runtime as that counter value plus the entered remaining hours. Later polls update the remaining estimate from 4x0469. This is an estimate and assumes the app countdown and 4x0469 use the same operating-hour basis, and that the counter represents device-filter operating hours since the last filter change. The month setting 4x0460 is not converted into operating hours. Capture a new reference after a filter change or if 4x0469 resets.

No reference has been captured on the installation. Treat this as an unverified backend estimate until the relationship between the app countdown and 4x0469 is confirmed.

See [REGISTERS.md](REGISTERS.md) for the tile candidate values, variable idents, units, register sources and unresolved register meanings. It covers values from this monitoring module and the standard System Control instance. No visualization files are part of this module documentation.

## Exported functions

- `PROXONMON_RequestStatus(int $id)`: reads all monitoring register groups once.
- `PROXONMON_ResetPendingFaults(int $id)`: invokes the backend fault-reset action.
- `PROXONMON_RestartController(int $id)`: invokes the backend controller-restart action.
- `PROXONMON_CaptureDeviceFilterRuntimeReference(int $id)`: captures the entered app countdown and current runtime counter as a reference.
- `PROXONMON_RefreshReadQuality(int $id)`: recalculates read freshness without Modbus reads.

## Setup

Use **Create Monitoring Instance** in the standard System Control configuration page. The new instance is connected to the same ModBus gateway. Choose a polling interval that fits the project. The module does not change existing ModBus devices or their logging settings.
