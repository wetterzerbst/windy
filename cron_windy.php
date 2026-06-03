<?php
/**
 * Wetternetzwerk Zerbst (ZEWN) - Windy API v2 (JSON POST via cURL)
 * Optimiert für direkte Nutzung imperialer Einheiten (Ecowitt CSV Format)
 * Inkl. Regen, Solar, UV, Taupunkt und lokaler Zeitzone für Logs
 * Ermöglicht das übertragen mehrerer Stationen an Windy
 * ===
 * Zerbst Weather Network (ZEWN) - Windy API v2 (JSON POST via cURL)
 * Optimized for direct use of imperial units (Ecowitt CSV format)
 * Includes rain, solar, UV, dew point, and local time zone for logs
 * Enables the transmission of data from multiple stations to Windy
 */

// --- NEU: Standard-Zeitzone für alle PHP-Datumsfunktionen auf Berlin setzen ---
// --- NEW: Set the default time zone for all PHP date functions to Berlin ---
date_default_timezone_set('Europe/Berlin');

if (!isset($dir)) {
    $dir = __DIR__;
}

// --- KONFIGURATION DER STATIONEN ---
// --- STATION CONFIGURATION ---
$stations = [
    'Timbuktu' => [
        'active'    => true, // true = Datenübertragung eingeschaltet | false = ausgeschaltet | true = Data transfer enabled | false = disabled
        'path'      => $dir . '/pfad/zur/datei/live.csv', // Ecowitt Datendatei hier eintragen | Enter the Ecowitt data file here
        'apiKey'    => '4565ch8fgm8ghc8nv46hgvn89rh49n8g5hcf8e47gn895hchncg84h5cg8cnghncg', // API-Key hier eintragen | Enter your API key here
        'stationId' => '0'
    ],
    'Washington' => [
        'active'    => false, // true = Datenübertragung eingeschaltet | false = ausgeschaltet | true = Data transfer enabled | false = disabled
        'path'      => $dir . '/pfad/zur/datei/live.csv', // Ecowitt Datendatei hier eintragen | Enter the Ecowitt data file here
        apiKey'    => '4565ch8fgm8ghc8nv46hgvn89rh49n8g5hcf8e47gn895hchncg84h5cg8cnghncg', // API-Key hier eintragen | Enter your API key here
        'stationId' => '1'
    ],
    'Moskau' => [
        'active'    => false, // true = Datenübertragung eingeschaltet | false = ausgeschaltet | true = Data transfer enabled | false = disabled
        'path'      => $dir . '/pfad/zur/datei/live.csv', // Ecowitt Datendatei hier eintragen | Enter the Ecowitt data file here
        apiKey'    => '4565ch8fgm8ghc8nv46hgvn89rh49n8g5hcf8e47gn895hchncg84h5cg8cnghncg', // API-Key hier eintragen | Enter your API key here
        'stationId' => '2'
    ]
];

// --- HILFSFUNKTIONEN ---
// --- HELPER FUNCTIONS ---

// Für den Tagesniederschlag (mprecip), da die API hier standardmäßig mm erwartet
// For daily precipitation (mprecip), since the API expects mm by default
function inToMm($in) { 
    return ($in !== null && $in !== '') ? round((float)$in * 25.4, 1) : null; 
}

// Berechnet den Taupunkt in Celsius anhand der Magnus-Formel
// Calculates the dew point in Celsius using the Magnus formula
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
// --- MAIN PROCESS ---
// Diese Ausgabe nutzt nun automatisch 'Europe/Berlin' durch die Einstellung oben
// This version now automatically uses 'Europe/Berlin' based on the setting above
echo "[" . date('Y-m-d H:i:s') . "] Starte Datenuebertragung an Windy... | Start data transfer to Windy...\n";

