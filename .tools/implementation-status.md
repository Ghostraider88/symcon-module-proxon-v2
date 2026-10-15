# Implementation status

## Anlagensteuerung und Raumsteuerung — 2026-10-15

- Implemented: separate system control instance for operating mode, fan stage, cooling release, and intensive ventilation duration; central room PTC control; per-HNBP/NBP room setpoint, PTC release, and panel key lock.
- Checked: GLT register addresses against the supplied Modbus table; all repository JSON files parse; library and module GUIDs are unique; new module folder/name/class match; `git diff --check` is clean.
- Not run: IP-Symcon runtime or hardware writes. PHP CLI is not installed in the development shell.
- Open for hardware confirmation: the device behavior when writing the duration at 4x0133 and the displayed state of the cooling release at 4x0062. T300 integration is deferred.
