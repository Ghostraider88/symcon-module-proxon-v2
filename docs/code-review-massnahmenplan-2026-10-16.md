# Proxon Maßnahmenplan nach dem Code Review

**Status:** Lokale Umsetzung abgeschlossen; isolierte Prüfungen bestanden. Der Symcon-TEST-Nachweis steht vor einer Auslieferung noch aus.

**Planungsdatum:** 16. Oktober 2026

**Bezugsstand:** main, Commit 2024c62

**Grundlage:** Gesamtes Code Review einschließlich lesender Symcon-Prüfung vom 16. Oktober 2026

**Umsetzungsauftrag:** Am 16. Oktober 2026 erteilt. Die Arbeitspakete werden in der dokumentierten Reihenfolge umgesetzt.

## Ziel

Die Proxon-Bibliothek soll gültige Bedienbefehle zuverlässig ausführen, ungültige oder widersprüchliche Schreibwerte vor dem Modbus-Zugriff zurückweisen und den tatsächlichen Zustand der Anlage verständlich darstellen. Ein Kommunikationsfehler darf weder als erfolgreicher Schaltvorgang noch als aktueller Messwert erscheinen.

Die bestehenden Module für FWT und T300, die Trennung von Standard, Advanced und Monitoring sowie die Raumsteuerung bleiben die fachliche Grundlage. Die Maßnahmen sichern diese Funktionen ab und beseitigen die im Review gefundenen Fehler.

Das Ziel ist erreicht, wenn:

1. Ungültige Zahlen, Auswahlen, Schalterwerte und FWT-2/3-Room-Panel-IDs keine Schreibtelegramme erzeugen.
2. Bestätigte Beziehungen zwischen Advanced-Grenzen vor dem Schreiben eingehalten werden.
3. Die beiden FWT-2/3-Raummodule (Central Panel und Room Panel) eine kompatible exportierte Temperaturschnittstelle und die vorgesehenen ganzen Gradschritte besitzen.
4. Eine geöffnete Kachel neue Anlagen- und Raumwerte ohne Neuladen erhält.
5. Bestätigte Betriebszustände einschließlich Aus Vorrang vor Schätzungen haben.
6. Aktuelle, veraltete, unbekannte und fehlgeschlagene Messungen unterscheidbar sind.
7. Polling und manuelle Aktualisierung unter der abgestimmten Last zuverlässig arbeiten.
8. Gewöhnliche Konfigurationsänderungen bestehende Variablen-IDs und deren Historienbezüge erhalten.
9. Die Prüfungen reproduzierbar laufen und der abschließende TEST-Nachweis keine neuen Fehler aus den behobenen Ursachen zeigt.

## Umfang und Grenzen

Die Umsetzung umfasst acht Module außerhalb des FWT-1-Legacy-Raummoduls, die gemeinsame Raum-Bibliothek, die für die Datenübertragung notwendigen Teile der aktiven HTML-Kachel, Formulare, Prüfskripte und zugehörige Dokumentation. FWT 1 ist laut Nutzerklärung vom 16. Oktober 2026 nicht Teil dieses Arbeitsumfangs; R09, R10 und AP07 bleiben für eine spätere gesonderte Bearbeitung vorgemerkt.

Die Bearbeitung erfolgt im GIT-Checkout. Modul-GUIDs, Datenfluss-GUIDs, Präfixe, bestehende Variablen-Idents und Variablentypen sollen erhalten bleiben. Notwendige Abweichungen sind vor ihrer Umsetzung als eigene Migration zu planen.

Die manuell angelegten produktiven Modbus-Geräte, zugehörige Skripte, Archive und Anzeigen werden erst bei einem gesondert freigegebenen Cutover umgestellt. Gateway-Einstellungen werden anhand von Messungen bewertet und nicht automatisch durch das Modul verändert.

COP und JAZ bleiben aus der Entwicklung entfernt. Die Maßnahmen führen keine neuen Komfortfunktionen, keine zusätzliche T300-Funktionserweiterung und keine neue grafische Gestaltung ein. Die vorhandenen Visualisierungsentwürfe bleiben ein eigener Themenbereich.

## Ausgangslage und Nachweise

Im Review wurden alle neun Module einschließlich gemeinsamer PHP-Bibliothek, Formularen, Metadaten und aktiver Visualisierungslogik betrachtet. 41 geprüfte Code- und Metadatendateien in Symcon stimmten mit dem Git-Bezugsstand überein.

Die Bibliotheksvalidierung war erfolgreich. Die zehn produktiven PHP-Dateien kompilierten ohne Syntaxfehler. Das eigenständige Raum-Prüfskript definiert eigene SDK-Funktionen und ist deshalb außerhalb des Symcon-Kernels auszuführen. Zusätzlich enthält es überholte Pfad- und Klassenreferenzen.

Die Logsuche im Sieben-Tage-Fenster bis zur Abfrage am 16. Oktober gegen 09:28 Uhr ergab 369 Proxon-bezogene Treffer, davon 346 Fehler und 23 Warnungen. Die Treffer umfassen auch frühere Entwicklungs- und Testläufe. Sie bilden keinen Zähler ausschließlich aktueller Produktfehler.

Aktuell bestätigt waren wiederkehrende Konvertierungsfehler bei der Übertragung von Kacheldaten sowie Monitoring-Aufrufe mit 64 beziehungsweise 81 Sekunden Laufzeit. Eingestellte Pollintervalle von 30 beziehungsweise 60 Sekunden führten in einer Stichprobe zu einzelnen mehrere Minuten alten Werten; später waren diese wieder aktuell. Welche Register oder Warteschlangen die Verzögerung verursachen, ist noch zu ermitteln.

