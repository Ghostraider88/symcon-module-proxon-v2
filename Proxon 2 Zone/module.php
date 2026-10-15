<?
	class ProxonZone extends IPSModuleStrict {
		public function Create(): void {
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyInteger("ControlPanel", 1);
			$this->RegisterPropertyInteger("Interval", 30);

			$this->RegisterTimer("Poller", 0, "PROXON_RequestStatus(\$_IPS['TARGET']);");
 
		}

		public function ApplyChanges(): void {
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableFloat("CurrentTemperature", $this->Translate("Current Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
				"TEMPLATE" => VARIABLE_TEMPLATE_VALUE_PRESENTATION_ROOM_TEMPERATURE
			], 1);
			$this->RegisterVariableInteger("TargetTemperature", $this->Translate("Target Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SLIDER,
				"TEMPLATE" => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE
			], 2);
			$this->EnableAction("TargetTemperature");
			$this->RegisterVariableBoolean("PTCRelease", $this->Translate("PTC Release"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SWITCH
			], 3);
			$this->EnableAction("PTCRelease");
			
			$this->SetTimerInterval("Poller", $this->ReadPropertyInteger("Interval") * 1000);
		}

		public function RequestStatus(): void {
			// We use "modulo 20" to target the HNBP, which has ControlPanel ID 20,
			// but in the ModBus Address space comes always first, therefore Address + 0
			
			// CurrentTemperature -> FC4, 590 + X*3, INT16 (0.1 °C Resolution)
			$Address = 590 + (($this->ReadPropertyInteger("ControlPanel") % 20) * 3);
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 4, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			// CurrentTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($Data[1] >= pow(2, 15)) $Data[1] -= pow(2, 16);
			$this->SetValue("CurrentTemperature", $Data[1] / 10.0);

			// MiddleTemperature -> FC3, 233 + X, INT16 (1.0 °C Resolution)
			$Address = 233 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$MiddleTemperature = (unpack("n*", substr($Data,2)));
			// MiddleTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($MiddleTemperature[1] >= pow(2, 15)) $MiddleTemperature[1] -= pow(2, 16);

			// We want to store the MiddleTemperature in a buffer, to use it for SetTemperature
			$this->SetBuffer("MiddleTemperature", $MiddleTemperature[1]);

			// We need both values to calculate the TargetTemperature, because the MiddleTemperature is the "base" for the TargetTemperature			

			// OffsetTemperature -> FC3, 213 + X, INT16 (1.0 °C Resolution)
			$Address = 213 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$OffsetTemperature = (unpack("n*", substr($Data,2)));
			// OffsetTemperature is a signed value, so we need to convert it (there is no value for unpacking a signed short)
			if($OffsetTemperature[1] >= pow(2, 15)) $OffsetTemperature[1] -= pow(2, 16);

			$this->SetValue("TargetTemperature", $MiddleTemperature[1] + $OffsetTemperature[1]);
			
			// PTCRelease -> FC3, 253 + X, INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 253 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$Data = (unpack("n*", substr($Data,2)));
			$this->SetValue("PTCRelease", $Data[1] > 0);			
		}

		public function SetTemperature(int $Value): void {
			$MiddleTemperature = $this->GetBuffer("MiddleTemperature");
			if ($MiddleTemperature === "") {
				die($this->Translate("A current value must be available before a new target temperature can be set."));
			}
			
			$OffsetTemperature = $Value - intval($MiddleTemperature);

			// OffsetTemperature -> FC6, 213 + X, INT16 (1.0 °C Resolution)
			$Address = 213 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = pack("n*", $OffsetTemperature);
			$this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));

			$this->SetValue("TargetTemperature", $Value);
		}

		public function SetPTC(bool $Release): void {
			// PTCRelease -> FC6, 253 + X, INT16 (0 = Gesperrt, 1 = Freigegeben)
			$Address = 253 + ($this->ReadPropertyInteger("ControlPanel") % 20);
			$Data = pack("n*", $Release ? 1 : 0);
			$this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));

			$this->SetValue("PTCRelease", $Release);
		}

		public function RequestAction(string $Ident, mixed $Value): void {
			switch($Ident) {
				case "TargetTemperature":
					$this->SetTemperature($Value);
					break;
				case "PTCRelease":
					$this->SetPTC($Value);
					break;
			}
		}
	}
?>