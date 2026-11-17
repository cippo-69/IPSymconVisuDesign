# Visu Design für IP-Symcon

Zentrale Farben für die Kachel-Visualisierung (Farbkonzept W5x).
Die Kachel-Module holen sich die Farben hier ab und haben selbst keine Farbeinstellungen mehr.
Zusätzlich überträgt das Modul die Farben auf Wochenpläne, Variablendarstellungen, eigene Profile und den Akzent der eingebauten Kacheln. Farbsätze lassen sich als Themes speichern.

## Einrichtung

1. Modul über die Modulverwaltung hinzufügen: `https://github.com/cippo-69/IPSymconVisuDesign`
2. Eine Instanz „Visu Design“ anlegen. Gibt es mehrere, gilt die mit der kleinsten ID.
3. Farben im Konfigurationsformular einstellen: je Farbe ein Wert für den hellen Modus und einer für den Dark Mode.
4. Die Visu neu laden; die Kacheln lesen die Farben beim Aufbau.

## Farben

| Variable | Bedeutung | hell | dunkel |
|---|---|---|---|
| `--w5-fg` | Text | #1E2224 | #F1F0EB |
| `--w5-muted` | Nebentext | #6B7175 | #A9AEB1 |
| `--w5-accent` | Akzent (Bedienung, aktiv) | #2F6B4F | #74B892 |
| `--w5-on` | Schrift auf Akzent- und Alarmfläche | #FFFFFF | #1E2224 |
| `--w5-warm` | Wärme (Heizen) | #8E2B26 | #C9806C |
| `--w5-water` | Wasser, Kühle, Absenkung | #2C6A86 | #7DB6CF |
| `--w5-sun` | Sonne, PV | #7A6000 | #F2CF63 |
| `--w5-warn` | Warnung | #9A5200 | #EE9A45 |
| `--w5-alarm` | Alarm (nur als Fläche) | #C8321F | #FF5A47 |

## Themes

Eingebaut (nicht änderbar):

| Theme | Charakter | Akzent hell / dunkel |
|---|---|---|
| Farbkonzept W5x | Schwarzwald: Tannengrün, Ochsenrot, Zink | #2F6B4F / #74B892 |
| Fjord | kühl, nordisch: Petrol, Eisblau, Schiefer | #0F6E80 / #5CC8D7 |
| Indigo | modern, kräftig: Indigo mit Orange und Cyan | #4F46E5 / #A5B4FC |

Im Aktionsbereich: Theme wählen → „Laden und anwenden“. Eigene Farben mit Namen → „Aktuelle Farben als Theme speichern“.
Eigene Themes lassen sich löschen; eingebaute nicht. Alle Texte der Themes erreichen mindestens 4,5:1 Kontrast auf Weiß bzw. #323437.

## Anwenden auf

Ab Werk aus; im Formular unter „Anwenden auf“ einschalten. Geschrieben werden nur Abweichungen; ein Zeitgeber zieht alle 6 Stunden nach
(Module legen Profile neu an).

| Ziel | Wie |
|---|---|
| Wochenpläne | Regeln: Aktionsname enthält … → Farbrolle (Standard: Normal → Wärme, Absenk → Wasser, Bewässerung AN → Wasser, Bewässerung AUS → Nebentext) |
| Variablendarstellungen | alle Farbfelder (z. B. `GLOW_COLOR`, `COLOR`) nach dem ursprünglichen Farbton; Leuchtfarben nehmen den dunklen Wert |
| Eigene Profile | Farben der Zuordnungen nach dem ursprünglichen Farbton; Profile mit `~` und Farbskalen (4 und mehr Farbtöne) bleiben |
| Kachel-Visu | Design-Datei mit `accentColor`, `onAccentColor`, `errorColor`, `onErrorColor`; wird über das helle bzw. dunkle Design des Geräts gelegt |

Alarm-Regel: Zuordnungen und Darstellungen, deren Bezeichnung ein Alarmwort enthält (Standard: Alarm, Fehler, Störung, Error), sind immer in Alarmfarbe;
verneinte Bezeichnungen („Kein Fehler“, „Alarm aus“) sind ausgenommen. Sonst gilt die Zuordnung nach Farbton: Grün → Akzent, Rot → Alarm, Orange → Warnung, Gelb → Sonne, Blau → Wasser; Schwarz, Weiß, Grau und Violett bleiben.
Die ursprüngliche Farbe jeder Stelle wird gemerkt; „Ursprungsfarben wiederherstellen“ setzt Darstellungen und Profile zurück.
Einzelfarben (Wochenplan, Profile, Visu-Akzent) sind die Mitte aus hellem und dunklem Wert. Unter „Ausnahmen“ eingetragene Objekte
(und alles darunter) behalten ihre Darstellungsfarben; Profile, die eine ausgenommene Variable nutzt, bleiben ebenfalls unverändert.
Wochenpläne richten sich nur nach den Regeln.

## Funktionen

| Funktion | Ergebnis |
|---|---|
| `W5D_GetCSS(int $InstanzID)` | CSS-Text: helle Werte auf `:root`, dunkle auf `body.dark` |
| `W5D_GetColors(int $InstanzID)` | JSON `{"light":{…},"dark":{…},"mid":{…}}` für HTML-Boxen ohne Dark-Mode-Erkennung |
| `W5D_GetThemes(int $InstanzID)` | JSON-Liste der Theme-Namen |
| `W5D_LoadTheme(int $InstanzID, string $Name)` | Theme laden und anwenden |
| `W5D_SaveTheme(int $InstanzID, string $Name)` | aktuelle Farben als eigenes Theme speichern |
| `W5D_DeleteTheme(int $InstanzID, string $Name)` | eigenes Theme löschen |
| `W5D_ExportTheme(int $InstanzID, string $Name)` / `W5D_ImportTheme(int $InstanzID, string $JSON)` | Theme als JSON aus- und einlesen (leerer Name = aktuelle Farben) |
| `W5D_Apply(int $InstanzID)` | Farben jetzt übertragen; liefert eine Zusammenfassung |
| `W5D_RestoreOriginals(int $InstanzID)` | Darstellungen und Profile auf die Ursprungsfarben |
| `W5D_ResetDefaults(int $InstanzID)` | lädt das Theme Farbkonzept W5x |

## Einbindung in ein Kachel-Modul

```php
private function DesignStyle(): string
{
    $guid = '{018EEC85-8FFC-4E1F-A966-702C4BBDC2E8}';
    if (!IPS_ModuleExists($guid) || !function_exists('W5D_GetCSS')) {
        return '';
    }
    $ids = IPS_GetInstanceListByModuleID($guid);
    if (count($ids) === 0) {
        return '';
    }
    sort($ids);
    return '<style>' . W5D_GetCSS($ids[0]) . '</style>';
}

public function GetVisualizationTile(): string
{
    return $this->DesignStyle() . file_get_contents(__DIR__ . '/module.html');
}
```

Im `module.html` werden die eigenen Variablen auf `body` (nicht auf `:root`) aus `--w5-*` abgeleitet,
damit `body.dark` die dunklen Werte liefert. Der Wert nach dem Komma gilt ohne Design-Instanz:

```css
body      { --x-accent: var(--w5-accent, #2F6B4F); --x-on: var(--w5-on, #fff); }
body.dark { --x-accent: var(--w5-accent, #74B892); --x-on: var(--w5-on, #1E2224); }
```

Die Klasse `dark` setzt das Kachel-Skript, wenn die Schriftfarbe der Visu hell ist.

## Lizenz

MIT
