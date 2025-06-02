# Climate Control Tile für Symcon

Eine moderne, kreisförmige Klimasteuerungs-Kachel für IP-Symcon mit dem HTML-SDK.

## Features

- **Kreisförmige Temperaturanzeige** mit farbigem Fortschrittsbalken
- **Ist- und Soll-Temperatur** Anzeige im Zentrum
- **Plus/Minus Buttons** für 0,5°C Schritte
- **Modus-Buttons** für verschiedene Betriebsarten (Aus, Heizen, Kühlen, Auto, etc.)
- **Responsive Design** für Desktop und Mobile
- **Touch-optimiert** für Tablets
- **Tastatur-Navigation** unterstützt

## Installation

### Manuelle Installation (empfohlen)

1. **ClimateControlTile-Ordner kopieren**: Kopieren Sie den `ClimateControlTile` Ordner in das Symcon-Modulverzeichnis:
   - Linux: `/var/lib/symcon/modules/`
   - Windows: `C:\ProgramData\Symcon\modules\`

2. **Symcon-Dienst neustarten**: Damit das neue Modul erkannt wird

3. **Instanz erstellen**: 
   - Symcon-Konsole → "Objekte hinzufügen" → "Instanz"
   - Suchen Sie nach "ClimateControlTile" oder "CCT"
   - Neue Instanz erstellen

### Git-Repository Installation

Falls Sie ein Git-Repository haben, können Sie die URL in Symcon unter "Module" → "Hinzufügen" eingeben.

## Konfiguration

Nach der Installation müssen folgende Variablen konfiguriert werden:

### Erforderliche Variablen

- **Ist-Temperatur Variable**: Variable die die aktuelle Temperatur enthält
- **Soll-Temperatur Variable**: Variable die die gewünschte Temperatur speichert
- **Modus Variable**: Variable mit den verschiedenen Betriebsmodi

### Optionale Einstellungen

- **Temperatur Schrittweite**: Standard 0,5°C (einstellbar von 0,1 bis 5,0°C)
- **Minimale Temperatur**: Standard 5°C 
- **Maximale Temperatur**: Standard 35°C

### Modus-Variable einrichten

Die Modus-Variable sollte ein Variablenprofil mit Assoziationen haben:

```
0 = "Aus"
1 = "Heizen" 
2 = "Kühlen"
3 = "Auto"
4 = "Entfeuchten"
```

## Verwendung

- **Temperatur ändern**: Plus/Minus Buttons oder Pfeiltasten
- **Modus wechseln**: Klick auf Modus-Button oder Tasten 1-5
- **Anzeige**: Der Kreis färbt sich je nach Temperatur von blau (kalt) zu rot (heiß)

## Technische Details

- **Kompatibilität**: Symcon 7.0+
- **Technologie**: HTML5, CSS3, JavaScript (ES6)
- **Framework**: Symcon HTML-SDK
- **Browser**: Moderne Browser mit ES6-Unterstützung

## Dateien

```
ClimateControlTile/
├── module.php          # Haupt-Modul-Datei
├── form.json          # Konfigurationsformular
├── locale.json        # Übersetzungen
└── html/
    ├── index.html     # HTML-Template
    ├── index.css      # Styling
    └── index.js       # JavaScript-Logik
```

## Support

Bei Fragen oder Problemen schauen Sie in die Symcon-Dokumentation oder das Community-Forum.