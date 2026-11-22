<?php
declare(strict_types=1);

/** Prognoseadapter: normalisierte PV-Slots im JSON; keine API-Requests. */
final class LunaChargePlanner
{
    public static function parseForecast(string $json, int $now): array
    {
        $raw = json_decode($json, true);
        if (!is_array($raw)) return [];
        $raw = $raw['slots'] ?? $raw;
        if (!is_array($raw)) return [];
        $slots = [];
        foreach (array_slice($raw, 0, 500) as $slot) {
            if (!is_array($slot)) continue;
            $tsValue = $slot['ts'] ?? ($slot['time'] ?? null);
            $ts = is_numeric($tsValue) ? (int)$tsValue : (is_string($tsValue) ? (int)strtotime($tsValue) : 0);
            $pv = $slot['pvW'] ?? ($slot['powerW'] ?? null);
            if ($ts <= $now - 7200 || $ts > $now + 7 * 86400 || !is_numeric($pv)) continue;
            $slots[$ts] = ['ts' => $ts, 'pvW' => max(0, min(40000, (float)$pv))];
        }
        ksort($slots);
        return array_values(array_slice($slots, 0, 168));
    }

    public static function expectedAcKwh(array $slots, int $now, int $deadline, float $baseLoadW, float $chargerKw): ?float
    {
        if ($deadline <= $now || count($slots) < 2) return null;
        $kwh = 0.0;
        $coveredSeconds = 0;
        for ($i = 0; $i + 1 < count($slots); ++$i) {
            $a = max($now, (int)$slots[$i]['ts']);
            $b = min($deadline, (int)$slots[$i+1]['ts']);
            $seconds = max(0, min(3600, $b - $a));
            if (!$seconds) continue;
            $usableW = max(0, (float)$slots[$i]['pvW'] - $baseLoadW);
            $kwh += min($usableW / 1000, $chargerKw) * $seconds / 3600;
            $coveredSeconds += $seconds;
        }
        $horizon = max(1, $deadline - $now);
        if ($coveredSeconds / $horizon < 0.6) return null;
        return round($kwh, 2);
    }
}
