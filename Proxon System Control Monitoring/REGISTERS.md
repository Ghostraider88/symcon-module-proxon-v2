# FWT-Werte für eine Anlagenkachel

Diese Seite beschreibt verfügbare Variablen aus den Instanzen **Proxon System Control** und **Proxon System Control Monitoring**. Eine künftige Visualisierung kann die Werte über den jeweiligen Variablen-Ident der ausgewählten Instanz lesen. Diese Änderung legt die Datenbasis fest; Visualisierungsdateien werden nicht geändert.

`FC04` bezeichnet Function Code 4 (Input Register), `FC03` Function Code 3 (Holding Register). Die Registerbezeichnungen folgen der FWT-Registerliste. „Geklärt“ bedeutet, dass Bezeichnung und Skalierung aus der Registerliste und der aktuellen Modullogik hervorgehen. Es bestätigt keine zusätzliche, nicht dokumentierte Anlagenphysik.

## Empfohlene Kachelwerte

| Instanz | Ident | Einheit | Bedeutung | Quelle / Status |
|---|---|---:|---|---|
| System Control | `ActualOperatingMode` | Text | Von der Steuerung gemeldete Betriebsart | FC04 / 4x0023; Zuordnung im Modul vorhanden |
| System Control | `BypassStatus` | Text | Bypass offen oder geschlossen | FC04 / 4x0222; 0 = geschlossen, 1 = offen |
| System Control | `DamperPosition` | Text | Gemeldete Schieberstellung | FC04 / 4x0159; fünf Stellungen im Modul beschriftet |
| System Control | `SupplyAirTemperature` | °C | Zulufttemperatur | FC04 / 4x0195, signed / 100 |
| System Control | `FreshAirTemperature` | °C | Außen-/Frischlufttemperatur laut Registerbezeichnung | FC04 / 4x0198, signed / 100 |
| Monitoring | `TotalElectricalPower` | W | Elektrische Gesamtleistung laut FWT | FC04 / 4x0025, Registerwert / 10 |
| Monitoring | `FaultPresent` | Boolean | Mindestens ein gelesener Fehlerindikator ist ungleich null | Zusammenfassung der unten aufgeführten Fehlerregister; bei lückenhaftem Lesen zusätzlich `FaultStatusComplete` beachten |
| Monitoring | `FaultSummary` | Text | Lesbare Störungsmeldung, ohne erfundene Code- oder Bitübersetzung | Aus den bestätigten Fehlerindikatoren abgeleitet |
| Monitoring | `FaultStatusComplete` | Boolean | Alle zehn für die Störungsübersicht verwendeten Register wurden beim letzten Poll gelesen | Gültigkeit der beiden Störungswerte darüber |
| Monitoring | `Airflow` | m³/h | Luftvolumenstrom laut Registerliste | FC04 / 4x0026, Registerwert / 10; Messgröße/Bezug ungeklärt |
| Monitoring | `TotalCOPJAZ1Hour` | Verhältniszahl | Vom Regler gemeldeter Gesamt-COP/JAZ für 1 Stunde | FC04 / 4x0033, Wert / 100; Berechnungsdefinition nicht unabhängig verifiziert |

`FaultDetails` und die numerischen Fehlerregister sind Backend-Diagnosewerte. Sie werden nicht als Kachelwerte empfohlen. `EnergySinceFirstSample` ist eine lokale Leistungssummen-Schätzung, kein Zählerstand.

## Standard-System-Control: Status- und Bedienwerte