Simulationen mit Ersatzobjekten bestätigten problematische FWT-Eingaben, widersprüchlich akzeptierte Lüftergrenzen, die Übersteuerung eines gemeldeten Aus-Zustands durch eine Schätzung sowie mehrere FWT-1-Lese- und Schreibfehler. Dabei wurden keine Modbus-Schreibbefehle an die Anlage gesendet.

Private Instanz-IDs, Netzwerkadressen, Installationspfade und Hausmesswerte gehören nicht in diese Repository-Dokumentation.

## Zuordnung der Reviewpunkte

| Referenz | Priorität | Befund oder Konfigurationsrisiko | Arbeitspaket |
| --- | --- | --- | --- |
| R01 | P1 | Arrays an UpdateVisualizationValue verursachen aktuelle Logfehler | AP04 |
| R02 | P1 | Unzureichende FWT-Eingabeprüfung vor Schreibzugriffen | AP01 |
| R03 | P1 | Ungültige Panel-IDs können andere Panelregister adressieren | AP02 |
| R04 | P1 | Zusammengehörige Advanced-Grenzen können widersprüchlich gesetzt werden | AP03 |
| R05 | P2 | Lange Abfragen und zusätzliche parallele Aktualisierungsanforderungen | AP05 |
| R06 | P2 | Widersprüchliche Signaturen von PROXON_SetTemperature | AP01 |
| R07 | P2 | Gemeinsame Zeitstempel verdecken veraltete Einzelwerte | AP06 |
| R08 | P2 | Gemeldetes Aus kann in der Kachel durch Heizen oder Kühlen ersetzt werden | AP04 |
| R09 | P2 | FWT 1 verarbeitet Nullwerte und fehlgeschlagene Basiswert-Lesungen falsch | AP07 |
| R10 | P2 | FWT 1 bestätigt Schreibfehler lokal und verändert Bitfelder ohne gemeinsame Sperre | AP07 |
| R11 | P2 | Prüflauf verweist auf alten Modulpfad und alte Klasse; automatisierte Prüfungen fehlen | AP00 und AP09 |
| R12 | P3 | Advanced-Instanzauswahl und Referenzpflege sind unvollständig | AP08 |
| R13 | Konfigurationsrisiko | Reduzieren der Sensoranzahl löscht vorhandene Sensorvariablen | AP08 |
| R14 | P3 | Registerkatalog, T300-Beschreibung und einzelne Modulnamen sind inkonsistent | AP09 |

P1 bezeichnet zuerst zu behebende Funktions- und Schreibprobleme. P2 betrifft Zuverlässigkeit und Datenverarbeitung. P3 betrifft Konfiguration und Dokumentation. Eine fehlende Prüfung im Modul ist bestätigt; daraus folgt noch keine Aussage darüber, ob eine bestimmte Anlagenfirmware einen problematischen Schreibwert selbst akzeptiert oder ablehnt.

## Reihenfolge und Abhängigkeiten

| Phase | Reihenfolge | Ergebnis |
| --- | --- | --- |
| Prüfgrundlage | AP00 | Reproduzierbare, isolierte Nachweise für die folgenden Änderungen |
| Schreibzugriffe | AP01, AP02, AP03 | Verlässliche Eingaben, Paneladressierung und zusammengehörige Grenzen |
| Aktuelle Livefehler | AP04 | Funktionierender Kachelkanal und korrekte Betriebszustandsanzeige |
| Polling und Datenqualität | AP05, danach AP06 | Beherrschbare Abfragen und erkennbare Gültigkeit der Werte |
| Konfiguration | AP08 | Gültige Advanced-Verknüpfung und Erhalt bestehender Sensorobjekte |
| Abschluss | AP09 | Konsistente Dokumentation, automatisierte Prüfungen und Release-Nachweis |

AP00 ist die technische Prüfgrundlage für alle weiteren Pakete. AP03 verwendet die Eingabeprüfungen aus AP01 und benötigt einen nachweislich aktuellen Gegenwert. AP06 baut auf dem Kachelkanal aus AP04 und dem Polling-Verhalten aus AP05 auf. AP08 berücksichtigt die Gültigkeits- und Referenzbehandlung der vorherigen Pakete. AP07 ist gemäß Nutzerklärung aus dem aktuellen Umfang herausgenommen.

Jedes Arbeitspaket wird als einzeln abnehmbarer Abschnitt bearbeitet. Bei größerem Umfang wird es entlang seiner Nutzerabläufe weiter geteilt; seine Abschlusskriterien bleiben vollständig erhalten.

## Arbeitspakete

### AP00 Prüfgrundlage und Nachweise

**Status:** Abgeschlossen am 16. Oktober 2026

**Bezug:** R11

**Ziel:** Die vorhandenen Prüfungen können Fehler der produktiven Schnittstellen tatsächlich erkennen.

**Geplante Arbeiten**

- Das eigenständige Prüfsystem unter .tools/check-room-mapping.php auf den aktuellen Modulpfad und die aktuelle Klasse umstellen.
- Die Ersatzobjekte am tatsächlichen SDK-Verhalten ausrichten. Insbesondere darf eine Attrappe eine nicht unterstützte Übertragung eines Arrays nicht als erfolgreiche Kachelaktualisierung bestätigen.
- Prüfungen im eigenständigen PHP-Prozess von Prüfungen innerhalb des Symcon-Kernels trennen. Eigenständig definierte IPS-Funktionen werden nicht im laufenden Kernel erneut deklariert.
- Die späteren Fehlerfälle zunächst als gezielte Regressionstests abbilden.
- Die Prüfung der kleinsten unterstützten Symcon-Version und die Prüfung der installierten Version getrennt nachvollziehbar machen.

