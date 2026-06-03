Die datei cron_windy.php soll es Dir ermöglichen, die Daten mehrerer Wetterstationen in einem Rutsch an die Windy-API
zu übermitteln. Wir empfehlen für die Einrichtung des CRONTABS, die Übertragungszeit nur auf alle 5 Minuten einzustellen,
da die Empfangs-API bei Windy den Datenempfang sonst verweigert. In diesem Script gibt es auch die Möglichkeit, für einzelne
Stationen die Datenübertragung ein- oder auszuschalten. Man kann dieses Script also auch mit nur einer Station nutzen.

Konfiguration des Crontabs:
*/5 * * * * php /pfad/zur/cron_windy.php > /pfad/zum/logfile/windy.txt 2>&1

Beim Crontab ist darauf zu achten, die richtigen Pfade zu nutzen. Hier wurde Wert darauf gelegt, das immer nur die letzte
Übertragung in das Logfile geschrieben wird, um das Dateisystem nicht unnötig voll zu schreiben, aber auch Spammails nach
jeder Ausführung des CRONTABS zu vermeiden. Irgendwann ist nämlich auch mal der Speicherplatz voll.

===

The cron_windy.php file is designed to allow you to send data from multiple weather stations to the Windy API all at once. When setting up the CRONTAB, we recommend setting the transmission interval to every 5 minutes, as Windy’s receiving API will otherwise reject the data. This script also allows you to enable or disable data transmission for individual stations. This means you can use this script even with just one station.

Configuring the crontab:
*/5 * * * * php /path/to/cron_windy.php > /path/to/logfile/windy.txt 2>&1

When configuring the crontab, make sure to use the correct paths. Here, we have ensured that only the most recent
transmission is written to the log file to avoid unnecessarily filling up the file system, but also to prevent spam emails after
every execution of the crontab. After all, storage space will eventually run out.
