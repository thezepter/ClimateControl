class ClimateControl {
    constructor() {
        this.data = {
            currentTemperature: 20,
            targetTemperature: 22,
            mode: 0,
            modes: []
        };
        
        this.elements = {
            currentTemp: document.getElementById('currentTemperature'),
            targetTemp: document.getElementById('targetTemperature'),
            progressCircle: document.getElementById('progressCircle'),
            temperatureMarker: document.getElementById('temperatureMarker'),
            increaseBtn: document.getElementById('increaseTemp'),
            decreaseBtn: document.getElementById('decreaseTemp'),
            modeButtons: document.getElementById('modeButtons')
        };
        
        this.minTemp = 5;
        this.maxTemp = 35;
        this.circumference = 2 * Math.PI * 85; // r = 85
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadInitialData();
        this.updateDisplay();
    }
    
    bindEvents() {
        // Temperatur Steuerung
        this.elements.increaseBtn.addEventListener('click', () => {
            this.requestAction('IncreaseTemperature', '');
        });
        
        this.elements.decreaseBtn.addEventListener('click', () => {
            this.requestAction('DecreaseTemperature', '');
        });
        
        // Touch Events für bessere mobile Unterstützung
        this.addTouchFeedback(this.elements.increaseBtn);
        this.addTouchFeedback(this.elements.decreaseBtn);
    }
    
    addTouchFeedback(element) {
        element.addEventListener('touchstart', (e) => {
            e.preventDefault();
            element.style.transform = 'scale(0.95)';
        });
        
        element.addEventListener('touchend', (e) => {
            e.preventDefault();
            element.style.transform = '';
            element.click();
        });
    }
    
    loadInitialData() {
        // Lade initiale Daten vom Symcon Modul
        if (typeof IPS_RequestAction === 'function') {
            // Symcon Umgebung - lade aktuelle Daten
            this.requestData();
        } else {
            // Entwicklungsumgebung - verwende Standardwerte
            console.log('Entwicklungsmodus: Verwende Standardwerte');
            this.data.modes = [
                { value: 0, name: 'Aus', icon: 'power' },
                { value: 1, name: 'Heizen', icon: 'thermometer' },
                { value: 2, name: 'Kühlen', icon: 'snowflake' },
                { value: 3, name: 'Auto', icon: 'settings' }
            ];
            this.updateModeButtons();
        }
    }
    
    requestData() {
        try {
            // Anfrage an das PHP Modul für aktuelle Daten
            const moduleData = window.GetCurrentData ? window.GetCurrentData() : null;
            if (moduleData) {
                this.updateData(moduleData);
            }
        } catch (error) {
            console.error('Fehler beim Laden der Daten:', error);
        }
    }
    
    requestAction(ident, value) {
        try {
            if (typeof IPS_RequestAction === 'function') {
                IPS_RequestAction(ident, value);
            } else {
                console.log(`RequestAction: ${ident} = ${value}`);
                // Simulation für Entwicklung
                this.simulateAction(ident, value);
            }
        } catch (error) {
            console.error('Fehler bei RequestAction:', error);
        }
    }
    
    simulateAction(ident, value) {
        // Simulation für Entwicklungsumgebung
        switch (ident) {
            case 'IncreaseTemperature':
                if (this.data.targetTemperature < this.maxTemp) {
                    this.data.targetTemperature += 0.5;
                    this.updateDisplay();
                }
                break;
            case 'DecreaseTemperature':
                if (this.data.targetTemperature > this.minTemp) {
                    this.data.targetTemperature -= 0.5;
                    this.updateDisplay();
                }
                break;
            case 'SetMode':
                this.data.mode = parseInt(value);
                this.updateModeButtons();
                break;
        }
    }
    
    updateData(newData) {
        this.data = { ...this.data, ...newData };
        this.updateDisplay();
        this.updateModeButtons();
    }
    
    updateDisplay() {
        // Aktualisiere Temperaturanzeigen
        this.elements.currentTemp.textContent = this.formatTemperature(this.data.currentTemperature);
        this.elements.targetTemp.textContent = this.formatTemperature(this.data.targetTemperature);
        
        // Aktualisiere Kreisfortschritt
        this.updateCircleProgress();
        
        // Aktualisiere Temperatur Marker
        this.updateTemperatureMarker();
    }
    
    formatTemperature(temp) {
        return parseFloat(temp).toFixed(1);
    }
    
    updateCircleProgress() {
        const tempRange = this.maxTemp - this.minTemp;
        const currentRange = Math.max(0, Math.min(tempRange, this.data.currentTemperature - this.minTemp));
        const progress = currentRange / tempRange;
        
        const offset = this.circumference - (progress * this.circumference);
        this.elements.progressCircle.style.strokeDashoffset = offset;
        
        // Farbänderung basierend auf Temperatur
        const hue = this.getTemperatureHue(this.data.currentTemperature);
        this.elements.progressCircle.style.stroke = `hsl(${hue}, 80%, 60%)`;
        this.elements.temperatureMarker.style.fill = `hsl(${hue}, 80%, 60%)`;
    }
    
    getTemperatureHue(temp) {
        // Blau (200) für kalt bis Rot (0) für heiß
        const tempRange = this.maxTemp - this.minTemp;
        const normalizedTemp = Math.max(0, Math.min(1, (temp - this.minTemp) / tempRange));
        return Math.round(200 - (normalizedTemp * 200));
    }
    
    updateTemperatureMarker() {
        const tempRange = this.maxTemp - this.minTemp;
        const targetRange = Math.max(0, Math.min(tempRange, this.data.targetTemperature - this.minTemp));
        const progress = targetRange / tempRange;
        const angle = (progress * 360) - 90; // -90 um bei 12 Uhr zu starten
        
        this.elements.temperatureMarker.style.transform = `rotate(${angle}deg)`;
        this.elements.temperatureMarker.style.transformOrigin = '100px 100px';
    }
    
    updateModeButtons() {
        this.elements.modeButtons.innerHTML = '';
        
        this.data.modes.forEach(mode => {
            const button = document.createElement('button');
            button.className = `mode-btn ${mode.value === this.data.mode ? 'active' : ''}`;
            button.textContent = mode.name;
            button.onclick = () => this.setMode(mode.value);
            
            // Touch Feedback
            this.addTouchFeedback(button);
            
            this.elements.modeButtons.appendChild(button);
        });
    }
    
    setMode(modeValue) {
        this.requestAction('SetMode', modeValue);
        
        // Lokale Aktualisierung für bessere UX
        this.data.mode = modeValue;
        this.updateModeButtons();
    }
    
    // Symcon HandleMessage Handler
    handleMessage(data) {
        try {
            const messageData = typeof data === 'string' ? JSON.parse(data) : data;
            this.updateData(messageData);
        } catch (error) {
            console.error('Fehler beim Verarbeiten der Nachricht:', error);
        }
    }
}

