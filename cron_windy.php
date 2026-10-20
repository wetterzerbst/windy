<?php
/**
 * UPDATE: 21.10.2026 auf korrekte v2-Api von Windy. Vorher v1.
 * Wetternetzwerk Zerbst (ZEWN) - Windy API v2* (JSON POST via cURL)
 * Optimiert für direkte Nutzung imperialer Einheiten (Ecowitt CSV Format)
 * Inkl. Regen, Solar, UV, Taupunkt und lokaler Zeitzone für Logs
 * Ermöglicht das übertragen mehrerer Stationen an Windy
 * Programmiert von: wetter-zerbst.org | wetter.zerbst@gmail.com
 * ===
 * UPDATE: October 21, 2026 updated to the correct Windy v2 API. Previously v1.
 * Zerbst Weather Network (ZEWN) - Windy API v2* (JSON POST via cURL)
 * Optimized for direct use of imperial units (Ecowitt CSV format)
 * Includes rain, solar, UV, dew point, and local time zone for logs
 * Enables the transmission of data from multiple stations to Windy
 * Programmed by: wetter-zerbst.org | wetter.zerbst@gmail.com
 */

// --- NEU: 21.10.2026 auf korrekte v2-Api von Windy. Vorher v1. ---
// --- NEW: October 21, 2026 updated to the correct Windy v2 API. Previously v1 ---

date_default_timezone_set('Europe/Berlin');

if (!isset($dir)) {
    $dir = __DIR__;
}

$cache_dir = $dir . "/cache/";
if (!file_exists($cache_dir)) { 
    mkdir($cache_dir, 0777, true); 
    chmod($cache_dir, 0777); 
}

// --- KONFIGURATION DER STATIONEN (V2 API + Sendesperre) ---
$stations = [
    'STATION1' => [
        'active'       => false,
        'path'         => $dir . '/path/station1/live.csv',
        'stationId'    => '1fXXXXXXX',
        'password'     => 'fch485f6hg537g593487g48gh4873g847gh08745hg087754hg4gh548hhg487gh',
        'min_interval' => 300 // Sendesperre in Sekunden (5 Minuten)
    ],
    'STATION2' => [
        'active'       => false, 
        'path'         => $dir . '/path/station2/live.csv',
        'stationId'    => '2fXXXXXXX',
        'password'     => 'fch485f6hg537g593487g48gh4873g847gh08745hg087754hg4gh548hhg487gh',
        'min_interval' => 300
    ],
    'STATION1' => [
        'active'       => true,
        'path'         => $dir . '/path/station3/live.csv',
        'stationId'    => '1fXXXXXXX',
        'password'     => 'fch485f6hg537g593487g48gh4873g847gh08745hg087754hg4gh548hhg487gh',
        'min_interval' => 300
    ]
];

// --- HILFSFUNKTIONEN ---

// Für den Tagesniederschlag (mprecip), da die API hier standardmäßig mm erwartet
function inToMm($in) { 
    return ($in !== null && $in !== '') ? round((float)$in * 25.4, 1) : null; 
}

// Berechnet den Taupunkt in Celsius anhand der Magnus-Formel
function calculateDewPointC($tempF, $humidity) {
    if ($tempF === null || $tempF === '' || $humidity === null || $humidity === '') {
        return null;
    }
    
    $tempC = ($tempF - 32) * 5 / 9;
    $rh = (float)$humidity;
    
    if ($rh <= 0) return null; 

    $a = 17.271;
    $b = 237.7;
    
    $alpha = (($a * $tempC) / ($b + $tempC)) + log($rh / 100);
    $dewPointC = ($b * $alpha) / ($a - $alpha);
    
    return round($dewPointC, 1);
}

// --- HAUPTPROZESS ---
echo "[" . date('Y-m-d H:i:s') . "] Starte Datenuebertragung an Windy V2...\n";