| Ident | Typ / Einheit | Bedeutung | Quelle | Einordnung |
|---|---|---|---|---|
| `OperatingMode` | Auswahl, Integer | Soll-Betriebsart: Aus, Sommer, Winter, Eco Comfort, Ofen oder Notbetrieb | Schreibregister FC04 / 4x0016 | Bedienwert, kein Messwert |
| `FanStage` | Auswahl 1–4 | Angeforderte Lüfterstufe | Schreibregister FC04 / 4x0022 | Bedienwert, kein Messwert |
| `CoolingEnabled` | Boolean | Kühlfunktion freigeben/sperren | FC04 / 4x0062 | Bedienwert |
| `ActualOperatingMode` | Text | Aktuell von der FWT gemeldete Betriebsart | FC04 / 4x0023 | Geeignet für Statusanzeige |
| `ActualHeatPumpMode` | Text | Vom Modul dekodierter Wärmepumpenmodus | Derzeit FC03 / 4x0069 | **Quelle ungeklärt:** Abweichung zur Registerliste (4x0241); vor Kachelverwendung verifizieren |
| `SupplyFanRPM` | rpm | Drehzahl Zuluftventilator | FC04 / 4x0000 | Statuswert |
| `ExtractFanRPM` | rpm | Drehzahl Abluftventilator | FC04 / 4x0001 | Statuswert |
| `SupplyFanStageActual` | Stufe | Tatsächliche Zuluftstufe | FC04 / 4x0154 | Statuswert |
| `ExtractFanStageActual` | Stufe | Tatsächliche Abluftstufe | FC04 / 4x0211 | Statuswert |
| `SupplyAirTemperature` | °C | Zulufttemperatur | FC04 / 4x0195, signed / 100 | Statuswert |
| `ExtractAirTemperature` | °C | Ablufttemperatur | FC04 / 4x0196, signed / 100 | Statuswert |
| `ExhaustAirTemperature` | °C | Fortlufttemperatur | FC04 / 4x0197, signed / 100 | Statuswert |
| `FreshAirTemperature` | °C | Außen-/Frischlufttemperatur | FC04 / 4x0198, signed / 100 | Statuswert |
| `BypassStatus` | Text | Bypass offen/geschlossen | FC04 / 4x0222 | 0 = geschlossen, 1 = offen; andere Werte werden als unbekannt ausgegeben |
| `DamperPosition` | Text | Stellung des Luftschiebers zwischen fünf beschrifteten Positionen | FC04 / 4x0159 | Stufenbeschriftung vorhanden |
| `DeviceFilterRuntime` | h | Betriebsstunden Gerätefilter | FC03 / 4x0469 | Statuswert |
| `RecirculationFilterRuntime` | h | Betriebsstunden Umluftfilter | FC03 / 4x0468 | Statuswert |
| `ConnectedPanels` | Text | Vom Registerbitfeld erkannte Bedienpanels | FC03 / 4x0210–0211 | Eher Backend; projektabhängig und statisch |
| `ConnectedPanelCount` | Anzahl | Anzahl erkannter Bedienpanels | FC03 / 4x0210–0211 | Eher Backend; projektabhängig und statisch |

Die schaltbaren Sollwerte sind bewusst von den gemeldeten Istwerten getrennt. `MaximumCompressorPower` und weitere Konfigurations-/Optimierungswerte gehören nicht in die Standard-Anlagenkachel.

### Optionale Raum-/Panelwerte

Für eine ergänzende Raumansicht stellt System Control bis zu fünf Sensorgruppen bereit. `N` steht für 1 bis 5; angelegt und abgefragt werden nur die in der Instanz konfigurierte Anzahl.

| Ident-Muster | Einheit / Typ | Bedeutung | Register pro Sensor 1–5 |
|---|---|---|---|
| `CO2SensorNRoom` | Raum-Auswahl | Ordnet den Sensor einem Zentral- oder Nebenpanel-Raum zu | FC04: 4x0394, 0395, 0396, 0397, 0466 |
| `CO2SensorNValue` | ppm | CO₂-Messwert des Sensors | FC04: 4x0021, 0043, 0045, 0006, 0002 |
| `CO2SensorNRelativeHumidity` | % | Relative Luftfeuchtigkeit des Sensors | FC04: 4x0022, 0044, 0046, 0007, 0009 |

Diese Werte sind optionale Raumkacheldaten und erscheinen nicht zwingend in einer Anlagen-Prozesskachel.

## Monitoring: Register- und Variablenkatalog