**Betroffene Bereiche:** .tools/check-room-mapping.php, benötigte Testhilfen in .tools oder im reservierten Ordner tests.

**Nachweis:** Modulpfad und Klassenname im Raum-Prüflauf sind korrigiert. Der Testadapter weist nichtskalare Werte bei `UpdateVisualizationValue` zurück. `.tools/check-room-mapping.php` und `.tools/check-control-input.php` liefen in einem eigenständigen PHP-CLI-Prozess (PHP 8.5.11) erfolgreich; alle geänderten PHP-Dateien bestanden `php -l`. Die Testadapter verwenden keine Anlagen- oder Modbuszugriffe.

**Abnahme**

- Der bestehende Raum-Prüflauf endet außerhalb des Kernels vollständig.
- Überholte Pfad- und Klassenreferenzen sind beseitigt.
- Der Übertragungsfehler aus R01 wird von einer passenden Prüfung erkannt.
- Testhilfen sind keine produktiven Module und senden keine Gerätebefehle.

### AP01 FWT Eingaben und öffentliche Temperaturschnittstelle

**Status:** Abgeschlossen am 16. Oktober 2026

**Bezug:** R02 und R06

**Ziel:** Aus einem Bedienwert entsteht nur nach eindeutiger Prüfung ein passender Registerwert.

**Geplante Arbeiten**

- RequestAction-Eingaben vor verlustbehafteten Typumwandlungen validieren.
- Numerische Werte auf erlaubten Typ, Endlichkeit, Wertebereich und Registerauflösung prüfen.
- NAN, INF und -INF abweisen. Auswahlen und ganzzahlige Zähler akzeptieren ausschließlich zulässige ganze Werte.
- Für Schalter die erlaubten Eingaben ausdrücklich festlegen, angelehnt an die vorhandene T300-Prüfung mit booleschen Werten beziehungsweise Integer 0 und 1. Zeichenfolgen wie false werden nicht als Einschalten interpretiert.
- PROXON_SetTemperature für Central Panel und Room Panel mit gleicher öffentlicher Parametersignatur bereitstellen. Die vorhandenen Funktionsnamen bleiben erhalten.
- Die Verarbeitung von Bruchteilen eines Grades einheitlich festlegen und dokumentieren. Die bestehende Rundung des Zentralpanels ist dabei der Ausgangspunkt; stilles Abschneiden im Room Panel wird beseitigt.
- Den lokal bestätigten Zahlenwert aus dem tatsächlich kodierten Registerwert ableiten.
- Öffentliche Schreibmethoden zusätzlich auf ihre fachlichen Grenzen prüfen; die Formulargrenze allein genügt nicht.

**Betroffene Bereiche:** Schreibwege in FWT System Control, Advanced, Central Panel und Room Panel; gemeinsame Hilfsfunktionen nur soweit für diese Prüfung erforderlich.

**Nachweis:** Gemeinsame Eingabeprüfung weist NAN, INF, -INF, falsche Schaltervarianten, ungültige Auswahlwerte und gebrochene Ganzzahlwerte zurück. Ganzzahlige Registergrenzen bleiben gültig; der lokal bestätigte Floatwert entspricht der tatsächlich kodierten Auflösung. Die beiden `SetTemperature`-Methoden in Central Panel und Room Panel haben dieselbe `mixed`-Signatur und prüfen den Wert ohne vorgängige verlustbehaftete Umwandlung. Temperaturwerte werden gemeinsam auf ganze Grad gerundet. `.tools/check-control-input.php` und PHP-Syntaxlauf bestanden. FWT 1 wurde auf den Bezugsstand zurückgesetzt und bleibt außerhalb dieser Umsetzung.

**Abnahme**

- NAN erzeugt keinen Schreibversuch für eine Kompressorgrenze.
- Eine ungültige Betriebsart wird abgelehnt und nicht in Aus umgewandelt.
- Ein ungültiger Schalterwert aktiviert keine Funktion.
- Erlaubte Werte einschließlich der Grenzwerte werden passend kodiert.
- Beide Temperaturmethoden lassen sich ohne Signaturkonflikt registrieren.
- Bestehende Variablen-IDs und Variablentypen bleiben erhalten.

### AP02 Paneladressierung und Raumkonfiguration

**Status:** Abgeschlossen am 16. Oktober 2026

**Bezug:** R03

**Ziel:** Jede Rauminstanz kommuniziert ausschließlich mit dem gültig ausgewählten Panel.

**Geplante Arbeiten**

- ControlPanel im FWT Room Panel auf 1 bis 20 begrenzen; ID 20 bezeichnet weiterhin das HNBP.
- Ungültige Konfigurationen im Formular und in ApplyChanges verständlich melden. Polling und Schreibaktionen bleiben in diesem Zustand gesperrt.
- Jeden öffentlichen Schreibweg vor der Adressberechnung absichern.
- Bei einem Wechsel der Panelkonfiguration gespeicherte Raum-Basiswerte und deren Gültigkeit verwerfen, bis die neue Quelle erfolgreich gelesen wurde.
- Die gültige Modulo-Zuordnung für die unterstützten FWT-2/3-Panels beibehalten.

**Betroffene Bereiche:** FWT Room Panel, zugehörige Formulare und Statusmeldungen.

