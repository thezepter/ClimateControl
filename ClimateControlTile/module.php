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
        $this->UpdateFormField('TileHTML', 'value', $this->GetTileHTML());
    }
    
    public function GetTileHTML()
    {
        $data = $this->GetCurrentData();
        
        $html = file_get_contents(__DIR__ . '/html/index.html');
        $css = file_get_contents(__DIR__ . '/html/index.css');
        $js = file_get_contents(__DIR__ . '/html/index.js');
        
        // Ersetze relative Pfade durch absolute
        $content = str_replace('<link rel="stylesheet" href="index.css">', '', $html);
        $content = str_replace('<script src="index.js"></script>', '', $content);
        
        // Füge CSS und JS inline hinzu
        $content = str_replace('</head>', '<style>' . $css . '</style></head>', $content);
        $content = str_replace('</body>', '<script>
            window.moduleData = ' . json_encode($data) . ';
            // Override RequestAction für Symcon
            window.RequestAction = function(ident, value) {
                window.parent.postMessage({
                    type: "RequestAction",
                    instanceID: ' . $this->InstanceID . ',
                    ident: ident,
                    value: value
                }, "*");
            };
        </script><script>' . $js . '</script></body>', $content);
        
        return $content;
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