<?php

function getRaceFiles($dir) {
    $files = glob("$dir/*.csv");
    $races = [];
    foreach ($files as $file) {
        $name = basename($file, ".csv");
        $name = str_replace("-sprint", " (Sprint)", $name);
        $name = str_replace("-race", "", $name);
        $races[] = [
            'name' => ucwords(str_replace("_", " ", $name)),
            'path' => $file
        ];
    }
    return $races;
}

function compileDriverStats($race_files) {
    $stats = [];
    foreach ($race_files as $race) {
        $rows = array_map(fn($line) => str_getcsv($line, ",", '"', "\\"), file($race['path']));
        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        foreach (array_slice($rows, 1) as $row) {
            if (count($row) < count($headers)) continue;
            $data = array_combine($headers, $row);
            if (!isset($data['driver'])) continue;

            $driver = $data['driver'];
            $constructor = $data['team'] ?? 'Unknown';
            $points = isset($data['points']) ? (int)$data['points'] : 0;

            if (!isset($stats[$driver])) {
                $stats[$driver] = [
                    'points' => 0,
                    'constructor' => $constructor
                ];
            }
            $stats[$driver]['points'] += $points;
        }
    }
    uasort($stats, fn($a, $b) => $b['points'] <=> $a['points']);
    return $stats;
}

function compileConstructorStats($driver_stats) {
    $constructors = [];
    foreach ($driver_stats as $data) {
        $constructor = $data['constructor'];
        $points = $data['points'];
        if (!isset($constructors[$constructor])) {
            $constructors[$constructor] = 0;
        }
        $constructors[$constructor] += $points;
    }
    arsort($constructors);
    return $constructors;
}

function computeLapStats($file) {
    $rows = array_map(fn($line) => str_getcsv($line, ",", '"', "\\"), file($file));
    $headers = array_map('strtolower', array_map('trim', $rows[0]));
    $lap_data = [];

    foreach (array_slice($rows, 1) as $row) {
        if (count($row) < count($headers)) continue;
        $data = array_combine($headers, $row);
        if (!isset($data['driver'])) continue;

        $driver = $data['driver'];
        $status = strtolower(trim($data['status'] ?? ''));
        $is_dnf = $status !== 'finished';

        $lap_data[$driver] = [
            'best' => $is_dnf ? formatDNF() : formatBestWorst($data['fastestlap'] ?? null, $data['fastestnum'] ?? null),
            'bestnum' => $is_dnf ? formatDNF() : $data['fastestnum'],
            'worst' => $is_dnf ? formatDNF() : formatBestWorst($data['slowestlap'] ?? null, $data['slowestnum'] ?? null),
            'worstnum' => $is_dnf ? formatDNF() : $data['slowestnum'],
            'average' => $is_dnf ? formatDNF() : formatLapTime($data['avglap'] ?? null),
            'finalTime' => $is_dnf ? formatDNF() : formatLapTime($data['finaltime'] ?? null),
            'gap' => $is_dnf ? formatDNF() : $data['gap']
        ];
    }

    return $lap_data;
}

function formatLapTime($time) {
    return $time && trim($time) !== '' ? $time : formatDNF();
}

function formatBestWorst($time,$num) {
    $newTime = "{$time} ({$num})";
    return $newTime ? $newTime : formatDNF();
}

function formatDNF() {
    return '<span class="text-danger fw-bold">DNF</span>';
}


function formatSeconds($seconds) {
    $seconds = (float)$seconds;
    $min = floor($seconds / 60);
    $sec = $seconds - $min * 60;
    return sprintf("%d:%05.3f", $min, $sec);
}


function lapTimeToSeconds($lap) {
    if (!$lap || !is_string($lap)) return 0;
    if (strpos($lap, ':') !== false) {
        [$min, $sec] = explode(':', $lap);
        return (int)$min * 60 + (float)$sec;
    }
    return (float)$lap;
}

function secondsToLapTime($seconds) {
    $min = floor($seconds / 60);
    $sec = $seconds - $min * 60;
    return sprintf("%d:%05.2f", $min, $sec);
}

function getPodiumData($csv_path, $driver_info) {
    $rows = array_map(fn($line) => str_getcsv($line, ",", '"', "\\"), file($csv_path));
    $headers = array_map('strtolower', array_map('trim', $rows[0]));
    $data_rows = array_slice($rows, 1);

    $podium = [];
    $top_driver_code = null;
    foreach ($data_rows as $row) {

        if (count($row) !== count($headers)) continue;
        $data = array_combine($headers, $row);
        $position = (int)($data['position'] ?? 0);
        if ($position >= 1 && $position <= 3) {
            $code = strtoupper($data['driver']);
            $info = $driver_info[$code] ?? [
                'name' => $data['driver'],
                'country' => 'xx',
                'team' => 'Unknown'
            ];
            $info['headshot'] = "assets/drivers/{$code}.avif";
            $info['position'] = $position;
            $info['final_lap'] = $data['finaltime'] ?? 'N/A';
            $info['gap'] = $data['gap'] ?? 'N/A';
            $info['code'] = $code;
            $podium[$position] = $info;
        }
    }
    ksort($podium);

    $first_time = lapTimeToSeconds($podium[1]['final_lap'] ?? 0);
    foreach ([2, 3] as $pos) {
        if (isset($podium[$pos])) {
            $driver_time = lapTimeToSeconds($podium[$pos]['final_lap'] ?? 0);
            #$gap = $driver_time - $first_time;
            $gap = $podium[$pos]['gap'];
            $podium[$pos]['gap'] = sprintf("+%.3f", $gap);
        }
    }
    return $podium;
}
