<?
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
				"TEMPLATE" => VARIABLE_TEMPLATE_VALUE_PRESENTATION_ROOM_TEMPERATURE
			], 1);
			$this->RegisterVariableFloat("TargetTemperature", $this->Translate("Target Temperature"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_SLIDER,
				"TEMPLATE" => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE
			], 2);
			$this->EnableAction("TargetTemperature");
			$this->RegisterVariableInteger("OperatingMode", $this->Translate("Operating Mode"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_ENUMERATION,
				"DISPLAY" => 0,
				"LAYOUT" => 1,
				"OPTIONS" => json_encode([
					["Value" => 0, "Caption" => $this->Translate("Off"), "IconActive" => false, "IconValue" => ""],
					["Value" => 1, "Caption" => $this->Translate("Eco Summer"), "IconActive" => false, "IconValue" => ""],
					["Value" => 2, "Caption" => $this->Translate("Eco Winter"), "IconActive" => false, "IconValue" => ""],
					["Value" => 9, "Caption" => $this->Translate("Test"), "IconActive" => false, "IconValue" => ""]
			])
			], 3);
			$this->EnableAction("OperatingMode");
			$this->RegisterVariableInteger("FanStage", $this->Translate("Fan Stage"), [
				"PRESENTATION" => VARIABLE_PRESENTATION_ENUMERATION,
				"DISPLAY" => 0,
				"LAYOUT" => 1,
				"OPTIONS" => json_encode([
					["Value" => 1, "Caption" => $this->Translate("Fan Stage 1"), "IconActive" => false, "IconValue" => ""],
					["Value" => 2, "Caption" => $this->Translate("Fan Stage 2"), "IconActive" => false, "IconValue" => ""],
					["Value" => 3, "Caption" => $this->Translate("Fan Stage 3"), "IconActive" => false, "IconValue" => ""],
					["Value" => 4, "Caption" => $this->Translate("Fan Stage 4"), "IconActive" => false, "IconValue" => ""]
			])
			], 4);
			$this->EnableAction("FanStage");
			
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
			// OperatingMode -> FC3, 16, uint16 (0=Off, 1=Eco Summer, 2=Eco Winter, 9=Test)
			$Address = 16;
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$OperatingMode = (unpack("n*", substr($Data,2)));
			$this->SetValue("OperatingMode", $OperatingMode[1]);

			// FanStage -> FC3, 22, uint16 (1-4)
			$Address = 22;
			$Data = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 3, "Address" => $Address , "Quantity" => 1, "Data" => "")));
			if($Data == false)
				return;
			$FanStage = (unpack("n*", substr($Data,2)));
			$this->SetValue("FanStage", $FanStage[1]);
		}

		public function SetTemperature(float $Value): void {
			$Address = 70;
			$Data = pack("n*", intval($Value * 100));
			$this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));

			$this->SetValue("TargetTemperature", $Value);
		}

		public function SetOperatingMode(int $Value): void {
			if (!in_array($Value, [0, 1, 2, 9], true)) {
				throw new InvalidArgumentException("Unsupported operating mode.");
			}

			$Address = 16;
			$Data = pack("n*", $Value);
			$Response = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));
			if ($Response === false) {
				return;
			}

			$this->SetValue("OperatingMode", $Value);
		}

		public function SetFanStage(int $Value): void {
			if ($Value < 1 || $Value > 4) {
				throw new InvalidArgumentException("Fan stage must be between 1 and 4.");
			}

			$Address = 22;
			$Data = pack("n*", $Value);
			$Response = $this->SendDataToParent(json_encode(Array("DataID" => "{E310B701-4AE7-458E-B618-EC13A1A6F6A8}", "Function" => 6, "Address" => $Address , "Quantity" => 1, "Data" => bin2hex($Data))));
			if ($Response === false) {
				return;
			}

			$this->SetValue("FanStage", $Value);
		}

		public function RequestAction(string $Ident, mixed $Value): void {
			switch($Ident) {
				case "TargetTemperature":
					$this->SetTemperature($Value);
					break;
				case "OperatingMode":
					$this->SetOperatingMode($Value);
					break;
				case "FanStage":
					$this->SetFanStage($Value);
					break;
			}
		}
	}
?>