foreach ($stations as $name => $config) {
    if (!$config['active']) {
        echo "-> Station [$name]: Deaktiviert. | Disabled.\n";
        continue;
    }

    if (!file_exists($config['path'])) {
        echo "-> Station [$name]: FEHLER - Datei nicht gefunden. | ERROR - File not found.\n";
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

    // Datum aus CSV auslesen
    // Read date from CSV
    $dateUtcStr = $row['dateutc'] ?? '';
    
    // Formatieren in ISO 8601 für Windy (z.B. "2026-05-24T04:45:44Z" -> Bleibt UTC!)
    // Format in ISO 8601 for Windy (e.g., "2026-05-24T04:45:44Z" -> Remains in UTC!)
    $dateIso = str_replace(' ', 'T', $dateUtcStr) . 'Z';

    // --- NEU: Zeitstempel der Messung für das Logfile in Europe/Berlin umwandeln ---
    // --- NEW: Convert the measurement timestamp for the log file to Europe/Berlin ---
    try {
        $dt = new DateTime($dateUtcStr, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        $logTime = $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $logTime = $dateUtcStr; // Fallback
    }

    // 1. JSON Payload für die v2 API zusammenbauen
    // 1. Assemble the JSON payload for the v2 API
    $payload = [
        'observations' => [
            [
                'station'        => (int)$config['stationId'],
                'dateutc'        => $dateIso, // Windy bekommt weiterhin brav UTC | Windy continues to display UTC properly
                
                // Temperatur, Luftfeuchtigkeit & Taupunkt
                // Temperature, Humidity & Dew Point
                'tempf'          => isset($row['tempf']) && $row['tempf'] !== '' ? (float)$row['tempf'] : null,
                'humidity'       => isset($row['humidity']) && $row['humidity'] !== '' ? (int)$row['humidity'] : null,
                'dewpoint'       => calculateDewPointC($row['tempf'] ?? null, $row['humidity'] ?? null),
                
                // Luftdruck 
                'baromin'        => isset($row['baromrelin']) && $row['baromrelin'] !== '' ? (float)$row['baromrelin'] : null,
                
                // Wind 
                'windspeedmph'   => isset($row['windspeedmph']) && $row['windspeedmph'] !== '' ? (float)$row['windspeedmph'] : null,
                'windgustmph'    => isset($row['windgustmph']) && $row['windgustmph'] !== '' ? (float)$row['windgustmph'] : null,
                'winddir'        => isset($row['winddir']) && $row['winddir'] !== '' ? (int)$row['winddir'] : null,
                
                // Niederschlag
                'hourlyrainin'   => isset($row['hourlyrainin']) && $row['hourlyrainin'] !== '' ? (float)$row['hourlyrainin'] : null,
                'mprecip'        => isset($row['dailyrainin']) && $row['dailyrainin'] !== '' ? inToMm($row['dailyrainin']) : null, 
                
                // Solar & UV 
                'solarradiation' => isset($row['solarradiation']) && $row['solarradiation'] !== '' ? (float)$row['solarradiation'] : null,
                'uv'             => isset($row['uv']) && $row['uv'] !== '' ? (float)$row['uv'] : null
            ]
        ]
    ];

    // Leere (null) Felder sauber aus dem JSON-Payload entfernen
    $payload['observations'][0] = array_filter($payload['observations'][0], function($value) {
        return $value !== null;
    });

    $jsonPayload = json_encode($payload);
    $url = "https://stations.windy.com/pws/update/" . trim($config['apiKey']);

    // 2. cURL Request senden (POST JSON)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 3. Auswertung (Log-Ausgabe nutzt jetzt $logTime statt $dateIso)
    if ($curlError) {
        echo "-> Station [$name]: KRITISCHER FEHLER (cURL) - " . $curlError . "\n";
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        echo "-> Station [$name]: Daten erfolgreich uebertragen. (HTTP $httpCode, Zeit: $logTime)\n";
    } else {
        echo "-> Station [$name]: API FEHLER - HTTP Code: $httpCode | Antwort: " . trim($response) . "\n";
        echo "   Gesendete URL: $url\n";
        echo "   Gesendetes JSON: $jsonPayload\n";
    }
}

// Nutzt ebenfalls 'Europe/Berlin'
echo "[" . date('Y-m-d H:i:s') . "] Prozess beendet.\n\n";
?>