| Ident | Einheit / Typ | Register | Bedeutung | Klärungsstand |
|---|---|---|---|---|
| `ElectricalPowerPCB` | W | FC04 / 4x0019, / 10 | Elektrische Leistung der Platine laut Registerliste | Bezeichnung und Skalierung geklärt |
| `ElectricalPowerInverter` | W | FC04 / 4x0020, / 10 | Elektrische Leistung des Umrichters laut Registerliste | Bezeichnung und Skalierung geklärt |
| `TotalElectricalPower` | W | FC04 / 4x0025, / 10 | Elektrische Gesamtleistung laut FWT | Bezeichnung und Skalierung geklärt |
| `Airflow` | m³/h | FC04 / 4x0026, / 10 | Luftvolumenstrom | **Ungeklärt:** Messort und genauer Bezug der Größe sind nicht näher beschrieben |
| `CompressorCOPJAZ1Minute` | Verhältniszahl | FC04 / 4x0027, / 100 | Kompressor-COP/JAZ, 1 Minute | Reglerwert; Berechnung/Begriff COP vs. JAZ nicht unabhängig verifiziert |
| `CompressorCOPJAZ1Hour` | Verhältniszahl | FC04 / 4x0028, / 100 | Kompressor-COP/JAZ, 1 Stunde | Wie oben |
| `CompressorCOPJAZ24Hours` | Verhältniszahl | FC04 / 4x0029, / 100 | Kompressor-COP/JAZ, 24 Stunden | Wie oben |
| `CompressorCOPJAZ365Days` | Verhältniszahl | FC04 / 4x0030, / 100 | Kompressor-COP/JAZ, 365 Tage | Wie oben |
| `CompressorCOPJAZDays` | d | FC04 / 4x0031 | Zeitraum/Alter des Kompressor-COP/JAZ-Werts laut Registerbezeichnung | Genaue Zählweise ungeklärt |
| `TotalCOPJAZ1Minute` | Verhältniszahl | FC04 / 4x0032, / 100 | Gesamt-COP/JAZ, 1 Minute | Reglerwert; Berechnung/Begriff nicht unabhängig verifiziert |
| `TotalCOPJAZ1Hour` | Verhältniszahl | FC04 / 4x0033, / 100 | Gesamt-COP/JAZ, 1 Stunde | Wie oben |
| `TotalCOPJAZ24Hours` | Verhältniszahl | FC04 / 4x0034, / 100 | Gesamt-COP/JAZ, 24 Stunden | Wie oben |
| `EnergySinceFirstSample` | kWh | lokal berechnet | Integration der Gesamtleistung seit dem ersten gültigen Messpunkt | Schätzwert; kein Zählerstand und keine Historie vor dem ersten Messpunkt |
| `FaultPresent` | Boolean | abgeleitet | Mindestens ein gelesener Fehlerindikator ist ungleich null | Bei unvollständigem Poll nur Aussage über gelesene Register; `FaultStatusComplete` mit auswerten |
| `FaultSummary` | Text | abgeleitet | Lesbare Sammelmeldung: Anlagenstörung, schwerer Umrichterfehler und/oder interner Fehlerindikator | Keine Dekodierung von Fehlercodes oder einzelnen Bits |
| `FaultStatusComplete` | Boolean | abgeleitet | Alle zehn Fehlerindikatoren sind beim letzten Poll erfolgreich gelesen | `false` bedeutet, dass die Störungsübersicht unvollständig ist |
| `FaultDetails` | Text, Backend | abgeleitet | Registeradressen, letzte gelesene Rohwerte und nicht gelesene Register | In Visualisierungen verborgen; Codes/Bitfelder werden nicht übersetzt |
| `FaultRegister` | Rohwert | FC04 / 4x0047 | Zentrales Störungsregister | Registerfunktion bekannt; Fehlercodes nicht aufgeschlüsselt; Backend |
| `FaultStatus1` | Rohwert | FC04 / 4x0048 | ErrorStatus 1 | **Ungeklärt:** Bit-/Codebedeutungen fehlen; Backend |
| `FaultStatus2` | Rohwert | FC04 / 4x0049 | ErrorStatus 2 | **Ungeklärt:** Bit-/Codebedeutungen fehlen; Backend |
| `FaultStatus3` | Rohwert | FC04 / 4x0050 | ErrorStatus 3 | **Ungeklärt:** Bit-/Codebedeutungen fehlen; Backend |
| `FaultStatus4` | Rohwert | FC04 / 4x0051 | ErrorStatus 4 | **Ungeklärt:** Bit-/Codebedeutungen fehlen; Backend |
| `FanStatus` | Rohwert | FC04 / 4x0152 | Status Ventilatoren | **Ungeklärt:** Statuswerte nicht decodiert |
| `FanErrorRemainingTime` | s | FC04 / 4x0153 | Ventilatorfehler-Restzeit laut Registerbezeichnung | Exakte Zustands- und Ablaufsemantik ungeklärt |
| `ActualSupplyFanStage` | Stufe | FC04 / 4x0154 | Tatsächliche Zuluftstufe | Bezeichnung geklärt |
| `SupplyFanVoltage` | V | FC04 / 4x0155, / 100 | Spannung Zuluftventilator | Bezeichnung und Skalierung geklärt |
| `ExtractFanVoltage` | V | FC04 / 4x0156, / 100 | Spannung Abluftventilator | Bezeichnung und Skalierung geklärt |
| `SecondaryBoardFaultFlags` | Rohwert/Bitfeld | FC04 / 4x0157 | Fehlerflags Nebenplatine | **Ungeklärt:** einzelne Bits nicht dokumentiert; Backend |
| `CompressorStatus` | Rohwert | FC04 / 4x0162 | Kompressorstatus | **Ungeklärt:** Statuscodes nicht decodiert |
| `InverterErrorCode` | Rohwert | FC04 / 4x0170 | Umrichter-Fehlercode 1 | **Ungeklärt:** Code-zu-Text-Tabelle fehlt; Backend |
| `CurrentCompressorPower` | % | FC04 / 4x0171, / 100 | Aktuelle Kompressorleistung laut Regler | Bezeichnung und Skalierung geklärt |
| `InverterErrorCode2` | Rohwert | FC04 / 4x0172 | Umrichter-Fehlercode 2 | **Ungeklärt:** Code-zu-Text-Tabelle fehlt; Backend |
| `InverterFatalError` | Rohwert | FC04 / 4x0174 | Fatal-/FOF-Fehlerindikator Umrichter | Nichtnull wird als schwerer Fehlerindikator gemeldet; genaue Codes ungeklärt; Backend |
| `InverterErrorInput` | AUS/AN-Rohwert | FC04 / 4x0184 | Umrichter-Fehlereingang laut Registerliste | AUS/AN-Angabe vorhanden; Signalherkunft/Polarität nicht weiter erläutert; Backend |
| `InverterResetCounterTime` | s | FC04 / 4x0185 | Umrichter-Resetzählerzeit laut Bezeichnung | **Ungeklärt:** Zeitfenster und Rücksetzverhalten |
| `InverterErrorDelay` | s | FC04 / 4x0186 | Umrichter-Fehlerverzögerung laut Bezeichnung | **Ungeklärt:** genaue Verzögerungslogik |
| `InverterErrorCounter` | Anzahl | FC04 / 4x0187 | Umrichter-Fehlerzähler | **Ungeklärt:** Zählumfang und Rücksetzbedingung |
| `CompressorSpeed` | derzeit rpm | FC04 / 4x0190 | Kompressordrehzahl laut Modulname | **Ungeklärt:** Einheit und Messbezug sind in der Registerliste nicht bestätigt; rpm ist vorläufig |
| `MaximumCompressorPower` | % | FC04 / 4x0201, / 100 | Maximalleistung Kompressor, lesender Monitoringwert | Registereinheit laut Modul; Kachel nicht empfohlen, Konfiguration/Optimierung getrennt halten |
| `InverterModbusErrorCounter` | Anzahl | FC04 / 4x0213 | Umrichter-Modbus-Fehlerzähler | **Ungeklärt:** Zähl- und Rücksetzzeitraum; Backend |
| `EvaporatorTemperatureFromPressure` | °C | FC04 / 4x0216, signed / 100 | Verdampfertemperatur aus Druck | Bezeichnung und Skalierung geklärt |
| `InverterPowerModuleTemperature` | °C | FC04 / 4x0233 | Temperatur Umrichter-Leistungsmodul | Registereinheit laut Liste |
| `PacketErrorCounter` | Anzahl | FC04 / 4x0237 | Paketfehlerzähler | **Ungeklärt:** Zähl- und Rücksetzzeitraum; Backend |
| `InverterModbusRestartCounter` | Anzahl | FC04 / 4x0267 | Neustartzähler Umrichter-Modbus | **Ungeklärt:** Zähl- und Rücksetzzeitraum; Backend |
| `InverterOneSecondErrorCounter` | Anzahl | FC04 / 4x0268 | Fehlerzähler 1 Sekunde | **Ungeklärt:** genaue Zähl- und Rücksetzlogik; Backend |
| `PlantRuntime` | h | FC03 / 4x0467 | Anlagenlaufzeit | Registerbezeichnung geklärt |
| `RecirculationFilterRuntime` | h | FC03 / 4x0468 | Laufzeit Umluftfilter | Registerbezeichnung geklärt |
| `DeviceFilterRuntime` | h | FC03 / 4x0469 | Laufzeit Gerätefilter | Registerbezeichnung geklärt |
| `PreheaterRuntime` | h | FC03 / 4x0470 | Laufzeit Vorheizer | Registerbezeichnung geklärt |
| `RecirculationRuntime` | h | FC03 / 4x0471 | Laufzeit Umluft | Registerbezeichnung geklärt |
| `InverterRuntime` | h | FC03 / 4x0472 | Laufzeit Umrichter | Registerbezeichnung geklärt |
| `CompressorCoolingRuntime` | h | FC03 / 4x0473 | Kompressorlaufzeit Kühlen | Registerbezeichnung geklärt |
| `CompressorHeatingRuntime` | h | FC03 / 4x0474 | Kompressorlaufzeit Heizen | Registerbezeichnung geklärt |
| `DefrostRuntime` | h | FC03 / 4x0475 | Abtaulaufzeit | Registerbezeichnung geklärt |
| `FanRuntimeStage1` | h | FC03 / 4x0476 | Ventilatorlaufzeit Stufe 1 | Registerbezeichnung geklärt |
| `FanRuntimeStage2` | h | FC03 / 4x0477 | Ventilatorlaufzeit Stufe 2 | Registerbezeichnung geklärt |
| `FanRuntimeStage3` | h | FC03 / 4x0478 | Ventilatorlaufzeit Stufe 3 | Registerbezeichnung geklärt |
| `FanRuntimeStage4` | h | FC03 / 4x0479 | Ventilatorlaufzeit Stufe 4 | Registerbezeichnung geklärt |
| `LastSuccessfulRead` | Zeitstempel | lokal | Zeitpunkt der letzten Abfrage mit mindestens einem erfolgreich gelesenen Register | Keine FWT-Registeradresse |