**Nachweis:** FWT Room Panel begrenzt die Panel-ID im Formular und Backend auf 1–20, stoppt bei ungültiger Zuordnung Polling und Schaltungen und verwirft bei einem Panelwechsel den zwischengespeicherten Basiswert. Der isolierte Prüflauf deckt ID 0, 21, negative und gebrochene Werte, gültige Paneladressen sowie den Wechsel der Konfiguration ab. FWT 1 ist auf Nutzerwunsch nicht Teil dieser Umsetzung.

**Abnahme**

- IDs 0, 21 und negative IDs lösen im FWT Room Panel keine Lese- oder Schreibtelegramme aus.
- ID 20 und die gültigen NBP-IDs erreichen weiterhin ihre vorgesehenen Register.
- Eine neu zugewiesene Rauminstanz nutzt keinen Basiswert des zuvor ausgewählten Panels.

### AP03 Zusammengehörige Advanced Grenzen

**Status:** Abgeschlossen am 16. Oktober 2026

**Bezug:** R04

**Ziel:** Ein einzelner Advanced-Schreibbefehl erzeugt keine bestätigbar widersprüchliche Parameterkombination.

**Geplante Arbeiten**

- Die Beziehung FanMinimumAirflow kleiner oder gleich FanMaximumAirflow absichern.
- Weitere Beziehungen zwischen Stufenwerten und Schaltschwellen aus den bestätigten Herstellerunterlagen ableiten. Fachlich ungeklärte Beziehungen werden nicht als neue Regeln erfunden.
- Für die Prüfung einen aktuellen, erfolgreich gelesenen Gegenwert verwenden. Ein alter Pollcache darf keine aktuelle Prüfung vortäuschen.
- Bei fehlendem Gegenwert den Schreibvorgang kontrolliert zurückweisen.
- Ausschließlich den angeforderten Parameter schreiben. Andere Anlagenwerte werden nicht automatisch passend verstellt.
- Bei einer erforderlichen Reihenfolge mehrerer Bedienänderungen eine verständliche Erklärung liefern.
- Die bestehende Advanced-Freigabe und die Zuordnung zur richtigen System Control unmittelbar vor dem Schreiben erneut prüfen.

**Betroffene Bereiche:** Definitionen und WriteControl im Advanced-Modul.

**Nachweis:** FanMinimumAirflow und FanMaximumAirflow lesen den jeweiligen Gegenwert vor dem Schreiben frisch aus dem Register, umgehen einen zwischengespeicherten Pollwert und weisen einen fehlenden, ungültigen oder widersprüchlichen Gegenwert zurück. Die unabhängige Regression prüft Grenzwerte, fehlende Antwort und einen absichtlich veralteten Cache. Sie lief erfolgreich ohne Modbuszugriff.

**Abnahme**

- Minimum 100 Prozent und Maximum 0 Prozent werden nicht als gültige Kombination akzeptiert.
- Eine fehlende aktuelle Gegenwert-Lesung erzeugt keinen Schreibbefehl.
- Zulässige Änderungen funktionieren in nachvollziehbarer Reihenfolge.
- Nach Entzug der Advanced-Freigabe bleibt jeder Schreibweg gesperrt.

### AP04 Kachelübertragung und Betriebszustände

**Status:** Lokal abgeschlossen; offene Kachelaktualisierung und gezielte Logprüfung auf Symcon TEST noch ausstehend

**Bezug:** R01 und R08

**Ziel:** Die geöffnete Kachel erhält Aktualisierungen und respektiert bestätigte Anlagenzustände.

**Geplante Arbeiten**

- Einen gemeinsamen Versandweg für Kacheldaten festlegen, der einen vom SDK unterstützten Datentyp überträgt. Vorgesehen ist ein JSON-String.
- Beide UpdateVisualizationValue-Aufrufstellen über diesen Versandweg führen.
- handleMessage für die vereinbarte Dekodierung anpassen. Initiale Objektdaten und spätere Nachrichten werden konsistent verarbeitet.
- Fehlerhafte Nachrichten kontrolliert behandeln.
- Bestätigte Wärmepumpenmodi einschließlich Code 0 für Aus vorrangig anzeigen.
- Die bisherige Schätzung nur für tatsächlich unbekannte Modi zulassen und weiterhin als Schätzung kennzeichnen.
- Die Anpassung auf die Datenübertragung und Zustandsdarstellung begrenzen. Neue Layouts und SVG-Gestaltung bleiben gesonderte Aufgaben.

**Betroffene Bereiche:** FWT System Control/module.php, libs/FWTVisualizationRooms.php, FWT System Control/module.html und die passenden Prüfungen.

**Nachweis:** Anlagen- und Raumupdates senden JSON-Strings mit sicherer JSON-Kodierung; der Empfänger verarbeitet auch weiterhin das initiale Datenobjekt und ignoriert ungültiges JSON. Die JavaScript-Prüfung bestätigt einen unbekannten Modus mit Kennzeichnung als Schätzung und einen gemeldeten Modus 0 als Aus trotz positiver Drehzahl und Temperaturdifferenz. Der PHP-Raum-Prüflauf bestätigt die serialisierte Raumaktualisierung. Beide Prüfungen liefen ohne Symcon-Objekt- oder Geräteänderung.

**Abnahme**

- Anlagenpoll und ausgewählte Raumwertänderung aktualisieren eine bereits geöffnete Kachel.
- Eine fehlerhafte Nachricht beendet die Verarbeitung nicht unkontrolliert.
- Ein gemeldetes Aus bleibt auch bei vorhandener Drehzahl Aus.
- Für die behobenen Aufrufstellen entstehen im gezielten TEST-Lauf keine neuen Konvertierungsfehler.
- Namen und andere Texte bleiben beim Einbetten und Anzeigen sicher behandelt.

