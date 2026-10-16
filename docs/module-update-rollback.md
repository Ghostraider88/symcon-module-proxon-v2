# Proxon module update and rollback

This procedure applies to updates of the Proxon library through IP-Symcon Module Control. It is intended for a TEST system first. A production rollout remains a separate, explicitly planned step.

## Before an update

1. Record the repository branch and exact commit currently installed, together with the library version and build shown in Symcon.
2. Ensure that a known-good rollback branch is available in the repository. Module Control selects repository branches, so a rollback target must be published as a branch; recording a commit hash alone is not enough to select it there.
3. Record the relevant instance settings, including room-panel assignment, polling intervals, configured sensor count, and the Advanced-to-System-Control link. Keep the normal Symcon backup process available for broader recovery.
4. Keep the manually configured Modbus devices, gateways, scripts, archives, and visualizations unchanged during this module test.

## Update through Module Control

Use **Modules** in the Symcon object tree. The gear icon next to the Proxon repository selects its branch. Select the intended test branch, check for updates, and apply the offered update. Symcon documents this branch and update flow in [Module Control](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/module-control/).

After the update, inspect the affected instance configuration and status. Confirm that parent connections, panel assignment, interval, sensor count, and the Advanced reference still match the recorded values. Verify that existing variables and archive settings are still present. Do not send a manual Modbus write as part of this source update.

## Roll back the module source

1. Stop further testing and note the observed error and time.
2. In Git, publish or select the recorded known-good source as a rollback branch. Use the exact source revision that was installed before the failed update.
3. In Module Control, use the repository gear to select that rollback branch, check for updates, and apply the available update.
4. Reopen the affected instances. Check their status, parent connection, configuration values, and the Advanced reference. Confirm that monitoring resumes and review the Symcon log for new Proxon errors.
5. Leave any extra module-owned status or quality variables in place until their references, archive settings, and ownership have been checked. Do not delete variables or instances as a generic rollback step.

To resume development after recovery, select the intended development or release branch in Module Control and update only after that branch contains a reviewed fix.

## Configuration and data limits

The review-remediation changes preserve the existing module and data-flow GUIDs, public prefixes, and managed variable identifiers and types. The room-panel sensor-count change retains existing sensor variables and their identities when the configured count is reduced, then reuses them if the count is increased. New poll-quality state may be present only in the newer module version; an older version may simply leave those objects unused.

This rollback procedure does not recreate variables, identifiers, or archive history removed by an earlier migration. In particular, follow the existing migration note in the root README for the earlier move of plant operating mode and fan stage from the central room panel to System Control. Restore objects removed by an older migration only from a suitable Symcon backup and after checking scripts and visualizations that reference them.

No rollback or Module Control update was executed on Symcon while preparing this document. The procedure must be exercised on TEST before relying on it for a future release.
