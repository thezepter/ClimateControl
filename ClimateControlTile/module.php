<?php

declare(strict_types=1);

class ClimateControlTile extends IPSModule
{
    public function Create()
    {
        // Nie mehr das parent aufrufen
        parent::Create();

        // Eigenschaften für Variable IDs
        $this->RegisterPropertyInteger('CurrentTemperatureVariableID', 0);
        $this->RegisterPropertyInteger('TargetTemperatureVariableID', 0);
        $this->RegisterPropertyInteger('ModeVariableID', 0);
        
        // Eigenschaften für Konfiguration
        $this->RegisterPropertyFloat('TemperatureStep', 0.5);
        $this->RegisterPropertyFloat('MinTemperature', 5.0);
        $this->RegisterPropertyFloat('MaxTemperature', 35.0);
        
        // WebHook für Tile-Zugriff registrieren
        $this->RegisterHook('/hook/climatetile' . $this->InstanceID);
    }

    public function ApplyChanges()
    {
        // Nie mehr das parent aufrufen
        parent::ApplyChanges();

        // Nachrichten für Variable Updates registrieren
        $currentTempVarID = $this->ReadPropertyInteger('CurrentTemperatureVariableID');
        $targetTempVarID = $this->ReadPropertyInteger('TargetTemperatureVariableID');
        $modeVarID = $this->ReadPropertyInteger('ModeVariableID');

        if ($currentTempVarID > 0) {
            $this->RegisterMessage($currentTempVarID, VM_UPDATE);
        }
        if ($targetTempVarID > 0) {
            $this->RegisterMessage($targetTempVarID, VM_UPDATE);
        }
        if ($modeVarID > 0) {
            $this->RegisterMessage($modeVarID, VM_UPDATE);
        }

        // Initiales HTML laden
        $this->UpdateWebFront();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case VM_UPDATE:
                $this->UpdateWebFront();
                break;
        }
    }

    public function ProcessHookData()
    {
        $root = realpath(__DIR__ . '/html');
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $file = basename($path);
        
        // Wenn keine spezifische Datei angefordert wird, lade die Hauptseite
        if (empty($file) || $file === 'climatetile' . $this->InstanceID) {
            echo $this->GetVisualizationTile();
            return;
        }
        
        // Statische Dateien bereitstellen
        $filePath = $root . '/' . $file;
        if (file_exists($filePath) && strpos(realpath($filePath), $root) === 0) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            switch ($ext) {
                case 'css':
                    header('Content-Type: text/css');
                    break;
                case 'js':
                    header('Content-Type: application/javascript');
                    break;
                case 'html':
                    header('Content-Type: text/html');
                    break;
            }
            readfile($filePath);
        }
    }

    public function HandleMessage(string $data)
    {
        $message = json_decode($data, true);
        
        if (!isset($message['type']) || $message['type'] !== 'RequestAction') {
            return;
        }
        
        $this->RequestAction($message['ident'], $message['value']);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'IncreaseTemperature':
                $this->ChangeTemperature(true);
                break;
            case 'DecreaseTemperature':
                $this->ChangeTemperature(false);
                break;
            case 'SetMode':
                $this->SetMode((int)$Value);
                break;
            default:
                throw new Exception('Invalid Ident');
        }
    }

    private function ChangeTemperature(bool $increase)
    {
        $targetTempVarID = $this->ReadPropertyInteger('TargetTemperatureVariableID');
        if ($targetTempVarID === 0) {
            return;
        }

        $currentTemp = GetValue($targetTempVarID);
        $step = $this->ReadPropertyFloat('TemperatureStep');
        $minTemp = $this->ReadPropertyFloat('MinTemperature');
        $maxTemp = $this->ReadPropertyFloat('MaxTemperature');

        $newTemp = $increase ? $currentTemp + $step : $currentTemp - $step;
        $newTemp = max($minTemp, min($maxTemp, $newTemp));

        RequestAction($targetTempVarID, $newTemp);
        $this->UpdateWebFront();
    }

    private function SetMode(int $modeValue)
    {
        $modeVarID = $this->ReadPropertyInteger('ModeVariableID');
        if ($modeVarID === 0) {
            return;
        }

        RequestAction($modeVarID, $modeValue);
        $this->UpdateWebFront();
    }

    private function UpdateWebFront()
    {
        // WebFront aktualisieren
        $this->SendDataToChildren(json_encode([
            'DataID' => '{7A107D38-75B7-4B65-8FAE-7B8C9F6A7284}',
            'Data' => $this->GetCurrentData()
        ]));
    }
    
    // Hauptmethode für Symcon Tile-Visualisierung
    public function GetVisualizationTile()
    {
        $data = $this->GetCurrentData();
        
        // Kompakte Tile-Version für Symcon WebFront
        $tileHTML = '
        <div style="width: 100%; height: 100%; background: #252A36; border-radius: 16px; padding: 20px; color: #F2F2F2; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; position: relative; overflow: hidden;">
            <!-- Temperatur Kreis -->
            <div style="position: relative; width: 200px; height: 200px; margin: 0 auto 20px;">
                <svg style="width: 100%; height: 100%; position: absolute; top: 0; left: 0;" viewBox="0 0 200 200">
                    <circle cx="100" cy="100" r="85" fill="none" stroke="#363B47" stroke-width="8"/>
                    <circle id="progressCircle" cx="100" cy="100" r="85" fill="none" stroke="#4DA6FF" stroke-width="8" 
                            stroke-linecap="round" stroke-dasharray="534.07" stroke-dashoffset="400" 
                            transform="rotate(-90 100 100)" style="transition: all 0.3s ease;"/>
                    <circle id="tempMarker" cx="100" cy="15" r="6" fill="#4DA6FF" transform="rotate(45 100 100)"/>
                </svg>
                
                <!-- Temperatur Anzeige -->
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                    <div style="font-size: 36px; font-weight: 300; line-height: 1; margin-bottom: 5px;">' . number_format($data['currentTemperature'], 1) . '<span style="font-size: 0.6em; margin-left: 3px; color: #B3B3B3;">°C</span></div>
                    <div style="font-size: 14px; color: #B3B3B3; display: flex; align-items: center; justify-content: center; gap: 3px;">
                        <span>🎯</span>
                        <span>' . number_format($data['targetTemperature'], 1) . '°C</span>
                    </div>
                </div>
            </div>
            
            <!-- Temperatur Steuerung -->
            <div style="display: flex; justify-content: center; gap: 40px; margin-bottom: 20px;">
                <button onclick="requestAction(\'DecreaseTemperature\', \'\')" 
                        style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid #363B47; background: transparent; color: #F2F2F2; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 20px; transition: all 0.3s ease;"
                        onmouseover="this.style.borderColor=\'#4DA6FF\'; this.style.background=\'rgba(77, 166, 255, 0.1)\'"
                        onmouseout="this.style.borderColor=\'#363B47\'; this.style.background=\'transparent\'">−</button>
                        
                <button onclick="requestAction(\'IncreaseTemperature\', \'\')"
                        style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid #363B47; background: transparent; color: #F2F2F2; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.3s ease;"
                        onmouseover="this.style.borderColor=\'#4DA6FF\'; this.style.background=\'rgba(77, 166, 255, 0.1)\'"
                        onmouseout="this.style.borderColor=\'#363B47\'; this.style.background=\'transparent\'">+</button>
            </div>
            
            <!-- Modi -->
            <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;">';
            
        foreach ($data['modes'] as $mode) {
            $isActive = $mode['value'] === $data['mode'];
            $tileHTML .= '<button onclick="requestAction(\'SetMode\', ' . $mode['value'] . ')" 
                                 style="padding: 8px 12px; border-radius: 8px; border: 1px solid ' . ($isActive ? '#4DA6FF' : '#363B47') . '; 
                                        background: ' . ($isActive ? '#4DA6FF' : 'transparent') . '; 
                                        color: ' . ($isActive ? 'white' : '#B3B3B3') . '; cursor: pointer; font-size: 12px; 
                                        font-weight: 500; transition: all 0.3s ease; min-width: 50px; text-align: center;">' . 
                         htmlspecialchars($mode['name']) . '</button>';
        }
        
        $tileHTML .= '
            </div>
            
            <script>
                function requestAction(ident, value) {
                    fetch("/api/module/", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({
                            "jsonrpc": "2.0",
                            "method": "IPS_RequestAction",
                            "params": [' . $this->InstanceID . ', ident, value],
                            "id": 1
                        })
                    }).then(() => {
                        // Seite nach Aktion neu laden
                        setTimeout(() => location.reload(), 500);
                    });
                }
                
                // Temperatur-Progress Animation
                const currentTemp = ' . $data['currentTemperature'] . ';
                const minTemp = 5;
                const maxTemp = 35;
                const progress = Math.max(0, Math.min(1, (currentTemp - minTemp) / (maxTemp - minTemp)));
                const circumference = 534.07;
                const offset = circumference - (progress * circumference);
                
                document.getElementById("progressCircle").style.strokeDashoffset = offset;
                
                // Temperatur-basierte Farbe
                const hue = Math.round(200 - (progress * 200));
                document.getElementById("progressCircle").style.stroke = `hsl(${hue}, 80%, 60%)`;
                document.getElementById("tempMarker").style.fill = `hsl(${hue}, 80%, 60%)`;
                
                // Marker Position
                const targetProgress = Math.max(0, Math.min(1, (' . $data['targetTemperature'] . ' - minTemp) / (maxTemp - minTemp)));
                const angle = (targetProgress * 360) - 90;
                document.getElementById("tempMarker").style.transform = `rotate(${angle}deg)`;
                document.getElementById("tempMarker").style.transformOrigin = "100px 100px";
            </script>
        </div>';
        
        return $tileHTML;
    }
    
    public function GetTileHTML()
    {
        return $this->GetVisualizationTile();
    }

    public function GetCurrentData(): array
    {
        $currentTempVarID = $this->ReadPropertyInteger('CurrentTemperatureVariableID');
        $targetTempVarID = $this->ReadPropertyInteger('TargetTemperatureVariableID');
        $modeVarID = $this->ReadPropertyInteger('ModeVariableID');

        $data = [
            'currentTemperature' => 0,
            'targetTemperature' => 20,
            'mode' => 0,
            'modes' => []
        ];

        if ($currentTempVarID > 0 && IPS_VariableExists($currentTempVarID)) {
            $data['currentTemperature'] = GetValue($currentTempVarID);
        }

        if ($targetTempVarID > 0 && IPS_VariableExists($targetTempVarID)) {
            $data['targetTemperature'] = GetValue($targetTempVarID);
        }

        if ($modeVarID > 0 && IPS_VariableExists($modeVarID)) {
            $data['mode'] = GetValue($modeVarID);
            
            // Hole die verfügbaren Modi aus dem Variablenprofil
            $variable = IPS_GetVariable($modeVarID);
            if ($variable['VariableCustomProfile'] !== '') {
                $profile = IPS_GetVariableProfile($variable['VariableCustomProfile']);
            } elseif ($variable['VariableProfile'] !== '') {
                $profile = IPS_GetVariableProfile($variable['VariableProfile']);
            }

            if (isset($profile) && isset($profile['Associations'])) {
                foreach ($profile['Associations'] as $association) {
                    $data['modes'][] = [
                        'value' => $association['Value'],
                        'name' => $association['Name'],
                        'icon' => $association['Icon'] ?? ''
                    ];
                }
            }
        }

        return $data;
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                [
                    'type' => 'SelectVariable',
                    'name' => 'CurrentTemperatureVariableID',
                    'caption' => 'Ist-Temperatur Variable'
                ],
                [
                    'type' => 'SelectVariable',
                    'name' => 'TargetTemperatureVariableID',
                    'caption' => 'Soll-Temperatur Variable'
                ],
                [
                    'type' => 'SelectVariable',
                    'name' => 'ModeVariableID',
                    'caption' => 'Modus Variable'
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'TemperatureStep',
                    'caption' => 'Temperatur Schrittweite',
                    'minimum' => 0.1,
                    'maximum' => 5.0,
                    'digits' => 1
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'MinTemperature',
                    'caption' => 'Minimale Temperatur',
                    'minimum' => -20,
                    'maximum' => 50,
                    'digits' => 1
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'MaxTemperature',
                    'caption' => 'Maximale Temperatur',
                    'minimum' => -20,
                    'maximum' => 50,
                    'digits' => 1
                ]
            ]
        ]);
    }
}