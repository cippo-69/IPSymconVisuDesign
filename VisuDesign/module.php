<?php

declare(strict_types=1);

/**
 * Visu Design: zentrale Farben für alle HTML-Kacheln (Farbkonzept W5x).
 *
 * Die Kachel-Module holen sich die Farben hier ab (W5D_GetCSS / W5D_GetColors)
 * und haben selbst keine Farbeinstellungen mehr. Eine Instanz genügt.
 */
class VisuDesign extends IPSModule
{
    public const MODULE_ID = '{018EEC85-8FFC-4E1F-A966-702C4BBDC2E8}';

    /** Token => [Bezeichnung, hell, dunkel] (Farbkonzept W5x 1.0) */
    private const TOKENS = [
        'fg'     => ['Text',                          0x1E2224, 0xF1F0EB],
        'muted'  => ['Nebentext',                     0x6B7175, 0xA9AEB1],
        'accent' => ['Akzent (Bedienung, aktiv)',     0x2F6B4F, 0x74B892],
        'on'     => ['Schrift auf Akzent- und Alarmfläche', 0xFFFFFF, 0x1E2224],
        'warm'   => ['Wärme (Heizen)',                0x8E2B26, 0xC9806C],
        'water'  => ['Wasser, Kühle, Absenkung',      0x2C6A86, 0x7DB6CF],
        'sun'    => ['Sonne, PV',                     0x7A6000, 0xF2CF63],
        'warn'   => ['Warnung',                       0x9A5200, 0xEE9A45],
        'alarm'  => ['Alarm (nur als Fläche)',        0xC8321F, 0xFF5A47]
    ];

    public function Create()
    {
        parent::Create();
        foreach (self::TOKENS as $token => [$caption, $light, $dark]) {
            $this->RegisterPropertyInteger($this->Prop($token, false), $light);
            $this->RegisterPropertyInteger($this->Prop($token, true), $dark);
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $ids = IPS_GetInstanceListByModuleID(self::MODULE_ID);
        sort($ids);
        if (count($ids) > 1 && $ids[0] !== $this->InstanceID) {
            $this->SetSummary('inaktiv – es gilt Instanz ' . $ids[0]);
            $this->SetStatus(104);
            return;
        }
        $this->SetSummary('Akzent ' . $this->Hex('accent', false) . ' / ' . $this->Hex('accent', true));
        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $elements = [
            ['type' => 'Label', 'caption' => 'Zentrale Farben für alle W5x-Kacheln. Linke Spalte: heller Modus, rechte Spalte: Dark Mode. Änderungen wirken nach dem Neuladen der Visu.']
        ];
        foreach (self::TOKENS as $token => [$caption]) {
            $elements[] = ['type' => 'RowLayout', 'items' => [
                ['type' => 'SelectColor', 'name' => $this->Prop($token, false), 'caption' => $caption . ' (hell)', 'width' => '300px'],
                ['type' => 'SelectColor', 'name' => $this->Prop($token, true), 'caption' => $caption . ' (dunkel)', 'width' => '300px']
            ]];
        }
        return json_encode([
            'elements' => $elements,
            'actions'  => [
                ['type' => 'Button', 'caption' => 'Farbkonzept W5x wiederherstellen', 'onClick' => 'W5D_ResetDefaults($id);', 'confirm' => 'Alle Farben auf das Farbkonzept W5x zurücksetzen?'],
                ['type' => 'Label', 'caption' => 'CSS-Variablen: --w5-' . implode(', --w5-', array_keys(self::TOKENS))]
            ],
            'status'   => [
                ['code' => 104, 'icon' => 'inactive', 'caption' => 'Weitere Instanz – es gilt die Instanz mit der kleinsten ID']
            ]
        ]);
    }

    /** CSS mit den Variablen --w5-*; hell auf :root, dunkel auf body.dark (Dark-Mode-Klasse der Kacheln). */
    public function GetCSS(): string
    {
        $light = $dark = '';
        foreach (array_keys(self::TOKENS) as $token) {
            $light .= '--w5-' . $token . ':' . $this->Hex($token, false) . ';';
            $dark .= '--w5-' . $token . ':' . $this->Hex($token, true) . ';';
        }
        return ':root{' . $light . '}body.dark{' . $dark . '}';
    }

    /** JSON {"light":{token:"#RRGGBB"},"dark":{...}} für Module ohne Dark-Mode-Erkennung (z. B. HTML-Box). */
    public function GetColors(): string
    {
        $out = ['light' => [], 'dark' => []];
        foreach (array_keys(self::TOKENS) as $token) {
            $out['light'][$token] = $this->Hex($token, false);
            $out['dark'][$token] = $this->Hex($token, true);
        }
        return json_encode($out);
    }

    public function ResetDefaults(): void
    {
        foreach (self::TOKENS as $token => [$caption, $light, $dark]) {
            IPS_SetProperty($this->InstanceID, $this->Prop($token, false), $light);
            IPS_SetProperty($this->InstanceID, $this->Prop($token, true), $dark);
        }
        IPS_ApplyChanges($this->InstanceID);
    }

    private function Prop(string $token, bool $dark): string
    {
        return ucfirst($token) . ($dark ? 'Dark' : 'Light');
    }

    private function Hex(string $token, bool $dark): string
    {
        return sprintf('#%06X', $this->ReadPropertyInteger($this->Prop($token, $dark)) & 0xFFFFFF);
    }
}
