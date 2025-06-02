<?php

declare(strict_types=1);

class ClimateControlTile extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Eigenschaften für Variable IDs
        $this->RegisterPropertyInteger('CurrentTemperatureVariableID', 0);
        $this->RegisterPropertyInteger('TargetTemperatureVariableID', 0);
        $this->RegisterPropertyInteger('ModeVariableID', 0);
        
        // Eigenschaften für Konfiguration
        $this->RegisterPropertyFloat('TemperatureStep', 0.5);
        $this->RegisterPropertyFloat('MinTemperature', 5.0);
        $this->RegisterPropertyFloat('MaxTemperature', 35.0);
        
        // Variable für HTML-Inhalt registrieren
        $this->RegisterVariableString('HTMLContent', 'HTML Content', '~HTMLBox', 0);
        
        // Action-Variablen für Steuerung registrieren
        $this->RegisterVariableBoolean('TempUp', 'Temperatur +', '~Switch', 1);
        $this->RegisterVariableBoolean('TempDown', 'Temperatur -', '~Switch', 2);
        $this->EnableAction('TempUp');
        $this->EnableAction('TempDown');
    }

    public function ApplyChanges()
    {
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

        // HTML-Inhalt initial setzen
        $this->UpdateHTMLContent();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case VM_UPDATE:
                $this->UpdateHTMLContent();
                break;
        }
    }

    private function UpdateHTMLContent()
    {
        $htmlContent = $this->GetVisualizationTile();
        SetValue($this->GetIDForIdent('HTMLContent'), $htmlContent);
    }

    public function RequestAction($Ident, $Value)
    {
        IPS_LogMessage('ClimateControl', "RequestAction called: $Ident = $Value");
        
        switch ($Ident) {
            case 'TempUp':
                $this->ChangeTemperature(true);
                SetValue($this->GetIDForIdent('TempUp'), false); // Button zurücksetzen
                break;
            case 'TempDown':
                $this->ChangeTemperature(false);
                SetValue($this->GetIDForIdent('TempDown'), false); // Button zurücksetzen
                break;
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
        
        // HTML nach Aktion aktualisieren
        $this->UpdateHTMLContent();
    }

    public function TestFunction()
    {
        IPS_LogMessage('ClimateControl', 'Test function called successfully!');
        echo "Test erfolgreich!";
    }

    private function ChangeTemperature(bool $increase)
    {
        $targetTempVarID = $this->ReadPropertyInteger('TargetTemperatureVariableID');
        if ($targetTempVarID === 0 || !IPS_VariableExists($targetTempVarID)) {
            IPS_LogMessage('ClimateControl', 'Keine Soll-Temperatur Variable konfiguriert');
            return;
        }

        $currentTemp = GetValue($targetTempVarID);
        $step = $this->ReadPropertyFloat('TemperatureStep');
        $minTemp = $this->ReadPropertyFloat('MinTemperature');
        $maxTemp = $this->ReadPropertyFloat('MaxTemperature');

        $newTemp = $increase ? $currentTemp + $step : $currentTemp - $step;
        $newTemp = max($minTemp, min($maxTemp, $newTemp));

        IPS_LogMessage('ClimateControl', "Ändere Temperatur von $currentTemp auf $newTemp");
        
        // Direkt Variable setzen oder RequestAction versuchen
        try {
            if (IPS_GetVariable($targetTempVarID)['VariableAction'] > 0) {
                RequestAction($targetTempVarID, $newTemp);
            } else {
                SetValue($targetTempVarID, $newTemp);
            }
        } catch (Exception $e) {
            IPS_LogMessage('ClimateControl', 'Fehler beim Setzen der Temperatur: ' . $e->getMessage());
        }
    }

    private function SetMode(int $modeValue)
    {
        $modeVarID = $this->ReadPropertyInteger('ModeVariableID');
        if ($modeVarID === 0 || !IPS_VariableExists($modeVarID)) {
            IPS_LogMessage('ClimateControl', 'Keine Modus Variable konfiguriert');
            return;
        }

        IPS_LogMessage('ClimateControl', "Ändere Modus auf $modeValue");
        
        try {
            if (IPS_GetVariable($modeVarID)['VariableAction'] > 0) {
                RequestAction($modeVarID, $modeValue);
            } else {
                SetValue($modeVarID, $modeValue);
            }
        } catch (Exception $e) {
            IPS_LogMessage('ClimateControl', 'Fehler beim Setzen des Modus: ' . $e->getMessage());
        }
    }

    private function SimulateTemperatureChange(bool $increase)
    {
        // Simulierte Temperaturwerte für Demo-Zwecke
        $step = $this->ReadPropertyFloat('TemperatureStep');
        $minTemp = $this->ReadPropertyFloat('MinTemperature');
        $maxTemp = $this->ReadPropertyFloat('MaxTemperature');
        
        // Aktuelle Werte aus der Demo-Simulation holen
        $currentTarget = $this->GetSimulatedTargetTemp();
        $newTemp = $increase ? $currentTarget + $step : $currentTarget - $step;
        $newTemp = max($minTemp, min($maxTemp, $newTemp));
        
        $this->SetSimulatedTargetTemp($newTemp);
    }

    private function SimulateModeChange(int $modeValue)
    {
        $this->SetSimulatedMode($modeValue);
    }

    private function GetSimulatedTargetTemp(): float
    {
        return $this->GetBuffer('SimulatedTargetTemp') ? (float)$this->GetBuffer('SimulatedTargetTemp') : 22.0;
    }

    private function SetSimulatedTargetTemp(float $temp)
    {
        $this->SetBuffer('SimulatedTargetTemp', (string)$temp);
    }

    private function GetSimulatedMode(): int
    {
        return $this->GetBuffer('SimulatedMode') ? (int)$this->GetBuffer('SimulatedMode') : 0;
    }

    private function SetSimulatedMode(int $mode)
    {
        $this->SetBuffer('SimulatedMode', (string)$mode);
    }

    public function GetVisualizationTile()
    {
        $data = $this->GetCurrentData();
        
        $content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .tile { width: 100%; height: 100%; background: #252A36; border-radius: 16px; padding: 20px; color: #F2F2F2; position: relative; overflow: hidden; box-sizing: border-box; }
        .temp-circle { position: relative; width: 180px; height: 180px; margin: 0 auto 15px; }
        .temp-display { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
        .current-temp { font-size: 32px; font-weight: 300; line-height: 1; margin-bottom: 5px; }
        .target-temp { font-size: 12px; color: #B3B3B3; }
        .controls { display: flex; justify-content: center; gap: 30px; margin-bottom: 15px; }
        .temp-btn { width: 35px; height: 35px; border-radius: 50%; border: 2px solid #363B47; background: transparent; color: #F2F2F2; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.3s ease; }
        .temp-btn:hover { border-color: #4DA6FF; background: rgba(77, 166, 255, 0.1); }
        .modes { display: flex; gap: 5px; flex-wrap: wrap; justify-content: center; }
        .mode-btn { padding: 6px 10px; border-radius: 6px; border: 1px solid #363B47; background: transparent; color: #B3B3B3; cursor: pointer; font-size: 11px; font-weight: 500; transition: all 0.3s ease; min-width: 40px; text-align: center; }
        .mode-btn.active { background: #4DA6FF; border-color: #4DA6FF; color: white; }
        .mode-btn:hover { border-color: #4DA6FF; color: #F2F2F2; }
    </style>
</head>
<body>
    <div class="tile">
        <div class="temp-circle">
            <svg style="width: 100%; height: 100%; position: absolute; top: 0; left: 0;" viewBox="0 0 180 180">
                <circle cx="90" cy="90" r="75" fill="none" stroke="#363B47" stroke-width="6"/>
                <circle id="progressCircle" cx="90" cy="90" r="75" fill="none" stroke="#4DA6FF" stroke-width="6" 
                        stroke-linecap="round" stroke-dasharray="471.24" stroke-dashoffset="300" 
                        transform="rotate(-90 90 90)" style="transition: all 0.5s ease;"/>
                <circle id="tempMarker" cx="90" cy="15" r="4" fill="#4DA6FF" transform="rotate(45 90 90)"/>
            </svg>
            
            <div class="temp-display">
                <div class="current-temp">' . number_format($data['currentTemperature'], 1) . '<span style="font-size: 0.6em; margin-left: 2px; color: #B3B3B3;">°C</span></div>
                <div class="target-temp">🎯 ' . number_format($data['targetTemperature'], 1) . '°C</div>
            </div>
        </div>
        
        <div class="controls">
            <button class="temp-btn" onclick="triggerSymconAction(\'TempDown\')">−</button>
            <button class="temp-btn" onclick="triggerSymconAction(\'TempUp\')">+</button>
        </div>
        
        <div class="modes">';
        
        foreach ($data['modes'] as $mode) {
            $active = $mode['value'] === $data['mode'] ? ' active' : '';
            $content .= '<button class="mode-btn' . $active . '" onclick="setMode(' . $mode['value'] . ')">' . 
                       htmlspecialchars($mode['name']) . '</button>';
        }
        
        $content .= '</div>
    </div>
    
    <script>
        function triggerSymconAction(action) {
            console.log("Triggering Symcon action:", action);
            
            // Versuche verschiedene Methoden zur Symcon-Kommunikation
            const instanceID = ' . $this->InstanceID . ';
            
            // Methode 1: Direkte IPS_RequestAction
            if (typeof IPS_RequestAction === "function") {
                console.log("Using direct IPS_RequestAction");
                IPS_RequestAction(instanceID, action, true);
                setTimeout(() => location.reload(), 1000);
                return;
            }
            
            // Methode 2: Parent window IPS_RequestAction
            if (window.parent && typeof window.parent.IPS_RequestAction === "function") {
                console.log("Using parent IPS_RequestAction");
                window.parent.IPS_RequestAction(instanceID, action, true);
                setTimeout(() => location.reload(), 1000);
                return;
            }
            
            // Methode 3: Variable direkt über Variable-ID setzen
            const tempUpVarID = ' . $this->GetIDForIdent('TempUp') . ';
            const tempDownVarID = ' . $this->GetIDForIdent('TempDown') . ';
            
            if (action === "TempUp" && typeof IPS_SetValue === "function") {
                IPS_SetValue(tempUpVarID, true);
                setTimeout(() => location.reload(), 1000);
                return;
            }
            
            if (action === "TempDown" && typeof IPS_SetValue === "function") {
                IPS_SetValue(tempDownVarID, true);
                setTimeout(() => location.reload(), 1000);
                return;
            }
            
            // Fallback: Page refresh um Änderungen zu sehen
            console.log("No Symcon integration available, reloading page");
            location.reload();
        }
        
        function setMode(modeValue) {
            currentMode = modeValue;
            
            document.querySelectorAll(".mode-btn").forEach(btn => btn.classList.remove("active"));
            event.target.classList.add("active");
            
            console.log("Mode changed to:", modeValue);
            
            try {
                if (typeof IPS_RequestAction === "function") {
                    IPS_RequestAction(' . $this->InstanceID . ', "SetMode", modeValue);
                } else if (typeof window.parent.IPS_RequestAction === "function") {
                    window.parent.IPS_RequestAction(' . $this->InstanceID . ', "SetMode", modeValue);
                }
            } catch (e) {
                console.log("RequestAction not available, using local simulation");
            }
        }
        
        function updateCircle() {
            const progress = Math.max(0, Math.min(1, (currentTargetTemp - 5) / (35 - 5)));
            const circumference = 471.24;
            const offset = circumference - (progress * circumference);
            document.getElementById("progressCircle").style.strokeDashoffset = offset;
            
            const hue = Math.round(200 - (progress * 200));
            document.getElementById("progressCircle").style.stroke = `hsl(${hue}, 80%, 60%)`;
            document.getElementById("tempMarker").style.fill = `hsl(${hue}, 80%, 60%)`;
            
            const angle = (progress * 360) - 90;
            document.getElementById("tempMarker").style.transform = `rotate(${angle}deg)`;
        }
        
        // Initial circle setup
        updateCircle();
    </script>
</body>
</html>';
        
        return $content;
    }

    public function GetCurrentData(): array
    {
        $currentTempVarID = $this->ReadPropertyInteger('CurrentTemperatureVariableID');
        $targetTempVarID = $this->ReadPropertyInteger('TargetTemperatureVariableID');
        $modeVarID = $this->ReadPropertyInteger('ModeVariableID');

        $data = [
            'currentTemperature' => 20.0,
            'targetTemperature' => $this->GetSimulatedTargetTemp(),
            'mode' => $this->GetSimulatedMode(),
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

        // Modi wenn keine Variable konfiguriert
        if (empty($data['modes'])) {
            $data['modes'] = [
                ['value' => 0, 'name' => 'Stop', 'icon' => ''],
                ['value' => 1, 'name' => 'Kühlen', 'icon' => ''],
                ['value' => 2, 'name' => 'Heizen', 'icon' => ''],
                ['value' => 3, 'name' => 'Lüfter', 'icon' => ''],
                ['value' => 4, 'name' => 'Entfeuchten', 'icon' => ''],
                ['value' => 5, 'name' => 'Automatik', 'icon' => '']
            ];
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
                ],
                [
                    'type' => 'Label',
                    'caption' => 'Die Klimasteuerung ist über die Tile-Visualisierung im WebFront verfügbar.'
                ]
            ]
        ]);
    }
}