### AP05 Polling und Transport

**Status:** Implementierung und isolierte Prüfungen abgeschlossen; Live-Laufzeitnachweis steht vor TEST-Auslieferung aus

**Bezug:** R05 und die Transportabhängigkeiten der übrigen Pakete

**Ziel:** Abfragen haben nachvollziehbare Laufzeiten und zusätzliche Bedienanforderungen vergrößern keine Warteschlange.

**Geplante Arbeiten**

- Zunächst Laufzeiten und erfolgreiche beziehungsweise fehlgeschlagene Lesegruppen messen, ohne produktive Gateway-Einstellungen zu verändern.
- Dynamische Messwerte, Einstellungen und weitgehend statische Informationen nach ihrem erforderlichen Aktualisierungsbedarf behandeln.
- Bestätigte zusammenhängende Register gezielt als Blöcke lesen. Unbekannte oder nicht unterstützte Adresslücken werden nicht blind mitgelesen.
- Zusätzliche Pollanforderungen während eines laufenden Durchlaufs begrenzen oder zusammenfassen. Die Sperre wird auch nach Fehlern zuverlässig freigegeben.
- Bereitschaft des Kernels, aktive Parent-Verbindung und Antwortformat vor der Weiterverarbeitung prüfen.
- Fehlgeschlagene Antworten nicht in Zahlenwerte oder Erfolgsmeldungen umwandeln.
- Intervall 0 deaktiviert den automatischen Timer und lässt manuelle Aktualisierung zu. Akzeptiert werden 0 oder 5–86400 Sekunden; ungültige Werte stoppen das Polling.
- Gateway-Timeout und tatsächliche Anlagenantwortzeiten getrennt bewerten. Änderungen am gemeinsam genutzten Gateway erfordern einen eigenen, konkreten Auftrag.
- Einen neuen Splitter oder eine umfassende Architekturänderung nur bei belegtem Bedarf gesondert planen.

**Betroffene Bereiche:** RequestStatus und Transporthilfen der Module, Timerkonfiguration und gegebenenfalls gemeinsame Hilfsfunktionen.

**Nachweis:** Die gemeinsame Polling-Hilfe verhindert parallele Polls derselben Instanz mit einer nicht wartenden Sperre und gibt sie auch bei Ausnahmen frei. Die sieben aktiven FWT-2/3- und T300-Module akzeptieren 0 oder 5–86400 Sekunden; 0 deaktiviert den Timer, manuelle Aktualisierung bleibt möglich, und ungültige Werte stoppen das Polling mit Status 203. Messungen je Registergruppe protokollieren Dauer, Antwortlänge und Ergebnis. FWT-System-Monitoring verwendet 16 exakte zusammenhängende Gruppen, T300 Control 4 FC3-Gruppen und T300 Monitoring 11 FC4-Gruppen; Lücken werden nicht mitgelesen. Isolierte Prüfungen bestätigen Sperrverhalten, Freigabe nach Ausnahme, kurze Antwortbehandlung, Intervallregeln und Gruppierung. In Symcon wurde noch keine reale Anlagenantwortzeit oder ein Transport-Timeout provoziert; diese Laufzeitmessung bleibt Teil des späteren TEST-Nachweises.

**Abnahme**

- Eine definierte Lastmessung dokumentiert Dauer, Intervall, erfolgreiche Gruppen und Fehlerfälle.
- Ein neuer manueller Aufruf während einer laufenden Abfrage erzeugt keinen zweiten vollständigen wartenden Durchlauf.
- Normale Durchläufe bleiben im abgestimmten Betrieb innerhalb des vorgesehenen Intervalls.
- Timeouts, kurze Antworten und Verbindungsverlust führen zu einem kontrollierten Ende.
- Nach einem Fehler ist der nächste reguläre Durchlauf wieder möglich.

### AP06 Datenalter und Gültigkeit

**Status:** Implementierung und isolierte Prüfungen abgeschlossen; Live-Kachelnachweis steht vor TEST-Auslieferung aus

**Bezug:** R07

**Ziel:** Benutzer und Automationen können einen aktuellen Messwert von einem alten oder unbekannten Stand unterscheiden.

**Geplante Arbeiten**

- Erfolgreiche Lesungen pro relevantem Wert oder klar definierter Lesegruppe mit einem Zeitstempel versehen.
- Kriterien für aktuell, veraltet, unbekannt und fehlgeschlagen anhand der vorgesehenen Aktualisierung festlegen.
- Unveränderte, erfolgreich erneut gelesene Werte weiterhin als aktuell behandeln.
- Ein einzelnes erfolgreiches Register nicht als Aktualitätsnachweis aller übrigen Werte verwenden.
- Letzte bekannte Zahlenwerte erhalten, ihre Gültigkeit jedoch separat führen. Die Kachel erhält für nicht verfügbare Werte eine passende Darstellung.
- Maschinenlesbare Zeitstempel unabhängig von der lokalen Datumsdarstellung übertragen.
- Das Ablaufdatum einer Anzeige auch dann erkennbar machen, wenn keine neue Nachricht mehr eintrifft.
- Instanzstatus und Backend-Status so setzen, dass dauerhafte Kommunikationsprobleme erkennbar werden.
- FaultStatusComplete bei der Verwendung der Störungszusammenfassung berücksichtigen. Eine fehlende Abfrage darf nicht als bestätigte Entwarnung erscheinen.
- Archiv- und Verbrauchsschätzungen weiterhin entsprechend ihrer jeweiligen Datenbasis kennzeichnen.

