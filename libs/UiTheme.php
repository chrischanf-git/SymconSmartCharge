<?php
declare(strict_types=1);

/** Presentation settings only. No network or Wallbox calls. */
final class LunaUiTheme
{
    public static function defaults(): array
    {
        return [
            'UiAccentColor' => '#47E19C',
            'UiBgColor' => '#0B100F',
            'UiSurfaceColor' => '#151D1A',
            'UiTextColor' => '#EFF7F1',
            'UiMutedColor' => '#91A59B',
            'UiBorderColor' => '#304137',
            'UiRadiusPx' => 20,
            'UiBorderPx' => 1,
            'UiGapPx' => 13,
            'UiPhotoHeightPx' => 220,
            'UiPhotoFit' => 'cover',
            'UiPhotoPosition' => 'center',
            'UiShowPhoto' => true,
            'UiPhotoMediaID' => 0,
        ];
    }

    public static function color($input, string $fallback): string
    {
        $s = trim((string)$input);
        if (preg_match('/^#?[a-fA-F0-9]{6}$/D', $s)) return '#' . strtoupper(ltrim($s, '#'));
        if (preg_match('/^0x[a-fA-F0-9]{6}$/iD', $s)) return '#' . strtoupper(substr($s, 2));
        return $fallback;
    }

    public static function css(array $cfg): string
    {
        $d = self::defaults();
        $colors = [];
        foreach (['Accent', 'Bg', 'Surface', 'Text', 'Muted', 'Border'] as $name) {
            $key = 'Ui' . $name . 'Color';
            $colors[$name] = self::color($cfg[$key] ?? '', $d[$key]);
        }
        $radius = max(0, min(38, (int)($cfg['UiRadiusPx'] ?? 20)));
        $border = max(0, min(4, (int)($cfg['UiBorderPx'] ?? 1)));
        $gap = max(6, min(30, (int)($cfg['UiGapPx'] ?? 13)));
        $height = max(115, min(400, (int)($cfg['UiPhotoHeightPx'] ?? 220)));
        $fit = in_array($cfg['UiPhotoFit'] ?? '', ['cover','contain'], true) ? $cfg['UiPhotoFit'] : 'cover';
        $pos = in_array($cfg['UiPhotoPosition'] ?? '', ['center','top','bottom'], true) ? $cfg['UiPhotoPosition'] : 'center';
        $show = !empty($cfg['UiShowPhoto']) ? '' : '.photo{display:none!important}.car-card{grid-template-columns:1fr!important}';
        return ':root{--bg:' . $colors['Bg'] . ';--surface:' . $colors['Surface'] .
            ';--surface2:' . $colors['Surface'] . ';--text:' . $colors['Text'] .
            ';--muted:' . $colors['Muted'] . ';--line:' . $colors['Border'] .
            ';--accent:' . $colors['Accent'] . ';--mint:' . $colors['Accent'] .
            ';--accent-ink:' . self::contrastInk($colors['Accent']) .
            ';--radius:' . $radius . 'px;--border-size:' . $border . 'px;--card-gap:' . $gap .
            'px;--photo-height:' . $height . 'px;--photo-fit:' . $fit .
            ';--photo-pos:' . $pos . ';}' . $show;
    }

    private static function contrastInk(string $color): string
    {
        $r = hexdec(substr($color, 1, 2));
        $g = hexdec(substr($color, 3, 2));
        $b = hexdec(substr($color, 5, 2));
        // Background for primary controls must remain readable even after color adjustments.
        return (0.2126*$r + 0.7152*$g + 0.0722*$b) >= 155 ? '#0B1510' : '#FFFFFF';
    }

