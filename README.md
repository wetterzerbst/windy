Die datei cron_windy.php soll es Dir ermöglichen, die Daten mehrerer Wetterstationen in einem Rutsch an die Windy-API
zu übermitteln. Wir empfehlen für die Einrichtung des CRONTABS, die Übertragungszeit nur auf alle 5 Minuten einzustellen,
da die Empfangs-API bei Windy den Datenempfang sonst verweigert. In diesem Script gibt es auch die Möglichkeit, für einzelne
Stationen die Datenübertragung ein- oder auszuschalten. Man kann dieses Script also auch mit nur einer Station nutzen.

Konfiguration des Crontabs:
*/5 * * * * php /pfad/zur/cron_windy.php > /pfad/zum/logfile/windy.txt 2>&1

Beim Crontab ist darauf zu achten, die richtigen Pfade zu nutzen. Hier wurde Wert darauf gelegt, das immer nur die letzte
Übertragung in das Logfile geschrieben wird, um das Dateisystem nicht unnötig voll zu schreiben, aber auch Spammails nach
jeder Ausführung des CRONTABS zu vermeiden. Irgendwann ist nämlich auch mal der Speicherplatz voll.
