<?php

declare(strict_types=1);

/**
 * Visu Design: zentrale Farben für die Visualisierung (Farbkonzept W5x).
 *
 * - Kachel-Module holen sich die Farben hier ab (W5D_GetCSS / W5D_GetColors) und haben keine eigenen Farbeinstellungen.
 * - Themes: eingebaute und eigene Farbsätze laden, speichern, löschen, exportieren, importieren.
 * - Anwenden auf: Wochenplan-Aktionen (Regeln nach Aktionsname), Farben in Variablendarstellungen und eigenen Profilen
 *   (Zuordnung zur Farbrolle nach dem ursprünglichen Farbton) und den Akzent der Kachel-Visu (Design-Datei).
 * Eine Instanz genügt; bei mehreren gilt die mit der kleinsten ID.
 */
class VisuDesign extends IPSModule
{
    public const MODULE_ID = '{018EEC85-8FFC-4E1F-A966-702C4BBDC2E8}';
    private const VISU_MODULE = '{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}';

    /** Farbrollen */
    private const TOKENS = [
        'fg'     => 'Text',
        'muted'  => 'Nebentext',
        'accent' => 'Akzent (Bedienung, aktiv, OK)',
        'on'     => 'Schrift auf Akzent- und Alarmfläche',
        'warm'   => 'Wärme (Heizen)',
        'water'  => 'Wasser, Kühle, Absenkung',
        'sun'    => 'Sonne, PV, Licht',
        'warn'   => 'Warnung',
        'alarm'  => 'Alarm (nur als Fläche)'
    ];

    /** Eingebaute Themes: Rolle => [hell, dunkel] */
    private const THEMES = [
        'Farbkonzept W5x' => [
            'fg' => [0x1E2224, 0xF1F0EB], 'muted' => [0x6B7175, 0xA9AEB1], 'accent' => [0x2F6B4F, 0x74B892], 'on' => [0xFFFFFF, 0x1E2224],
            'warm' => [0x8E2B26, 0xC9806C], 'water' => [0x2C6A86, 0x7DB6CF], 'sun' => [0x7A6000, 0xF2CF63], 'warn' => [0x9A5200, 0xEE9A45], 'alarm' => [0xC8321F, 0xFF5A47]
        ],
        // kühl, nordisch: Petrol, Eisblau, Schiefer
        'Fjord' => [
            'fg' => [0x15202B, 0xE9EEF2], 'muted' => [0x5C6B78, 0x9DAEBB], 'accent' => [0x0F6E80, 0x5CC8D7], 'on' => [0xFFFFFF, 0x0B1A20],
            'warm' => [0xB5452A, 0xF2936B], 'water' => [0x2A5DA8, 0x8DB5F5], 'sun' => [0x7D6200, 0xF4C95D], 'warn' => [0xA04800, 0xF59B4A], 'alarm' => [0xC42B2B, 0xFF6B6B]
        ],
        // modern, kräftig: Indigo mit Orange und Cyan
        'Indigo' => [
            'fg' => [0x1C1A2E, 0xEDEDF7], 'muted' => [0x67647E, 0xA8A5C0], 'accent' => [0x4F46E5, 0xA5B4FC], 'on' => [0xFFFFFF, 0x1C1A2E],
            'warm' => [0xC2410C, 0xFB923C], 'water' => [0x0E7490, 0x67D7EE], 'sun' => [0x806600, 0xFDE047], 'warn' => [0xB45309, 0xFBBF24], 'alarm' => [0xDC2626, 0xF87171]
        ]
    ];
    private const DEFAULT_THEME = 'Farbkonzept W5x';

    private const DEFAULT_RULES = [
        ['Pattern' => 'Normal', 'Token' => 'warm'],
        ['Pattern' => 'Absenk', 'Token' => 'water'],
        ['Pattern' => 'Bewässerung AN', 'Token' => 'water'],
        ['Pattern' => 'Bewässerung AUS', 'Token' => 'muted']
    ];

