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

## Kachel

Die Instanz ist zugleich eine Kachel für die Kachel-Visu (HTML-SDK); einfach die Instanz oder einen Link darauf in die Visu legen.

- Theme-Karten mit Farbstreifen (Akzent, Wärme, Wasser, Sonne, Alarm im aktuellen Modus); Antippen lädt und wendet das Theme an.
- „Farben anpassen“: alle Farbrollen hell und dunkel mit Farbwählern, „Übernehmen“ / „Verwerfen“, „Als Theme speichern“.
- Eigene Themes löschen (zweites Antippen bestätigt). „Jetzt anwenden“ zeigt das Ergebnis.
- Kleine Kachel (unter 300 px Höhe): aktives Theme und Theme-Auswahl als Chips.
- Die Kachel färbt sich selbst mit dem gewählten Theme um.

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
| `W5D_GetTileCSS(int $InstanzID)` | CSS-Bausteine für HTML-Kacheln (Wurzel `class="w5t"`), siehe „Kachel-Bausteine“ |
| `W5D_GetTileJS(int $InstanzID)` | Skript für HTML-Kacheln: setzt `body.dark` nach der Schriftfarbe der Visu, merkt aufgeklappte Abschnitte |
| `W5D_GetColors(int $InstanzID)` | JSON `{"light":{…},"dark":{…},"mid":{…}}` für HTML-Boxen ohne Dark-Mode-Erkennung |
| `W5D_GetThemes(int $InstanzID)` | JSON-Liste der Theme-Namen |
| `W5D_LoadTheme(int $InstanzID, string $Name)` | Theme laden und anwenden |
| `W5D_SaveTheme(int $InstanzID, string $Name)` | aktuelle Farben als eigenes Theme speichern |
| `W5D_DeleteTheme(int $InstanzID, string $Name)` | eigenes Theme löschen |
| `W5D_ExportTheme(int $InstanzID, string $Name)` / `W5D_ImportTheme(int $InstanzID, string $JSON)` | Theme als JSON aus- und einlesen (leerer Name = aktuelle Farben) |
| `W5D_SetColors(int $InstanzID, string $JSON)` | Farben setzen: `{Rolle: ["#hell", "#dunkel"]}` |
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

## Kachel-Bausteine (ab 1.4)

Für HTML-Kacheln aus Skripten (Variable mit `~HTMLBox`) liefert das Modul Layout und Dark-Mode-Erkennung mit.
Die Kachel enthält dann kein eigenes CSS mehr; fehlt ein Baustein, wird er hier ergänzt.

```php
$id  = 12345; // Instanz Visu Design
$css = W5D_GetCSS($id) . W5D_GetTileCSS($id);
$html = '<style>' . $css . '</style>'
    . '<div class="w5t" data-store="meinekachel">' . $inhalt . '</div>'
    . '<script>' . W5D_GetTileJS($id) . '</script>';
```

| Baustein | Markup |
|---|---|
| Kopf | `<div class="head"><div class="st"><div class="v"><span class="dot ok"></span>Online</div><div class="sub">…</div></div><div class="facts"><span>Label <b>Wert</b></span></div></div>` |
| Karte | `<div class="card"><span class="lbl">Titel</span> … </div>` |
| Zeile | `<div class="row"><div class="t"><b>Titel</b><span class="sub">Zusatz</span></div><span class="r">Wert</span></div>` (`r wide` für lange Werte) |
| Abschnitt | `<details data-k="schluessel"><summary><span class="lbl">Titel</span><span class="sub">Info</span></summary> … </details>` in einer Karte |
| Chips | `<div class="chips"><span class="chip"><span class="dot sm ok"></span>Text</span></div>` |
| Hinweis-Badges | `<div class="badges"><span class="badge">Text</span></div>` |
| Balken | `<div class="bar"><span>Label</span><b>Wert</b><div class="tr"><i style="width:40%"></i></div></div>` |
| Texte | `.sub` Nebentext, `.hint`, `.meta` Fußzeile, `.bad` Alarmfarbe, `.warn` Warnfarbe |
| Punkte | `.dot` mit `ok` (Akzent), `bad` (Alarm, blinkt), `warn`; `.dot.sm` klein |

`data-store` an der Wurzel: Name, unter dem das Skript aufgeklappte Abschnitte im Browser merkt (localStorage `w5t:<Name>`).

### Ab 1.5: Bausteine für Kachel-Module (HTML-SDK)

Kachel-Module liefern `<style>` mit `W5D_GetCSS` + `W5D_GetTileCSS`, danach `module.html` mit Wurzel `<div class="w5t fill">` und `<script>` mit `W5D_GetTileJS`.
Das Skript stellt `w5tTheme()` bereit; Module rufen es bei jedem Neuzeichnen auf (Hell/Dunkel). In `module.html` steht dann nur noch CSS für Sonderelemente, das ausschließlich `--w5t-*`/`--w5-*` nutzt.

| Baustein | Markup / Klassen |
|---|---|
| Vollfläche | `.w5t.fill` – füllt die Kachel, scrollt, oben Platz für den Kacheltitel (`--w5t-top`, Standard 58 px); `body.compact` (kleine Kachel) mit `.more` = in der kleinen Kachel ausgeblendet |
| Status | `.dot.busy` (Warnfarbe, pulsiert), `.badge.warn`, `.card.alarm`, `.card.warn`, `.bar .tr i.warn` / `i.bad`, `.bars` |
| Werte-Raster | `.kv` mit `<span>Name</span><b>Wert</b>` |
| Schalter | `<button class="tg on">` (`.tg.bad` = Alarmfarbe, `:disabled`) |
| Buttons | `.btn` (neutral), `.btn.acc` (Akzent), `.btn.bad` (Gefahr), zusätzlich `.solid`, `.sm`, `:disabled`; auch als `<a>`. Gruppe `.acts`, Textlink `.lnk` |
| Eingaben | `.inrow` mit `input`/`select`, einzeln `.in` |
| Segment-Umschalter | `<div class="seg"><button class="on">A</button><button>B</button></div>`, `.seg.sm` |
| Chips | `button.chip`, `.chip.on`, `.chip.sel` |
| Hinweisleiste | `.toast` |
| Farb-Token | `--w5t-knob` (Schalter-Knopf), `--w5t-shadow` |
| Schieberegler (ab 1.6) | `<input type="range" class="rng">`; Spur über `--tr` (z. B. Farbverlauf), Knopffarbe über `--th` |
| Rundknopf (ab 1.6) | `<button class="ib"><svg>…</svg></button>` (Akzentfläche), `.ib.plain` (neutral), `.ib.sm` |
| Raster (ab 1.6) | `<div class="grid" style="--min:112px">` mit Chips (Text wird gekürzt) |
| Farbpunkt im Chip (ab 1.6) | `<button class="chip"><i style="--c:#f80"></i>Text</button>` |
| Sonstiges (ab 1.6) | `.dim` (abgeschwächt), `.num` (Ziffern gleich breit) |

## Lizenz

MIT