    public static function photoUri(array $cfg, string $defaultFile): string
    {
        $id = (int)($cfg['UiPhotoMediaID'] ?? 0);
        if ($id > 0 && function_exists('IPS_MediaExists') && IPS_MediaExists($id)) {
            try {
                $metadata = IPS_GetMedia($id);
                // Only local, cached image media up to 2 MB, no SVG or remote URLs.
                if ((int)($metadata['MediaType'] ?? -1) === 1 &&
                    (int)($metadata['MediaSize'] ?? PHP_INT_MAX) <= 2000000) {
                    $b64 = IPS_GetMediaContent($id);
                    if (is_string($b64) && strlen($b64) <= 2800000) {
                        $data = base64_decode($b64, true);
                        if (is_string($data) && strlen($data) <= 2000000) {
                            $mime = self::imageMime($data);
                            if ($mime !== null) return 'data:' . $mime . ';base64,' . base64_encode($data);
                        }
                    }
                }
            } catch (Throwable $e) { /* fallback is bundled photograph */ }
        }
        $bytes = @file_get_contents($defaultFile);
        if (!is_string($bytes)) return '';
        $mime = self::imageMime($bytes);
        return $mime === null ? '' : 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private static function imageMime(string $bytes): ?string
    {
        if (substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") return 'image/png';
        if (substr($bytes, 0, 3) === "\xff\xd8\xff") return 'image/jpeg';
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') return 'image/webp';
        return null;
    }

    public static function previewUri(array $cfg): string
    {
        $d = self::defaults();
        $bg = self::color($cfg['UiBgColor'] ?? '', $d['UiBgColor']);
        $surface = self::color($cfg['UiSurfaceColor'] ?? '', $d['UiSurfaceColor']);
        $accent = self::color($cfg['UiAccentColor'] ?? '', $d['UiAccentColor']);
        $line = self::color($cfg['UiBorderColor'] ?? '', $d['UiBorderColor']);
        $text = self::color($cfg['UiTextColor'] ?? '', $d['UiTextColor']);
        $radius = max(0, min(38, (int)($cfg['UiRadiusPx'] ?? 20)));
        $stroke = max(0, min(4, (int)($cfg['UiBorderPx'] ?? 1)));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="430" height="166" viewBox="0 0 430 166">' .
            '<rect x="0" y="0" width="430" height="166" rx="12" fill="' . $bg . '"/>' .
            '<rect x="14" y="14" width="402" height="138" rx="' . $radius . '" fill="' . $surface . '" stroke="' . $line . '" stroke-width="' . $stroke . '"/>' .
            '<circle cx="55" cy="62" r="27" fill="none" stroke="' . $accent . '" stroke-width="7"/>' .
            '<text x="95" y="52" fill="' . $text . '" font-size="16" font-family="Arial" font-weight="bold">Luna SmartCharge</text>' .
            '<text x="95" y="75" fill="' . $text . '" font-size="11" font-family="Arial">Designvorschau nach Anwenden</text>' .
            '<rect x="27" y="108" width="180" height="30" rx="8" fill="' . $accent . '"/>' .
            '<text x="63" y="128" fill="' . self::contrastInk($accent) . '" font-size="12" font-family="Arial" font-weight="bold">Smart laden</text>' .
            '<rect x="222" y="108" width="180" height="30" rx="8" fill="none" stroke="' . $line . '" stroke-width="' . max(1,$stroke) . '"/>' .
            '<text x="262" y="128" fill="' . $text . '" font-size="12" font-family="Arial">Jetzt laden</text>' .
            '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function formPanel(string $title = 'Optik und Design'): array
    {
        return ['type' => 'ExpansionPanel', 'caption' => $title, 'items' => [
            ['type' => 'Label', 'caption' => 'Farben, Rundungen und Bild getrennt fuer diese Ansicht anpassen. Aenderungen werden nach Uebernehmen und Neuladen sichtbar.'],
            ['type' => 'SelectColor', 'name' => 'UiAccentColor', 'caption' => 'Akzentfarbe', 'allowTransparent' => false],
            ['type' => 'SelectColor', 'name' => 'UiBgColor', 'caption' => 'Hintergrundfarbe', 'allowTransparent' => false],
            ['type' => 'SelectColor', 'name' => 'UiSurfaceColor', 'caption' => 'Kartenfarbe', 'allowTransparent' => false],
            ['type' => 'SelectColor', 'name' => 'UiBorderColor', 'caption' => 'Rahmenfarbe', 'allowTransparent' => false],
            ['type' => 'SelectColor', 'name' => 'UiTextColor', 'caption' => 'Textfarbe', 'allowTransparent' => false],
            ['type' => 'SelectColor', 'name' => 'UiMutedColor', 'caption' => 'Sekundaere Schriftfarbe', 'allowTransparent' => false],
            ['type' => 'NumberSpinner', 'name' => 'UiRadiusPx', 'caption' => 'Rundung der Karten', 'minimum' => 0, 'maximum' => 38, 'suffix' => ' px'],
            ['type' => 'NumberSpinner', 'name' => 'UiBorderPx', 'caption' => 'Rahmenstaerke', 'minimum' => 0, 'maximum' => 4, 'suffix' => ' px'],
            ['type' => 'NumberSpinner', 'name' => 'UiGapPx', 'caption' => 'Abstand zwischen Karten', 'minimum' => 6, 'maximum' => 30, 'suffix' => ' px'],
            ['type' => 'CheckBox', 'name' => 'UiShowPhoto', 'caption' => 'Fahrzeugfoto anzeigen'],
            ['type' => 'SelectMedia', 'name' => 'UiPhotoMediaID', 'caption' => 'Eigenes Fahrzeugfoto (Bildmedium, optional)'],
            ['type' => 'NumberSpinner', 'name' => 'UiPhotoHeightPx', 'caption' => 'Bildhoehe', 'minimum' => 115, 'maximum' => 400, 'suffix' => ' px'],
            ['type' => 'Select', 'name' => 'UiPhotoFit', 'caption' => 'Bildformat', 'options' => [
                ['caption' => 'Bild fuellt den Bereich (Zuschnitt)', 'value' => 'cover'],
                ['caption' => 'Ganzes Bild zeigen', 'value' => 'contain'],
            ]],
            ['type' => 'Select', 'name' => 'UiPhotoPosition', 'caption' => 'Bildausschnitt', 'options' => [
                ['caption' => 'Mitte', 'value' => 'center'], ['caption' => 'Oben', 'value' => 'top'], ['caption' => 'Unten', 'value' => 'bottom'],
            ]],
        ]];
    }
}