foreach ($stations as $name => $config) {
    if (!$config['active']) {
        echo "-> Station [$name]: Deaktiviert.\n";
        continue;
    }

    // --- SENDESPERRE / CACHE PRÜFEN ---
    $cache_file = $cache_dir . "last_windy_" . md5($name) . ".txt";
    if (isset($config['min_interval'])) {
        $last_send = file_exists($cache_file) ? (int)file_get_contents($cache_file) : 0;
        if ((time() - $last_send) < $config['min_interval']) {
            $remaining = $config['min_interval'] - (time() - $last_send);
            echo "-> Station [$name]: Sendesperre aktiv (noch ca. $remaining Sek. warten).\n";
            continue; 
        }
    }

    if (!file_exists($config['path'])) {
        echo "-> Station [$name]: FEHLER - Datei nicht gefunden.\n";
        continue;
    }

    $lines = file($config['path']);
    if (count($lines) < 2) continue;

    $header = str_getcsv(trim($lines[0]), ';');
    $lastLine = trim(end($lines));
    if (empty($lastLine)) {
        $lastLine = trim($lines[count($lines) - 2]);
    }
    
    $data = str_getcsv($lastLine, ';');
    if (count($header) !== count($data)) continue;

    $row = array_combine($header, $data);

    // Datum aus CSV auslesen (erwartet YYYY-MM-DD HH:MM:SS gemäss Windy V2 Vorgabe)
    $dateUtcStr = $row['dateutc'] ?? gmdate("Y-m-d H:i:s");

    // Zeitstempel der Messung für das Logfile in Europe/Berlin umwandeln
    try {
        $dt = new DateTime($dateUtcStr, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        $logTime = $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $logTime = $dateUtcStr; // Fallback
    }

    // Query-Parameter für den V2 GET-Request (Authentifizierung + Messwerte)
    $queryParams = [
        'station'        => (string)$config['stationId'],
        'PASSWORD'       => (string)$config['password'],
        'dateutc'        => $dateUtcStr, 
        'tempf'          => isset($row['tempf']) && $row['tempf'] !== '' ? (float)$row['tempf'] : null,
        'humidity'       => isset($row['humidity']) && $row['humidity'] !== '' ? (int)$row['humidity'] : null,
        'dewpoint'       => calculateDewPointC($row['tempf'] ?? null, $row['humidity'] ?? null),
        'baromin'        => isset($row['baromrelin']) && $row['baromrelin'] !== '' ? (float)$row['baromrelin'] : null,
        'windspeedmph'   => isset($row['windspeedmph']) && $row['windspeedmph'] !== '' ? (float)$row['windspeedmph'] : null,
        'windgustmph'    => isset($row['windgustmph']) && $row['windgustmph'] !== '' ? (float)$row['windgustmph'] : null,
        'winddir'        => isset($row['winddir']) && $row['winddir'] !== '' ? (int)$row['winddir'] : null,
        'hourlyrainin'   => isset($row['hourlyrainin']) && $row['hourlyrainin'] !== '' ? (float)$row['hourlyrainin'] : null,
        'mprecip'        => isset($row['dailyrainin']) && $row['dailyrainin'] !== '' ? inToMm($row['dailyrainin']) : null, 
        'solarradiation' => isset($row['solarradiation']) && $row['solarradiation'] !== '' ? (float)$row['solarradiation'] : null,
        'uv'             => isset($row['uv']) && $row['uv'] !== '' ? (float)$row['uv'] : null
    ];

    // Null-Werte sauber aus den Parametern filtern
    $queryParams = array_filter($queryParams, function($value) {
        return $value !== null;
    });

    // Korrekter V2-Endpunkt als GET-Request mit Query-String
    $url = "https://stations.windy.com/api/v2/observation/update?" . http_build_query($queryParams);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Auswertung mit robuster Cache-Behandlung (inkl. Rechte-Fix & 429 Rate-Limit-Sicherung)
    if ($curlError) {
        echo "-> Station [$name]: KRITISCHER FEHLER (cURL) - " . $curlError . "\n";
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        file_put_contents($cache_file, time());
        if (file_exists($cache_file)) chmod($cache_file, 0777); // Berechtigung für Webserver & Cron sichern
        echo "-> Station [$name]: Daten erfolgreich uebertragen (V2 API). (HTTP $httpCode, Zeit: $logTime)\n";
    } elseif ($httpCode == 429) {
        file_put_contents($cache_file, time()); // Sendesperre bei Rate-Limit sofort aktivieren
        if (file_exists($cache_file)) chmod($cache_file, 0777);
        echo "-> Station [$name]: V2 API RATE LIMIT (429) - Sendesperre greift. Antwort: " . trim($response) . "\n";
    } else {
        echo "-> Station [$name]: V2 API FEHLER - HTTP Code: $httpCode | Antwort: " . trim($response) . "\n";
        echo "   Gesendete URL: $url\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Prozess beendet.\n\n";
?>