Die Monitoring-Instanz liest FC04 / 4x0047–0051, 0157, 0170, 0172, 0174 und 0184 für die Störungsübersicht. Bekannte Nichtnullwerte erzeugen generische Meldungen; fehlende Werte führen zu `FaultStatusComplete = false`. Die rohen Werte bleiben für Diagnosezwecke im Backend verfügbar und werden nicht als Klartext-Fehlercode ausgegeben.

## Offene Register- und Bedeutungsfragen

- 4x0026: Welcher Luftvolumenstrom und welcher Messquerschnitt sind gemeint?
- 4x0048–0051 und 4x0157: Welche Bits entsprechen welchen Fehlern?
- 4x0152 und 4x0162: Welche Statuswerte bedeuten welche Ventilator-/Kompressorzustände?
- 4x0170, 4x0172 und 4x0174: Welche Fehlercodes und FOF-Werte gibt es?
- 4x0184: Was genau ist die Quelle und Polarität des AUS/AN-Fehlereingangs?
- 4x0185–0187, 4x0213, 4x0237, 4x0267–0268: Welche Zeitfenster, Zählbedingungen und Rücksetzregeln gelten?
- 4x0190: Welche Einheit und welcher Drehzahl-Messbezug gelten?
- 4x0241: Die Registerliste nennt hier den Wärmepumpenmodus; das Modul liest dafür aktuell FC03 / 4x0069. Die richtige Quelle muss geklärt werden.
- Vierwegeventil: Im aktuellen Modul ist noch kein verifiziertes Register mit belastbarer Zustandsdekodierung vorhanden.
- 4x0035/0036 (Gesamt-JAZ 365 Tage und Alter): In der Registerliste als nicht implementiert markiert und daher nicht im Modul verfügbar.
