<?php

declare(strict_types=1);

class ProxonCentral extends IPSModuleStrict {
		public function Create(): void {
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyInteger("Interval", 30);

			$this->RegisterTimer("Poller", 0, "PROXON_RequestStatus(\$_IPS['TARGET']);");
 
		}

		public function ApplyChanges(): void {
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableFloat("CurrentTemperature", $this->Translate("Current Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
				"TEMPLATE" => VARIABLE_TEMPLATE_VALUE_PRESENTATION_ROOM_TEMPERATURE,
				"SUFFIX" => " °C",
				"DIGITS" => 1
			], 1);
			$this->RegisterVariableFloat("TargetTemperature", $this->Translate("Target Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SLIDER,
				"TEMPLATE" => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE,
				"MIN" => 10,
				"MAX" => 30,
				"STEP_SIZE" => 1,
				"SUFFIX" => " °C",
				"DIGITS" => 0
			], 2);
			$this->EnableAction("TargetTemperature");
			$this->RegisterVariableBoolean("PTCRelease", $this->Translate("PTC Release"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SWITCH
			], 3);
			$this->EnableAction("PTCRelease");

			// Operating mode and fan stage now belong to the separate system control instance.
			$this->MaintainVariable("OperatingMode", $this->Translate("Operating Mode"), VARIABLETYPE_INTEGER, "", 4, false);
			$this->MaintainVariable("FanStage", $this->Translate("Fan Stage"), VARIABLETYPE_INTEGER, "", 5, false);
			
			$this->SetTimerInterval("Poller", $this->ReadPropertyInteger("Interval") * 1000);
		}

		public function RequestStatus(): void {
			// CurrentTemperature -> FC4, 263, INT16 (0.01 °C Resolution)
			$Address = 263;
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 4, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			// CurrentTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($Data[1] >= pow(2, 15)) $Data[1] -= pow(2, 16);
			$this->SetValue("CurrentTemperature", $Data[1] / 100.0);

			// TargetTemperature -> FC3, 70, INT16 (0.01 °C Resolution)
			$Address = 70;
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$TargetTemperature = (unpack("n*", substr($Data,2)));
			// TargetTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($TargetTemperature[1] >= pow(2, 15)) $TargetTemperature[1] -= pow(2, 16);

			$this->SetValue("TargetTemperature", $TargetTemperature[1] / 100.0);			
			// PTCRelease -> 4x0187, FC3, uint16 (0=locked, 1=released)
			$Address = 187;
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data !== false) {
				$PTCRelease = unpack("n*", substr($Data, 2));
				$this->SetValue("PTCRelease", $PTCRelease[1] > 0);
			}
		}

		public function SetTemperature(float $Value): void {
			if ($Value < 10 || $Value > 30) {
				throw new InvalidArgumentException($this->Translate("Target temperature must be between 10 and 30 °C."));
			}

			// The main control panel only supports whole-degree setpoints.
			$Value = (float) round($Value);

			$Address = 70;
			$Data = pack("n*", (int) round($Value * 100));
			$Response = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));
			if ($Response !== false) {
				$this->SetValue("TargetTemperature", $Value);
			}
		}

		public function SetPTC(bool $Release): void {
			$Address = 187;
			$Data = pack("n*", $Release ? 1 : 0);
			$Response = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));
			if ($Response !== false) {
				$this->SetValue("PTCRelease", $Release);
			}
		}

		public function RequestAction(string $Ident, mixed $Value): void {
			switch($Ident) {
				case "TargetTemperature":
					$this->SetTemperature((float) $Value);
					break;
				case "PTCRelease":
					$this->SetPTC((bool) $Value);
					break;
			}
		}
	}
?>