**Betroffene Bereiche:** FWT System Control, FWT System Monitoring, T300-Statusverarbeitung soweit erforderlich, Raum-Bibliothek und Kachelempfänger.

**Nachweis:** Jede abgefragte Registergruppe führt einen eigenen letzten Versuch, letzten Erfolg, Zeitstempel, Alter und Zustand (current, stale, unknown oder failed). Ein 30-Sekunden-Timer aktualisiert den Qualitätsstatus auch ohne neue Pollantwort; Werte veralten nach max(300 Sekunden, dreifachem konfigurierten Intervall). Fehlgeschlagene Gruppen lassen nur ihre eigenen Werte ungültig. Die Kachel prüft Alter und Qualität, aktualisiert dargestellte Zustände regelmäßig ohne Seitenneuladen und lässt bestätigtes Aus gegenüber Schätzungen vorrangig. Raumwerte führen Qualität pro Feld; neu aktivierte interne Sensorsignale bleiben bis zu einer passenden neuen Lesung unbekannt. Die isolierten Prüfungen für Polling, Kachel und Raumzuordnung bestanden. Live-Meldungsverlust und die Ablaufdarstellung wurden noch nicht in Symcon beobachtet.

**Abnahme**

- Fällt nur die Drehzahl-Lesung aus, wird eine alte Drehzahl nicht durch einen erfolgreichen Laufzeitzähler aktuell gehalten.
- Noch ungelesene Standardwerte erscheinen nicht als bestätigte Messung.
- Bei vollständigem Nachrichtenverlust altert die Anzeige sichtbar.
- Ein echter Nullwert bleibt von einem fehlenden Wert unterscheidbar.
- Eine erneute erfolgreiche Lesung stellt die gültige Anzeige wieder her.

### AP07 FWT 1 Leseverarbeitung und Schreibbestätigung

**Status:** Zurückgestellt; FWT 1 ist laut Nutzerklärung nicht Teil dieses Auftrags.

**Bezug:** R09 und R10 bleiben im Review erfasst, werden in diesem Branch aber weder umgesetzt noch getestet. Es gibt keine FWT-1-Codeänderung.

### AP08 Konfiguration Referenzen und Variablenerhalt

**Status:** Lokal abgeschlossen; tatsächliches Referenz-, Variablen-ID- und Archivverhalten auf Symcon TEST noch ausstehend

**Bezug:** R12 und R13

**Ziel:** Konfigurationswechsel hinterlassen korrekte Abhängigkeiten und erhalten bestehende Objektbezüge.

**Geplante Arbeiten**

- Die Advanced-Instanzauswahl mit validModules auf die passende System Control beschränken.
- Die ausgewählte Instanz zusätzlich im Backend anhand von Modultyp, Existenz und passender Datenflussverbindung prüfen.
- Bei Änderung der Auswahl die alte eigene Referenz entfernen und die neue registrieren.
- Beim Reduzieren der CO2/RF-Sensoranzahl die vorhandenen Sensorvariablen erhalten, inaktiv behandeln und ihre Bedienung sperren.
- Beim erneuten Aktivieren dieselben Variablen-IDs verwenden und die Gültigkeit erst nach einer passenden Lesung bestätigen.
- Sichtbarkeitsänderungen und die neue Behandlung inaktiver Sensorgruppen dokumentieren.
- Bereits früher gelöschte IDs oder Historien nicht durch automatisch erzeugte Ersatzobjekte als wiederhergestellt ausgeben.

**Betroffene Bereiche:** Advanced/form.json und module.php sowie Sensorregistrierung und Referenzverwaltung in System Control.

**Abnahme**

- Der Instanzselektor bietet passende Instanzen an; unpassende Zuordnungen werden auch bei direkter Konfiguration abgelehnt.
- Nach einem Wechsel ist nur die tatsächlich verwendete eigene System-Control-Referenz vorhanden.
- Reduzieren und erneutes Erhöhen der Sensoranzahl erhalten die bestehenden Variablen-IDs und Archivkonfigurationen.
- Inaktive Gruppen erzeugen keine Schreibaktionen und erscheinen nicht als aktuelle Messung.

### AP09 Dokumentation automatisierte Prüfungen und Release

**Status:** Lokal abgeschlossen; GitHub Actions kann nach Push laufen. Der Symcon-TEST-Nachweis folgt nach Auslieferung; der aktuelle Symcon-MCP-Dateivergleich bestätigt, dass die installierten Moduldateien noch nicht diesem GIT-Stand entsprechen.

**StylePHP-Prüfstand:** Das `.style`-Submodul fehlt im Checkout. Ein temporärer Dry-Run gegen die offizielle Symcon-Konfiguration meldete zunächst Formatierungsbefunde in 9 von 11 geprüften PHP-Dateien. `libs/FWTStatusPolling.php` wurde danach angepasst; das Ergebnis des abschließenden Gesamtlaufs wurde nicht verlässlich erfasst. Ein sauberer StylePHP-Lauf ist daher nicht nachgewiesen und bleibt vor einem Release erforderlich.

**Bezug:** R11 und R14 sowie Abschluss aller Arbeitspakete

**Ziel:** Der erreichte Funktionsstand ist konsistent dokumentiert, reproduzierbar geprüft und kontrolliert auslieferbar.

**Geplante Arbeiten**

