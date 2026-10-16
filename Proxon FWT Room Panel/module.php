<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTStatusPolling.php';

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTControlInput.php';

	class ProxonFWTRoomPanel extends IPSModuleStrict {
	    use FWTStatusPolling;

		use FWTControlInput;

		public function Create(): void {
			//Never delete this line!
			parent::Create();

        $this->CreateStatusReadQuality();
			
			$this->RegisterPropertyInteger("ControlPanel", 1);
			$this->RegisterPropertyInteger("Interval", 30);
			$this->RegisterAttributeInteger("AppliedControlPanel", 0);

			$this->RegisterTimer("Poller", 0, "PROXON_RequestStatus(\$_IPS['TARGET']);");
			$this->RegisterTimer('StatusQuality', 0, 'PROXON_RefreshReadQuality($_IPS[\'TARGET\']);');
 
		}

		public function ApplyChanges(): void {
			//Never delete this line!
			parent::ApplyChanges();

        $this->RegisterStatusReadQualityVariable(500);
			$ControlPanel = $this->ReadPropertyInteger("ControlPanel");
			if ($this->ReadAttributeInteger("AppliedControlPanel") !== $ControlPanel) {
				$this->SetBuffer("MiddleTemperature", "");
				$this->WriteAttributeInteger("AppliedControlPanel", $ControlPanel);
			}
			
			$this->RegisterVariableFloat("CurrentTemperature", $this->Translate("Current Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
				"TEMPLATE" => VARIABLE_TEMPLATE_VALUE_PRESENTATION_ROOM_TEMPERATURE,
				"SUFFIX" => " °C",
				"DIGITS" => 1
			], 1);
			$this->RegisterVariableInteger("TargetTemperature", $this->Translate("Target Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SLIDER,
				"TEMPLATE" => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE,
				"MIN" => 0,
				"MAX" => 50,
				"STEP_SIZE" => 1,
				"SUFFIX" => " °C",
				"DIGITS" => 0
			], 2);
			$this->MaintainAction("TargetTemperature", false);
			$this->RegisterVariableBoolean("PTCRelease", $this->Translate("PTC Release"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SWITCH
			], 3);
			$this->EnableAction("PTCRelease");
			$this->RegisterVariableBoolean("ButtonsLocked", $this->Translate("Buttons Locked"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SWITCH
			], 4);
			$this->EnableAction("ButtonsLocked");
			$this->HideLegacySensorTemperatureOffset();
			if (!$this->IsControlPanelValid()) {
				$this->SetTimerInterval("Poller", 0);
				$this->SetStatus(201);
				return;
			}
			$this->SetStatus(102);
			
			$this->ConfigureStatusPolling($this->ReadPropertyInteger('Interval'));
		}

		private function HideLegacySensorTemperatureOffset(): void {
			foreach (IPS_GetChildrenIDs($this->InstanceID) as $ChildID) {
				if (!IPS_VariableExists($ChildID)) {
					continue;
				}

				if (IPS_GetObject($ChildID)["ObjectIdent"] !== "SensorTemperatureOffset") {
					continue;
				}

				$this->MaintainAction("SensorTemperatureOffset", false);
				if (!IPS_SetHidden($ChildID, true)) {
					$this->LogMessage($this->Translate("Could not hide legacy sensor temperature offset."), KL_WARNING);
				}
			}
		}

		public function RequestStatus(): void
		{
			$startedAt = $this->BeginStatusPoll();
			if ($startedAt === null) {
			    return;
			}
			try {
			    $this->PollStatusValues();
			} finally {
			    $this->EndStatusPoll($startedAt, 'FWT Room Panel');
			}
		}

		private function PollStatusValues(): void
		{
			if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
			    return;
			}

			if (!$this->IsControlPanelValid()) {
				$this->SetTimerInterval("Poller", 0);
				$this->SetStatus(201);
				return;
			}

			// We use "modulo 20" to target the HNBP, which has ControlPanel ID 20,
			// but in the ModBus Address space comes always first, therefore Address + 0
			
			// CurrentTemperature -> FC4, 590 + X*3, INT16 (0.1 °C Resolution)
			$Address = 590 + (($this->ReadPropertyInteger("ControlPanel") % 20) * 3);
			$Data = $this->SendMeasuredStatusRead('Register ' . $Address, json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 4, "Address" => $Address , "Quantity" => 1, "Data" => "")), 4);
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			// CurrentTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($Data[1] >= pow(2, 15)) $Data[1] -= pow(2, 16);
			$this->SetValue("CurrentTemperature", $Data[1] / 10.0);

			// MiddleTemperature -> FC3, 233 + X, INT16 (1.0 °C Resolution)
			$Address = 233 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendMeasuredStatusRead('Register ' . $Address, json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")), 4);
			if($Data == false)
				return;
			$MiddleTemperature = (unpack("n*", substr($Data,2)));
			// MiddleTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($MiddleTemperature[1] >= pow(2, 15)) $MiddleTemperature[1] -= pow(2, 16);
			$this->UpdateTargetTemperaturePresentation($MiddleTemperature[1]);
			$this->MaintainAction("TargetTemperature", true);

			// We want to store the MiddleTemperature in a buffer, to use it for SetTemperature
			$this->SetBuffer("MiddleTemperature", $MiddleTemperature[1]);

			// We need both values to calculate the TargetTemperature, because the MiddleTemperature is the "base" for the TargetTemperature			

			// OffsetTemperature -> FC3, 213 + X, INT16 (1.0 °C Resolution)
			$Address = 213 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendMeasuredStatusRead('Register ' . $Address, json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")), 4);
			if($Data == false)
				return;
			$OffsetTemperature = (unpack("n*", substr($Data,2)));
			// OffsetTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($OffsetTemperature[1] >= pow(2, 15)) $OffsetTemperature[1] -= pow(2, 16);

			$this->SetValue("TargetTemperature", $MiddleTemperature[1] + $OffsetTemperature[1]);
			
			// PTCRelease -> FC3, 253 + X, INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 253 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendMeasuredStatusRead('Register ' . $Address, json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")), 4);
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			$this->SetValue("PTCRelease", $Data[1] > 0);			

			// ButtonsLocked -> FC3, 273 + panel index, uint16 (0=unlocked, 1=locked)
			$Address = 273 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendMeasuredStatusRead('Register ' . $Address, json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")), 4);
			if($Data !== false) {
				$ButtonsLocked = unpack("n*", substr($Data, 2));
				$this->SetValue("ButtonsLocked", $ButtonsLocked[1] > 0);
			}
		}

		public function SetTemperature(mixed $Value): void {
			$this->EnsureControlPanelValid();
			$MiddleTemperature = $this->GetBuffer("MiddleTemperature");
			if ($MiddleTemperature === "" || preg_match('/^-?\d+$/D', $MiddleTemperature) !== 1) {
				throw new RuntimeException($this->Translate("A current value must be available before a new target temperature can be set."));
			}
			$Value = $this->NormalizeFWTWholeDegreeTemperature($Value);
			$MiddleTemperature = (int) $MiddleTemperature;
			
			$OffsetTemperature = $Value - $MiddleTemperature;
			if ($OffsetTemperature < -3 || $OffsetTemperature > 3) {
				throw new InvalidArgumentException($this->Translate("Target temperature must be within 3 °C of the room base temperature."));
			}

			// OffsetTemperature -> FC6, 213 + X, INT16 (1.0 °C Resolution)
			$Address = 213 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = pack("n*", $OffsetTemperature);
			$Response = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));
			if ($Response !== false) {
				$this->SetValue("TargetTemperature", $Value);
			}
		}

		public function SetPTC(mixed $Release): void {
			$this->EnsureControlPanelValid();
			$Release = $this->NormalizeFWTBoolean($Release);
			// PTCRelease -> FC6, 253 + X, INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 253 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = pack("n*", $Release ? 1 : 0);
			$Response = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));
			if ($Response !== false) {
				$this->SetValue("PTCRelease", $Release);
			}
		}

		public function SetButtonsLocked(mixed $Locked): void {
			$this->EnsureControlPanelValid();
			$Locked = $this->NormalizeFWTBoolean($Locked);
			$Address = 273 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = pack("n*", $Locked ? 1 : 0);
			$Response = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));
			if ($Response !== false) {
				$this->SetValue("ButtonsLocked", $Locked);
			}
		}

		private function IsControlPanelValid(): bool {
			return $this->IsValidFWTPanelID($this->ReadPropertyInteger("ControlPanel"), 20);
		}

		private function EnsureControlPanelValid(): void {
			if ($this->IsControlPanelValid()) {
				return;
			}

			$this->SetTimerInterval("Poller", 0);
			$this->SetStatus(201);
			throw new InvalidArgumentException($this->Translate("Control panel ID must be between 1 and 20; 20 is the HNBP."));
		}

		private function UpdateTargetTemperaturePresentation(int $MiddleTemperature): void {
			$Presentation = [
				"PRESENTATION" => VARIABLE_PRESENTATION_SLIDER,
				"TEMPLATE" => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE,
				"MIN" => $MiddleTemperature - 3,
				"MAX" => $MiddleTemperature + 3,
				"STEP_SIZE" => 1,
				"SUFFIX" => " °C",
				"DIGITS" => 0
			];
			$VariableID = IPS_GetObjectIDByIdent("TargetTemperature", $this->InstanceID);
			$CurrentPresentation = IPS_GetVariablePresentation($VariableID);
			$CurrentMinimum = (float) ($CurrentPresentation["MIN"] ?? PHP_FLOAT_MIN);
			$CurrentMaximum = (float) ($CurrentPresentation["MAX"] ?? PHP_FLOAT_MIN);
			if ($CurrentMinimum === (float) $Presentation["MIN"] && $CurrentMaximum === (float) $Presentation["MAX"]) {
				return;
			}

			IPS_SetVariableCustomPresentation($VariableID, $Presentation);
		}

		public function RequestAction(string $Ident, mixed $Value): void {
			switch($Ident) {
				case "TargetTemperature":
					$this->SetTemperature($Value);
					break;
				case "PTCRelease":
					$this->SetPTC($Value);
					break;
				case "ButtonsLocked":
					$this->SetButtonsLocked($Value);
					break;
			}
		}
	}
?>