// Initialisierung nach DOM Load
document.addEventListener('DOMContentLoaded', () => {
    window.climateControl = new ClimateControl();
});

// Globale Funktionen für Symcon Integration
window.HandleMessage = function(data) {
    if (window.climateControl) {
        window.climateControl.handleMessage(data);
    }
};

window.UpdateClimateData = function(data) {
    if (window.climateControl) {
        window.climateControl.updateData(data);
    }
};

// Service Worker für bessere Performance (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

// Keyboard Navigation Support
document.addEventListener('keydown', (e) => {
    if (!window.climateControl) return;
    
    switch (e.key) {
        case 'ArrowUp':
        case '+':
            e.preventDefault();
            window.climateControl.requestAction('IncreaseTemperature', '');
            break;
        case 'ArrowDown':
        case '-':
            e.preventDefault();
            window.climateControl.requestAction('DecreaseTemperature', '');
            break;
        case '1':
        case '2':
        case '3':
        case '4':
        case '5':
            e.preventDefault();
            const modeIndex = parseInt(e.key) - 1;
            if (window.climateControl.data.modes[modeIndex]) {
                window.climateControl.setMode(window.climateControl.data.modes[modeIndex].value);
            }
            break;
    }
});

// Visibility API für Energiesparen
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        // Pausiere Updates wenn Seite nicht sichtbar
        console.log('Seite versteckt - pausiere Updates');
    } else {
        // Lade aktuelle Daten wenn Seite wieder sichtbar
        console.log('Seite sichtbar - lade aktuelle Daten');
        if (window.climateControl) {
            window.climateControl.requestData();
        }
    }
});