- Registerkatalog und Code bei Funktionscodes abgleichen. Holding-Bedienwerte werden entsprechend dem bestätigten Protokoll als FC03 zum Lesen und FC06 zum Schreiben dokumentiert. Projektspezifische Registerbezeichnungen bleiben davon unterscheidbar.
- Die T300-Beschreibung der Freigaben an die implementierten Betriebs- und Wartungsfunktionen angleichen.
- Verbliebene alte Modulnamen in aktiver Dokumentation korrigieren; historische Einträge bleiben als Historie erkennbar.
- Öffentliche Methoden, Rundungsregeln, Gültigkeitsangaben, Polling und Konfigurationsmigrationen dokumentieren.
- Automatisierte Prüfungen für Bibliotheksmetadaten, PHP-Syntax, gemeinsame öffentliche Signaturen und die relevanten Regressionen einrichten.
- PHP-Style-Prüfung gegen die Symcon StylePHP-Regeln vorsehen; den Status des `.style`-Submoduls prüfen und dokumentieren.
- Entwicklungsabhängigkeiten von der produktiven Bibliothek trennen.
- Die Bibliotheks-, Metadaten-, Syntax- und Regressionsprüfungen in GitHub Actions ausführen. PHP-Style-Prüfung gegen Symcon StylePHP bleibt vor Release erforderlich.
- Den Update- und Rollback-Ablauf über Module Control einschließlich vorherigem Modulzweig, erhaltenen Instanzkonfigurationen und Grenzen bei bereits migrierten Variablen dokumentieren.
- Erst beim späteren Code-Release Build, Datum, Changelog und Module-Store-Information synchronisieren. Der reine Plan benötigt keinen neuen Modul-Build.
- Nach Freigabe einen kleinen TEST-Nachweis und eine gezielte Logprüfung ausführen. Ein vorhandener Produktions-Cutover bleibt ein gesonderter Auftrag.

**Abnahme**

- Die vereinbarten automatisierten Prüfungen sind erfolgreich.
- Dokumentation und Freigabeverhalten widersprechen sich nicht.
- Der TEST-Nachweis benennt die behobenen Ursachen und das beobachtete Ergebnis.
- Neu verursachte Fehler werden von historischen Logmeldungen und absichtlich ausgelösten Prüffehlern unterschieden.
- Rückkehr zum vorherigen Modulstand und gegebenenfalls notwendige Konfigurationsschritte sind beschrieben.

## Verbindliche Prüffälle für die spätere Umsetzung

| Prüfung | Erwartetes Ergebnis | Paket |
| --- | --- | --- |
| NAN, INF und -INF in numerischen FWT-Eingaben | Ablehnung vor dem Transport | AP01 |
| Ungültige Zeichenfolge als Betriebsart | Keine Umwandlung in Aus | AP01 |
| Ungültige Zeichenfolge als Schalter | Kein Einschalten | AP01 |
| Erlaubte Grenzwerte und Registerauflösung | Passende Kodierung und lokale Bestätigung | AP01 |
| Gemeinsame Temperatursignatur | Keine Registrierungswarnung | AP01 |
| Panel-ID 0, 21 und negative ID im Room Panel | Kein Registerzugriff | AP02 |
| Gültige NBP-ID und HNBP-ID 20 | Richtige Registerzuordnung | AP02 |
| Panelwechsel mit vorhandenem Basiswert | Alter Basiswert wird nicht weiterverwendet | AP02 |
| Minimum oberhalb des Maximums | Kein Schreibtelegramm | AP03 |
| Fehlender aktueller Gegenwert | Kontrollierte Ablehnung | AP03 |
| Advanced-Freigabe wird entzogen | Schreibzugriffe sind gesperrt | AP03 |
| Anlagen- und Raumupdate bei geöffneter Kachel | Aktualisierung ohne Neuladen | AP04 |
| Gemeldetes Aus bei vorhandener Drehzahl | Anzeige bleibt Aus | AP04 |
| Fehlerhafte Kachelnachricht | Kontrollierte Verarbeitung | AP04 |
| Wiederholte Aktualisierung während eines Polls | Keine wachsende Warteschlange | AP05 |
| Timeout, Verbindungsverlust und kurze Antwort | Kontrolliertes Ende und späterer Wiederanlauf | AP05 |
| Einzelner Registerausfall bei sonst erfolgreichen Lesungen | Nur betroffene Werte verlieren Gültigkeit | AP06 |
| Keine neuen Nachrichten | Sichtbarer Übergang zu veralteten Daten | AP06 |
| Erfolgreich erneut gelesener Nullwert | Gültiger Nullwert | AP06 |
| Wechsel der Advanced-Verknüpfung | Korrekte Referenzliste | AP08 |
| Sensoranzahl reduzieren und wieder erhöhen | Bestehende IDs und Archivkonfiguration bleiben erhalten | AP08 |

Die Tabelle ist ein Prüfplan. Keiner dieser Punkte gilt allein durch seine Aufnahme als umgesetzt oder erneut erfolgreich geprüft. AP07-Zeilen sind ausschließlich für eine künftige, gesonderte FWT-1-Beauftragung vorgesehen und blockieren die aktuelle Umsetzung nicht.

## Durchführung und Nachweisführung nach einem Umsetzungsauftrag

Pro Funktionsabschnitt wird zuerst der konkrete Nutzerablauf umgesetzt und anschließend die passende Prüfung ausgeführt. Eine vorhandene dedizierte TEST-Instanz und bei Bedarf ein kleines temporäres Prüfmodul werden wiederverwendet. Vor einer Symcon-Änderung werden die betroffenen Objekte und Eigenschaften konkret festgehalten.