    public function Create()
    {
        parent::Create();
        foreach (self::THEMES[self::DEFAULT_THEME] as $token => [$light, $dark]) {
            $this->RegisterPropertyInteger($this->Prop($token, false), $light);
            $this->RegisterPropertyInteger($this->Prop($token, true), $dark);
        }
        $this->RegisterPropertyBoolean('ApplySchedules', false);
        $this->RegisterPropertyString('ScheduleRules', json_encode(self::DEFAULT_RULES));
        $this->RegisterPropertyBoolean('ApplyPresentations', false);
        $this->RegisterPropertyBoolean('ApplyProfiles', false);
        $this->RegisterPropertyInteger('VisuID', 0);
        $this->RegisterPropertyString('Exclude', '[]');
        $this->RegisterPropertyString('AlarmWords', 'Alarm, Fehler, Störung, Error');

        $this->RegisterAttributeString('Themes', '{}');     // eigene Themes: Name => Rolle => [hell, dunkel]
        $this->RegisterAttributeString('ColorMap', '{}');   // Farbstelle => {t: Rolle|null, o: ursprüngliche Farbe}
        $this->RegisterAttributeString('LastApply', '');

        $this->RegisterTimer('Reapply', 0, 'W5D_Apply($_IPS[\'TARGET\']);');

        $this->SetVisualizationType(1); // HTML-SDK-Kachel: Themes wählen, Farben anpassen, speichern
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        if (!$this->IsPrimary()) {
            $this->SetSummary('inaktiv – es gilt Instanz ' . $this->PrimaryID());
            $this->SetTimerInterval('Reapply', 0);
            $this->SetStatus(104);
            return;
        }
        $name = $this->CurrentThemeName();
        $this->SetSummary($name !== '' ? 'Theme ' . $name : 'eigene Farben');
        $this->SetStatus(102);
        // Module legen Profile neu an; regelmäßig nachziehen (schreibt nur Abweichungen)
        $this->SetTimerInterval('Reapply', 6 * 3600 * 1000);
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->Apply();
        }
        $this->Push();
    }

    // ------------------------------------------------------------------ Kachel

    public function GetVisualizationTile(): string
    {
        return '<style id="w5d">' . $this->GetCSS() . '</style>' . file_get_contents(__DIR__ . '/module.html')
            . '<script>handleMessage(' . json_encode(json_encode($this->TileState())) . ');</script>';
    }

    public function RequestAction($Ident, $Value)
    {
        $msg = '';
        switch ($Ident) {
            case 'LoadTheme':
                $this->LoadTheme((string) $Value);   // ApplyChanges sendet den neuen Zustand
                return;
            case 'SetColors':
                $msg = $this->SetColors((string) $Value);
                break;
            case 'SaveTheme':
                $msg = $this->SaveThemeChecked((string) $Value);
                break;
            case 'DeleteTheme':
                if (isset(self::THEMES[(string) $Value])) {
                    $msg = 'Eingebaute Themes können nicht gelöscht werden';
                } else {
                    $this->DeleteTheme((string) $Value);
                    $msg = '„' . $Value . '“ gelöscht';
                }
                break;
            case 'Apply':
                $msg = $this->Apply();
                break;
            default:
                throw new Exception('Unbekannte Aktion ' . $Ident);
        }
        $this->Push($msg);
    }

    /** Farben aus der Kachel übernehmen: JSON {Rolle: ["#hell", "#dunkel"]}. */
    public function SetColors(string $JSON): string
    {
        $in = json_decode($JSON, true);
        if (!is_array($in)) {
            return 'Ungültige Farben';
        }
        $n = 0;
        foreach (self::TOKENS as $token => $caption) {
            $pair = $in[$token] ?? null;
            if (!is_array($pair) || count($pair) !== 2) {
                continue;
            }
            foreach ([false, true] as $i => $dark) {
                if (!preg_match('/^#?[0-9A-Fa-f]{6}$/', (string) $pair[$i])) {
                    continue;
                }
                $c = hexdec(ltrim((string) $pair[$i], '#'));
                if ($this->ReadPropertyInteger($this->Prop($token, $dark)) !== $c) {
                    IPS_SetProperty($this->InstanceID, $this->Prop($token, $dark), $c);
                    $n++;
                }
            }
        }
        if ($n > 0) {
            IPS_ApplyChanges($this->InstanceID);
        }
        return $n > 0 ? $n . ' Farben übernommen' : 'Keine Änderung';
    }

    private function SaveThemeChecked(string $Name): string
    {
        $Name = trim($Name);
        if ($Name === '') {
            return 'Bitte einen Namen eingeben';
        }
        if (isset(self::THEMES[$Name])) {
            return 'Eingebaute Themes können nicht überschrieben werden';
        }
        $this->SaveTheme($Name);
        return 'Gespeichert als „' . $Name . '“';
    }

    private function Push(string $msg = ''): void
    {
        $state = $this->TileState();
        if ($msg !== '') {
            $state['msg'] = $msg;
        }
        $this->UpdateVisualizationValue(json_encode($state));
    }

    private function TileState(): array
    {
        $hexPair = fn (array $p) => [$this->Hex((int) $p[0]), $this->Hex((int) $p[1])];
        $themes = [];
        foreach ($this->AllThemes() as $name => $t) {
            $colors = [];
            foreach (array_keys(self::TOKENS) as $token) {
                $colors[$token] = $hexPair($t[$token] ?? [0, 0]);
            }
            $themes[] = ['name' => (string) $name, 'builtin' => isset(self::THEMES[$name]), 'colors' => $colors];
        }
        $colors = [];
        foreach ($this->CurrentColors() as $token => $p) {
            $colors[$token] = $hexPair($p);
        }
        return [
            'design'  => $this->GetCSS(),
            'current' => $this->CurrentThemeName(),
            'themes'  => $themes,
            'colors'  => $colors,
            'tokens'  => self::TOKENS,
            'last'    => $this->ReadAttributeString('LastApply')
        ];
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === IPS_KERNELSTARTED && $this->IsPrimary()) {
            $this->Apply();
        }
    }

    public function GetConfigurationForm()
    {
        $tokenOptions = [];
        foreach (self::TOKENS as $t => $caption) {
            $tokenOptions[] = ['caption' => $caption, 'value' => $t];
        }
        $colorRows = [['type' => 'Label', 'caption' => 'Linke Spalte heller Modus, rechte Spalte Dark Mode. Kacheln übernehmen Änderungen nach dem Neuladen der Visu.']];
        foreach (self::TOKENS as $token => $caption) {
            $colorRows[] = ['type' => 'RowLayout', 'items' => [
                ['type' => 'SelectColor', 'name' => $this->Prop($token, false), 'caption' => $caption . ' (hell)', 'width' => '320px'],
                ['type' => 'SelectColor', 'name' => $this->Prop($token, true), 'caption' => $caption . ' (dunkel)', 'width' => '320px']
            ]];
        }
        $themeOptions = [];
        foreach ($this->AllThemes() as $n => $t) {
            $themeOptions[] = ['caption' => $n . (isset(self::THEMES[$n]) ? ' (eingebaut)' : ''), 'value' => $n];
        }
        $current = $this->CurrentThemeName();

        return json_encode([
            'elements' => [
                ['type' => 'ExpansionPanel', 'caption' => 'Farben' . ($current !== '' ? ' – Theme „' . $current . '“' : ' – eigene Farben'), 'items' => $colorRows],
                ['type' => 'ExpansionPanel', 'caption' => 'Anwenden auf', 'items' => [
                    ['type' => 'CheckBox', 'name' => 'ApplySchedules', 'caption' => 'Wochenpläne: Farben der Aktionen nach den Regeln unten'],
                    ['type' => 'List', 'name' => 'ScheduleRules', 'caption' => 'Regeln (Aktionsname enthält … → Farbe; die erste passende gilt)', 'rowCount' => 5, 'add' => true, 'delete' => true,
                        'columns' => [
                            ['caption' => 'Aktionsname enthält', 'name' => 'Pattern', 'width' => '260px', 'add' => '', 'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Farbe', 'name' => 'Token', 'width' => 'auto', 'add' => 'accent', 'edit' => ['type' => 'Select', 'options' => $tokenOptions]]
                        ]],
                    ['type' => 'CheckBox', 'name' => 'ApplyPresentations', 'caption' => 'Variablendarstellungen: Farbfelder (z. B. Leuchtfarbe von Schaltern) nach ihrem Farbton'],
                    ['type' => 'CheckBox', 'name' => 'ApplyProfiles', 'caption' => 'Eigene Variablenprofile: Farben der Zuordnungen nach ihrem Farbton (Farbskalen mit 4 und mehr Farben bleiben)'],
                    ['type' => 'ValidationTextBox', 'name' => 'AlarmWords', 'caption' => 'Alarmwörter: Zuordnungen und Darstellungen mit diesen Wörtern immer in Alarmfarbe (Komma-getrennt; „Kein …“, „… aus“ ausgenommen)', 'width' => '640px'],
                    ['type' => 'SelectInstance', 'name' => 'VisuID', 'caption' => 'Kachel-Visu: Akzent der eingebauten Kacheln (0 = nicht ändern)', 'validModules' => [self::VISU_MODULE], 'width' => '420px'],
                    ['type' => 'List', 'name' => 'Exclude', 'caption' => 'Ausnahmen für Darstellungen und Profile (Objekte und alles darunter; Profile, die sie nutzen, bleiben ebenfalls)', 'rowCount' => 5, 'add' => true, 'delete' => true,
                        'columns' => [
                            ['caption' => 'Objekt', 'name' => 'ObjectID', 'width' => 'auto', 'add' => 0, 'edit' => ['type' => 'SelectObject']]
                        ]],
                    ['type' => 'Label', 'caption' => 'Einzelfarben (Wochenplan, Profile, Visu-Akzent) sind die Mitte aus hellem und dunklem Wert; Leuchtfarben nutzen den dunklen Wert.']
                ]]
            ],
            'actions' => [
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'Select', 'name' => 'ThemeName', 'caption' => 'Theme', 'options' => $themeOptions, 'value' => $current !== '' ? $current : self::DEFAULT_THEME, 'width' => '260px'],
                    ['type' => 'Button', 'caption' => 'Laden und anwenden', 'onClick' => 'W5D_LoadTheme($id, $ThemeName);'],
                    ['type' => 'Button', 'caption' => 'Löschen', 'onClick' => 'W5D_DeleteTheme($id, $ThemeName);', 'confirm' => 'Dieses eigene Theme löschen?']
                ]],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'ValidationTextBox', 'name' => 'NewThemeName', 'caption' => 'Name für eigenes Theme', 'width' => '260px'],
                    ['type' => 'Button', 'caption' => 'Aktuelle Farben als Theme speichern', 'onClick' => 'W5D_SaveTheme($id, $NewThemeName);']
                ]],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'Button', 'caption' => 'Jetzt anwenden', 'onClick' => 'echo W5D_Apply($id);'],
                    ['type' => 'Button', 'caption' => 'Ursprungsfarben wiederherstellen', 'onClick' => 'echo W5D_RestoreOriginals($id);',
                        'confirm' => 'Darstellungen und Profile auf ihre ursprünglichen Farben zurücksetzen und das Anwenden darauf abschalten?']
                ]],
                ['type' => 'Label', 'caption' => 'Zuletzt angewendet: ' . ($this->ReadAttributeString('LastApply') ?: '–')],
                ['type' => 'Label', 'caption' => 'CSS-Variablen für Kacheln: --w5-' . implode(', --w5-', array_keys(self::TOKENS))]
            ],
            'status' => [
                ['code' => 104, 'icon' => 'inactive', 'caption' => 'Weitere Instanz – es gilt die Instanz mit der kleinsten ID']
            ]
        ]);
    }

    // ------------------------------------------------------------------ Farben für Kacheln

    /** CSS mit den Variablen --w5-*; hell auf :root, dunkel auf body.dark (Dark-Mode-Klasse der Kacheln). */
    public function GetCSS(): string
    {
        $light = $dark = '';
        foreach (array_keys(self::TOKENS) as $token) {
            $light .= '--w5-' . $token . ':' . $this->Hex($this->Color($token, 'light')) . ';';
            $dark .= '--w5-' . $token . ':' . $this->Hex($this->Color($token, 'dark')) . ';';
        }
        return ':root{' . $light . '}body.dark{' . $dark . '}';
    }

    /** JSON {"light":{...},"dark":{...},"mid":{...}}; mid = Einzelfarbe für Stellen ohne Dark-Mode-Erkennung. */
    public function GetColors(): string
    {
        $out = ['light' => [], 'dark' => [], 'mid' => []];
        foreach (array_keys(self::TOKENS) as $token) {
            foreach (array_keys($out) as $mode) {
                $out[$mode][$token] = $this->Hex($this->Color($token, $mode));
            }
        }
        return json_encode($out);
    }

    /** Gemeinsame Bausteine für HTML-Kacheln (Wurzel class="w5t"): Kopf, Karten, Zeilen, Chips, Abschnitte; Farben aus --w5-*. */
    public function GetTileCSS(): string
    {
        return (string) @file_get_contents(__DIR__ . '/tile.css');
    }

    /** Skript für HTML-Kacheln: setzt body.dark nach der Schriftfarbe der Visu und merkt aufgeklappte Abschnitte (details[data-k]). */
    public function GetTileJS(): string
    {
        return (string) @file_get_contents(__DIR__ . '/tile.js');
    }

    // ------------------------------------------------------------------ Themes

    /** JSON-Liste aller Theme-Namen (eingebaute zuerst). */
    public function GetThemes(): string
    {
        return json_encode(array_keys($this->AllThemes()));
    }

    public function LoadTheme(string $Name): void
    {
        $theme = $this->AllThemes()[$Name] ?? null;
        if ($theme === null) {
            echo 'Theme „' . $Name . '“ nicht gefunden';
            return;
        }
        foreach (array_keys(self::TOKENS) as $token) {
            if (isset($theme[$token])) {
                IPS_SetProperty($this->InstanceID, $this->Prop($token, false), (int) $theme[$token][0]);
                IPS_SetProperty($this->InstanceID, $this->Prop($token, true), (int) $theme[$token][1]);
            }
        }
        IPS_ApplyChanges($this->InstanceID);
        $this->ReloadForm();
    }

    public function SaveTheme(string $Name): void
    {
        $Name = trim($Name);
        if ($Name === '' || isset(self::THEMES[$Name])) {
            echo $Name === '' ? 'Bitte einen Namen angeben' : 'Eingebaute Themes können nicht überschrieben werden';
            return;
        }
        $themes = $this->SavedThemes();
        $themes[$Name] = $this->CurrentColors();
        $this->WriteAttributeString('Themes', json_encode($themes));
        $this->Push();
        $this->SetSummary('Theme ' . $Name);
        $this->ReloadForm();
    }

    public function DeleteTheme(string $Name): void
    {
        if (isset(self::THEMES[$Name])) {
            echo 'Eingebaute Themes können nicht gelöscht werden';
            return;
        }
        $themes = $this->SavedThemes();
        unset($themes[$Name]);
        $this->WriteAttributeString('Themes', json_encode($themes));
        $this->Push();
        $this->ReloadForm();
    }

    /** Theme als JSON {"name":…, "colors":{Rolle:["#hell","#dunkel"]}}; leerer Name = aktuelle Farben. */
    public function ExportTheme(string $Name): string
    {
        $theme = $Name === '' ? $this->CurrentColors() : ($this->AllThemes()[$Name] ?? null);
        if ($theme === null) {
            return '';
        }
        $colors = [];
        foreach ($theme as $token => [$l, $d]) {
            $colors[$token] = [$this->Hex($l), $this->Hex($d)];
        }
        return json_encode(['name' => $Name !== '' ? $Name : 'Aktuell', 'colors' => $colors], JSON_UNESCAPED_UNICODE);
    }

    /** Theme aus ExportTheme-JSON übernehmen (wird als eigenes Theme gespeichert, nicht geladen). */
    public function ImportTheme(string $JSON): void
    {
        $in = json_decode($JSON, true);
        $name = trim((string) ($in['name'] ?? ''));
        if (!is_array($in) || $name === '' || isset(self::THEMES[$name]) || !is_array($in['colors'] ?? null)) {
            echo 'Ungültiges Theme oder Name eines eingebauten Themes';
            return;
        }
        $theme = $this->CurrentColors();
        foreach ($in['colors'] as $token => $pair) {
            if (isset(self::TOKENS[$token]) && is_array($pair) && count($pair) === 2) {
                $theme[$token] = [hexdec(ltrim((string) $pair[0], '#')) & 0xFFFFFF, hexdec(ltrim((string) $pair[1], '#')) & 0xFFFFFF];
            }
        }
        $themes = $this->SavedThemes();
        $themes[$name] = $theme;
        $this->WriteAttributeString('Themes', json_encode($themes));
        $this->Push();
        $this->ReloadForm();
    }

    public function ResetDefaults(): void
    {
        $this->LoadTheme(self::DEFAULT_THEME);
    }

    // ------------------------------------------------------------------ Anwenden

    /** Farben auf Wochenpläne, Darstellungen, Profile und Kachel-Visu übertragen; schreibt nur Abweichungen. */
    public function Apply(): string
    {
        if (!$this->IsPrimary()) {
            return 'inaktiv – es gilt Instanz ' . $this->PrimaryID();
        }
        $map = json_decode($this->ReadAttributeString('ColorMap'), true) ?: [];
        $exclude = $this->ExcludeRoots();
        $plan = $this->ReadPropertyBoolean('ApplySchedules') ? $this->ApplySchedules() : 0;
        $pres = $this->ReadPropertyBoolean('ApplyPresentations') ? $this->ApplyPresentations($map, $exclude, false) : 0;
        $prof = $this->ReadPropertyBoolean('ApplyProfiles') ? $this->ApplyProfiles($map, $exclude, false) : 0;
        $visu = $this->ApplyVisu();
        $this->WriteAttributeString('ColorMap', json_encode($map));
        $msg = sprintf('%s – geändert: %d Wochenplan-Aktionen, %d Darstellungen, %d Profilfarben, Kachel-Visu %s',
            date('d.m.Y H:i'), $plan, $pres, $prof, $visu === null ? 'nicht gewählt' : ($visu ? 'ja' : 'nein'));
        $this->WriteAttributeString('LastApply', $msg);
        $this->SendDebug('Apply', $msg, 0);
        return $msg;
    }

    /** Darstellungen und Profile auf die ursprünglichen Farben setzen und das Anwenden darauf abschalten. */
    public function RestoreOriginals(): string
    {
        $map = json_decode($this->ReadAttributeString('ColorMap'), true) ?: [];
        $pres = $this->ApplyPresentations($map, $this->ExcludeRoots(), true);
        $prof = $this->ApplyProfiles($map, [], true);
        IPS_SetProperty($this->InstanceID, 'ApplyPresentations', false);
        IPS_SetProperty($this->InstanceID, 'ApplyProfiles', false);
        IPS_ApplyChanges($this->InstanceID);
        return sprintf('Wiederhergestellt: %d Darstellungen, %d Profilfarben. Anwenden auf Darstellungen und Profile ist abgeschaltet.', $pres, $prof);
    }

    /** Wochenpläne richten sich nur nach den Regeln (Ausnahmen gelten hier nicht). */
    private function ApplySchedules(): int
    {
        $rules = json_decode($this->ReadPropertyString('ScheduleRules'), true) ?: [];
        $count = 0;
        foreach (IPS_GetEventList() as $e) {
            $ev = IPS_GetEvent($e);
            if ($ev['EventType'] !== 2) {
                continue;
            }
            foreach ($ev['ScheduleActions'] as $a) {
                $token = null;
                foreach ($rules as $r) {
                    $p = trim((string) ($r['Pattern'] ?? ''));
                    if ($p !== '' && mb_stripos((string) $a['Name'], $p) !== false) {
                        $token = (string) ($r['Token'] ?? '');
                        break;
                    }
                }
                if ($token === null || !isset(self::TOKENS[$token])) {
                    continue;
                }
                $color = $this->Color($token, 'mid');
                if ((int) $a['Color'] !== $color) {
                    IPS_SetEventScheduleActionEx($e, $a['ID'], $a['Name'], $color, $a['ActionID'], $a['ActionParameters']);
                    $count++;
                }
            }
        }
        return $count;
    }

    private function ApplyPresentations(array &$map, array $exclude, bool $restore): int
    {
        $count = 0;
        foreach (IPS_GetVariableList() as $v) {
            if ($this->IsExcluded($v, $exclude)) {
                continue;
            }
            $cp = IPS_GetVariable($v)['VariableCustomPresentation'] ?? [];
            if (!is_array($cp) || count($cp) === 0) {
                continue;
            }
            $changed = false;
            $new = $this->Recolor($cp, 'v:' . $v . ':', $map, $changed, $restore, IPS_GetName($v));
            if ($changed) {
                IPS_SetVariableCustomPresentation($v, $new);
                $count++;
            }
        }
        return $count;
    }

    /** $name: Bezeichnung der Stelle (Variablenname, bei Optionen deren Beschriftung) für die Alarm-Regel. */
    private function Recolor(array $a, string $prefix, array &$map, bool &$changed, bool $restore, string $name): array
    {
        foreach (['Caption', 'CAPTION', 'caption', 'Name', 'NAME'] as $ck) {
            if (isset($a[$ck]) && is_string($a[$ck]) && $a[$ck] !== '') {
                $name = $a[$ck];
                break;
            }
        }
        foreach ($a as $k => $x) {
            if (is_array($x)) {
                $a[$k] = $this->Recolor($x, $prefix . $k . '.', $map, $changed, $restore, $name);
                continue;
            }
            if (is_string($x) && $x !== '' && ($x[0] === '[' || $x[0] === '{')) {
                $j = json_decode($x, true);
                if (is_array($j)) {
                    $inner = false;
                    $j = $this->Recolor($j, $prefix . $k . '.', $map, $inner, $restore, $name);
                    if ($inner) {
                        $a[$k] = json_encode($j);
                        $changed = true;
                    }
                }
                continue;
            }
            if (!is_int($x) || stripos((string) $k, 'COLOR') === false) {
                continue;
            }
            $key = $prefix . $k;
            if ($restore) {
                $target = $map[$key]['o'] ?? null;
            } else {
                if (!array_key_exists($key, $map)) {
                    $map[$key] = ['t' => $this->Classify($x), 'o' => $x];
                }
                $t = $this->IsAlarmName($name) ? 'alarm' : $map[$key]['t'];
                $target = $t === null ? null : $this->Color($t, stripos((string) $k, 'GLOW') !== false ? 'dark' : 'mid');
            }
            if ($target !== null && $x !== (int) $target) {
                $a[$k] = (int) $target;
                $changed = true;
            }
        }
        return $a;
    }

    private function ApplyProfiles(array &$map, array $exclude, bool $restore): int
    {
        // Profile, die eine ausgenommene Variable nutzt, bleiben unverändert
        $skip = [];
        if (count($exclude) > 0) {
            foreach (IPS_GetVariableList() as $v) {
                if ($this->IsExcluded($v, $exclude)) {
                    $var = IPS_GetVariable($v);
                    $skip[$var['VariableCustomProfile']] = true;
                    $skip[$var['VariableProfile']] = true;
                }
            }
        }
        $count = 0;
        foreach (IPS_GetVariableProfileList() as $p) {
            if ($p === '' || $p[0] === '~' || isset($skip[$p])) {
                continue;
            }
            $assoc = IPS_GetVariableProfile($p)['Associations'];
            // Farbskalen (4 und mehr verschiedene Farbtöne im Ursprung) bleiben unverändert
            $orig = [];
            foreach ($assoc as $as) {
                $o = (int) ($map['p:' . $p . ':' . $as['Value']]['o'] ?? $as['Color']);
                if ($this->Classify($o) !== null) {
                    $orig[$o] = true;
                }
            }
            if (count($orig) >= 4) {
                continue;
            }
            foreach ($assoc as $as) {
                $key = 'p:' . $p . ':' . $as['Value'];
                $cur = (int) $as['Color'];
                if ($restore) {
                    $target = $map[$key]['o'] ?? null;
                } else {
                    if (!array_key_exists($key, $map)) {
                        $map[$key] = ['t' => $this->Classify($cur), 'o' => $cur];
                    }
                    $t = $this->IsAlarmName((string) $as['Name']) ? 'alarm' : $map[$key]['t'];
                    $target = $t === null ? null : $this->Color($t, 'mid');
                }
                if ($target !== null && $cur !== (int) $target) {
                    IPS_SetVariableProfileAssociation($p, $as['Value'], $as['Name'], $as['Icon'], (int) $target);
                    $count++;
                }
            }
        }
        return $count;
    }

    /** Akzent und Fehlerfarbe der eingebauten Kacheln (Design-Datei der Kachel-Visu, wird über hell/dunkel gelegt). null = keine Visu gewählt. */
    private function ApplyVisu(): ?bool
    {
        $visu = $this->ReadPropertyInteger('VisuID');
        if ($visu <= 0 || !IPS_InstanceExists($visu)) {
            return null;
        }
        $acc = $this->Color('accent', 'mid');
        $alarm = $this->Color('alarm', 'mid');
        $design = [
            'accentColor'   => $this->Argb($acc),
            'onAccentColor' => $this->Argb($this->OnColor($acc)),
            'errorColor'    => $this->Argb($alarm),
            'onErrorColor'  => $this->Argb($this->OnColor($alarm))
        ];
        $b64 = base64_encode(json_encode($design));
        if (IPS_GetProperty($visu, 'OverrideDesign') === true && IPS_GetProperty($visu, 'CustomDesign') === $b64) {
            return false;
        }
        IPS_SetProperty($visu, 'OverrideDesign', true);
        IPS_SetProperty($visu, 'CustomDesign', $b64);
        IPS_ApplyChanges($visu);
        return true;
    }

    // ------------------------------------------------------------------ Hilfen

    /** Farbrolle eines ursprünglichen Farbtons; null = neutral (Schwarz, Weiß, Grau, Violett) oder keine Farbe. */
    /** Alarm-Regel: Bezeichnung enthält ein Alarmwort und ist nicht verneint („Kein Fehler“, „Alarm aus“ bleiben). */
    private function IsAlarmName(string $name): bool
    {
        if ($name === '' || preg_match('/\b(kein|keine|keiner|keinen|ohne|nicht|no|none|aus|off|inaktiv)\b/iu', $name)) {
            return false;
        }
        foreach (preg_split('/\s*,\s*/', $this->ReadPropertyString('AlarmWords')) as $w) {
            if ($w !== '' && mb_stripos($name, $w) !== false) {
                return true;
            }
        }
        return false;
    }

    private function Classify(int $c): ?string
    {
        if ($c < 0) {
            return null;
        }
        $r = (($c >> 16) & 255) / 255;
        $g = (($c >> 8) & 255) / 255;
        $b = ($c & 255) / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $d = $max - $min;
        $l = ($max + $min) / 2;
        if ($d < 0.2 || $l < 0.1 || $l > 0.95) {
            return null;
        }
        if ($max === $r) {
            $h = 60 * fmod((($g - $b) / $d) + 6, 6);
        } elseif ($max === $g) {
            $h = 60 * ((($b - $r) / $d) + 2);
        } else {
            $h = 60 * ((($r - $g) / $d) + 4);
        }
        if ($h < 15 || $h >= 340) {
            return 'alarm';
        }
        if ($h < 45) {
            return 'warn';
        }
        if ($h < 70) {
            return 'sun';
        }
        if ($h < 170) {
            return 'accent';
        }
        if ($h < 260) {
            return 'water';
        }
        return null;
    }

    /** Farbe einer Rolle: light, dark oder mid (Mitte aus beiden). */
    private function Color(string $token, string $mode): int
    {
        $l = $this->ReadPropertyInteger($this->Prop($token, false)) & 0xFFFFFF;
        $d = $this->ReadPropertyInteger($this->Prop($token, true)) & 0xFFFFFF;
        if ($mode === 'light') {
            return $l;
        }
        if ($mode === 'dark') {
            return $d;
        }
        $m = 0;
        foreach ([16, 8, 0] as $s) {
            $m |= ((int) round(((($l >> $s) & 255) + (($d >> $s) & 255)) / 2)) << $s;
        }
        return $m;
    }

    /** Schrift auf einer Fläche: Weiß oder Text (hell) – die mit dem höheren Kontrast. */
    private function OnColor(int $bg): int
    {
        $dark = $this->Color('fg', 'light');
        return $this->Contrast($bg, 0xFFFFFF) >= $this->Contrast($bg, $dark) ? 0xFFFFFF : $dark;
    }

    private function Contrast(int $a, int $b): float
    {
        $la = $this->Luminance($a);
        $lb = $this->Luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function Luminance(int $c): float
    {
        $f = function (int $v): float {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $f(($c >> 16) & 255) + 0.7152 * $f(($c >> 8) & 255) + 0.0722 * $f($c & 255);
    }

    private function Argb(int $rgb): int
    {
        return 0xFF000000 | ($rgb & 0xFFFFFF);
    }

    private function Hex(int $rgb): string
    {
        return sprintf('#%06X', $rgb & 0xFFFFFF);
    }

    private function Prop(string $token, bool $dark): string
    {
        return ucfirst($token) . ($dark ? 'Dark' : 'Light');
    }

    private function CurrentColors(): array
    {
        $out = [];
        foreach (array_keys(self::TOKENS) as $token) {
            $out[$token] = [$this->Color($token, 'light'), $this->Color($token, 'dark')];
        }
        return $out;
    }

    private function CurrentThemeName(): string
    {
        $cur = $this->CurrentColors();
        foreach ($this->AllThemes() as $name => $theme) {
            if ($theme == $cur) {
                return $name;
            }
        }
        return '';
    }

    private function SavedThemes(): array
    {
        $t = json_decode($this->ReadAttributeString('Themes'), true);
        return is_array($t) ? $t : [];
    }

    private function AllThemes(): array
    {
        return self::THEMES + $this->SavedThemes();
    }

    private function PrimaryID(): int
    {
        $ids = IPS_GetInstanceListByModuleID(self::MODULE_ID);
        sort($ids);
        return $ids[0] ?? $this->InstanceID;
    }

    private function IsPrimary(): bool
    {
        return $this->PrimaryID() === $this->InstanceID;
    }

    private function ExcludeRoots(): array
    {
        $rows = json_decode($this->ReadPropertyString('Exclude'), true) ?: [];
        return array_values(array_filter(array_map(fn ($r) => (int) ($r['ObjectID'] ?? 0), $rows), fn ($id) => $id > 0));
    }

    private function IsExcluded(int $id, array $roots): bool
    {
        if (count($roots) === 0) {
            return false;
        }
        for ($i = $id, $n = 0; $i > 0 && $n < 64; $i = IPS_GetParent($i), $n++) {
            if (in_array($i, $roots, true)) {
                return true;
            }
        }
        return false;
    }
}
