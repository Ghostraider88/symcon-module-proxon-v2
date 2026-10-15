<?php

class ProxonConfigurator extends IPSModuleStrict
{
	public function GetConfigurationForm(): string
	{
		$form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

		// Noting to do if parent is not active
		if ($this->HasActiveParent() === false) {
			return json_encode($form);
		}

		$ControlPanels = $this->SendDataToParent(json_encode([
			"DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", 
			"Function" => 3, 
			"Address" => 210, 
			"Quantity" => 2, 
			"Data" => "",
		]));
		
		// No response results in empty list
		if ($ControlPanels === false) {			
			return json_encode($form);
		}
		
		$getMainInstanceID = function() {
			$ids = IPS_GetInstanceListByModuleID("{1D1DC6F5-A07B-FC2B-84E4-D68E6B71D401}");
			foreach ($ids as $id) {
				if (IPS_GetInstance($id)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
					continue;
				}
				return $id;
			}
			return null;
		};

		$getZoneInstanceID = function($ControlPanel) {
			$ids = IPS_GetInstanceListByModuleID("{9496FF42-B793-02E3-8271-541651A9085F}");
			foreach ($ids as $id) {
				if (IPS_GetInstance($id)['ConnectionID'] != IPS_GetInstance($this->InstanceID)['ConnectionID']) {
					continue;
				}
				if (IPS_GetProperty($id, "ControlPanel") != $ControlPanel) {
					continue;
				}
				return $id;
			}
			return null;
		};

		// Convert 
		$ControlPanels = unpack("n*", substr($ControlPanels, 2));
		$ControlPanels = ($ControlPanels[2] << 16) + $ControlPanels[1];

		$form['actions'][0]['values'][] = [
			"name" => $this->Translate("Controlpanel Central"), /* ZBP */
			"address" => 0,
			"create" => [
				"moduleID" => "{1D1DC6F5-A07B-FC2B-84E4-D68E6B71D401}",
				"configuration" => new stdClass(),
			],
			"instanceID" => $getMainInstanceID(),
		];

		for ($i = 0; $i < 20; $i++) {
			if (($ControlPanels & (1 << $i)) == 0) {
				continue;
			}
			$name = sprintf($this->Translate("Controlpanel %d"), $i + 1); /* NBP */
			if (($i+1) == 20) {
				$name = $this->Translate("Controlpanel Main"); /* HNBP */
			}
			$form['actions'][0]['values'][] = [
                "name" => $name,
                "address" => ($i + 1),
                "create" => [
                    "moduleID" => "{9496FF42-B793-02E3-8271-541651A9085F}",
                    "configuration" => [
                        "ControlPanel" => $i + 1,
					],
				],
				"instanceID" => $getZoneInstanceID($i + 1),
            ];
		}

		return json_encode($form);
	}
}