Modbus-Schreibfälle für Schutzgrenzen, Neustart und Fehlerreset werden zunächst ausschließlich mit Ersatzobjekten geprüft. Ein späterer Hardware-Schreibtest benötigt einen ausdrücklich dafür bestimmten Auftrag und ein passendes Testszenario.

Nach jedem Arbeitspaket werden nur der zugehörige Implementierungsstand, der tatsächlich ausgeführte Nachweis, das Ergebnis und offene Fragen ergänzt. Lange Wiederholungsprüfungen ohne neue Änderung oder konkreten Fehler werden vermieden. Temporäre Objekte werden nur entfernt, wenn sie nachweislich für den betreffenden Prüflauf angelegt wurden.

Für die spätere Entwicklung ist ein einzelner vorübergehender Arbeitsbranch mit nachvollziehbaren Commits je Abschnitt vorgesehen. Branchanlage, Commit, Push, Merge und Modul-Update sind durch das Erstellen dieses Plans nicht ausgelöst.

## Offene Entscheidungen vor dem jeweiligen Arbeitspaket

| Frage | Erforderliche Klärung | Zeitpunkt |
| --- | --- | --- |
| Weitere Beziehungen zwischen Advanced-Parametern | Bestätigte Herstellerregel statt Annahme aus dem Namen | Vor Erweiterung der Regeln in AP03 |
| Ursache und Laufzeit der langen Abfragen | Laufzeitmessung der einzelnen Gruppen auf Symcon TEST und Bewertung gemeinsam genutzter Kommunikation | Nach Auslieferung des Branchstands |
| Altersgrenzen für Messwerte | Implementiert als max(300 Sekunden, dreifaches Abfrageintervall); gegen reale Intervall- und Gruppenlaufzeiten auf TEST beobachten | Nach Auslieferung des Branchstands |
| Liveübernahme | Konkreter TEST- und Updateumfang; produktive manuelle Modbus-Struktur bleibt separat geplant | Vor dem jeweiligen Symcon-Test und vor Auslieferung |

Ungeklärte Fehlerbit-Zuordnungen, die physikalische Bedeutung offener Register und die Filter-Restlaufzeitschätzung werden dadurch nicht automatisch geklärt. Ihre vorhandenen Kennzeichnungen bleiben bestehen, bis ein gesonderter fachlicher Nachweis vorliegt.

## Abschluss und Pflege

Ein Arbeitspaket erhält erst nach Umsetzung und zugehörigem Nachweis den Status abgeschlossen. Das Gesamtziel ist erst erreicht, wenn sämtliche Zielkriterien erfüllt und die offenen Entscheidungen, soweit sie für die ausgelieferten Funktionen erforderlich sind, geklärt sind.

| Paket | Status | Ausgeführter Nachweis nach Umsetzung | Ergebnis und offene Punkte |
| --- | --- | --- | --- |
| AP00 | Abgeschlossen | Raum- und Eingabeprüfungen sowie PHP-Syntaxprüfung in PHP 8.5.11 | Bestanden |
| AP01 | Abgeschlossen | Eingabewerte, Grenzen, Enum, Schalter, Ganzgrad-Rundung und Registerauflösung | Bestanden |
| AP02 | Abgeschlossen | FWT Room Panel IDs 0/21/negativ/gebrochen; gültige Adressen; Panelwechsel | Bestanden, FWT 1 ausgeschlossen |
| AP03 | Abgeschlossen | Frischer Lüfter-Gegenwert, fehlender Wert, veralteter Cache, Unter-/Obergrenze | Bestanden, ohne Modbuszugriff |
| AP04 | Lokal abgeschlossen | JSON-Nachrichten, initiales Objekt, ungültige Nachricht, Modus 0 und unbekannter Modus | Isoliert bestanden; offene Kachelaktualisierung und Logprüfung auf TEST offen |
| AP05 | Lokal abgeschlossen | Sperre, Fehlerfreigabe, Intervalllogik, Antwortlänge, Registergruppen | Isoliert bestanden; reale Laufzeit-/Timeoutmessung auf TEST offen |
| AP06 | Lokal abgeschlossen | Per-Gruppenqualität, Alter, Kachelablauf, Sensorreaktivierung | Isoliert bestanden; Live-Meldungsverlusttest auf TEST offen |
| AP07 | Zurückgestellt | FWT 1 nicht bearbeitet oder getestet | Außerhalb des Auftragsumfangs |
| AP08 | Lokal abgeschlossen | Advanced-Referenzwechsel, Verbindungsprüfung, Sensor-IDs | Isoliert bestanden; tatsächliche Referenz- und Archivkonfiguration auf TEST offen |
| AP09 | Lokal abgeschlossen | Modulnotizen, Registergruppenkatalog, Metadatenvalidator, CI-Workflow, Update-/Rollback-Anleitung; MCP-Dateivergleich gegen Symcon | Lokale Prüfungen bestanden; installierte Moduldateien sind nicht aktuell, deshalb sind GitHub-Workflow nach Push, StylePHP vor Release und Symcon TEST noch ausstehend. Der Rollback-Ablauf ist dokumentiert, aber noch nicht auf TEST ausgeführt |

## Referenzen

- [Projektroadmap](../ROADMAP.md)
- [FWT Registerkatalog](../Proxon%20FWT%20System%20Monitoring/REGISTERS.md)
- [Bibliotheksübersicht](../README.md)
- [Symcon Module und öffentliche Methodensignaturen](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/)
- [Symcon UpdateVisualizationValue](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/updatevisualizationvalue/)
- [Symcon SelectInstance](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/configuration-forms/selectinstance/)
- [Symcon MaintainVariable](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/maintainvariable/)
