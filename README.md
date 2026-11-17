# Visu Design für IP-Symcon

Zentrale Farben für alle HTML-Kacheln der Kachel-Visualisierung (Farbkonzept W5x).
Die Kachel-Module holen sich die Farben hier ab und haben selbst keine Farbeinstellungen mehr.

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

Mit „Farbkonzept W5x wiederherstellen“ werden alle Werte zurückgesetzt.

## Funktionen

| Funktion | Ergebnis |
|---|---|
| `W5D_GetCSS(int $InstanzID)` | CSS-Text: helle Werte auf `:root`, dunkle auf `body.dark` |
| `W5D_GetColors(int $InstanzID)` | JSON `{"light":{…},"dark":{…}}` für HTML-Boxen ohne Dark-Mode-Erkennung |
| `W5D_ResetDefaults(int $InstanzID)` | setzt das Farbkonzept W5x |

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
