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
            progressRing: document.getElementById('progressRing'),
            backgroundRing: document.getElementById('backgroundRing'),
            targetMarker: document.getElementById('targetMarker'),
            dragPath: document.getElementById('dragPath'),
            increaseBtn: document.getElementById('increaseTemp'),
            decreaseBtn: document.getElementById('decreaseTemp'),
            modeButtons: document.getElementById('modeButtons')
        };
        
        this.minTemp = 5;
        this.maxTemp = 35;
        this.ringLength = 445; // Ungefähre Länge des offenen Rings
        this.isDragging = false;
        
        // Modusspezifische Farben
        this.modeColors = {
            0: 'hsl(220 15% 40%)', // Aus - Grau
            1: 'hsl(0 70% 55%)',   // Heizen - Rot
            2: 'hsl(200 80% 60%)', // Kühlen - Blau
            3: 'hsl(120 60% 50%)'  // Auto - Grün
        };
        
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
        
        // Ring Slider Events
        this.bindRingSliderEvents();
        
        // Touch Events für bessere mobile Unterstützung
        this.addTouchFeedback(this.elements.increaseBtn);
        this.addTouchFeedback(this.elements.decreaseBtn);
    }
    
    bindRingSliderEvents() {
        // Mouse Events
        this.elements.dragPath.addEventListener('mousedown', (e) => this.startDrag(e));
        this.elements.targetMarker.addEventListener('mousedown', (e) => this.startDrag(e));
        document.addEventListener('mousemove', (e) => this.drag(e));
        document.addEventListener('mouseup', () => this.endDrag());
        
        // Touch Events
        this.elements.dragPath.addEventListener('touchstart', (e) => this.startDrag(e), { passive: false });
        this.elements.targetMarker.addEventListener('touchstart', (e) => this.startDrag(e), { passive: false });
        document.addEventListener('touchmove', (e) => this.drag(e), { passive: false });
        document.addEventListener('touchend', () => this.endDrag());
        
        // Click auf Ring
        this.elements.dragPath.addEventListener('click', (e) => this.handleRingClick(e));
    }
    
    startDrag(e) {
        e.preventDefault();
        this.isDragging = true;
        this.elements.targetMarker.style.transform += ' scale(1.2)';
    }
    
    drag(e) {
        if (!this.isDragging) return;
        e.preventDefault();
        
        const clientX = e.clientX || (e.touches && e.touches[0].clientX);
        const clientY = e.clientY || (e.touches && e.touches[0].clientY);
        
        this.updateTemperatureFromPosition(clientX, clientY);
    }
    
    endDrag() {
        if (!this.isDragging) return;
        this.isDragging = false;
        this.elements.targetMarker.style.transform = this.elements.targetMarker.style.transform.replace(' scale(1.2)', '');
    }
    
    handleRingClick(e) {
        if (this.isDragging) return;
        this.updateTemperatureFromPosition(e.clientX, e.clientY);
    }
    
    updateTemperatureFromPosition(clientX, clientY) {
        const svg = this.elements.progressRing.closest('svg');
        const rect = svg.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        
        // Berechne Winkel relativ zur 12-Uhr Position
        const deltaX = clientX - centerX;
        const deltaY = clientY - centerY;
        let angle = Math.atan2(deltaY, deltaX) * (180 / Math.PI);
        
        // Normalisiere Winkel: 0° = 12 Uhr, 180° = 6 Uhr (unten offen)
        angle = angle + 90;
        if (angle < 0) angle += 360;
        if (angle > 360) angle -= 360;
        
        // Begrenze auf Ring-Bereich (0° bis 180°, unten offen)
        if (angle > 180 && angle < 270) angle = 180;
        if (angle > 270) angle = 0;
        
        // Konvertiere Winkel zu Temperatur
        const tempRange = this.maxTemp - this.minTemp;
        const normalizedAngle = angle / 180; // 0 bis 1
        const newTemp = this.minTemp + (normalizedAngle * tempRange);
        
        // Runde auf 0.5°C Schritte
        const roundedTemp = Math.round(newTemp * 2) / 2;
        const clampedTemp = Math.max(this.minTemp, Math.min(this.maxTemp, roundedTemp));
        
        if (clampedTemp !== this.data.targetTemperature) {
            this.data.targetTemperature = clampedTemp;
            this.updateDisplay();
            
            // Sende Änderung an Symcon
            this.requestAction('SetTargetTemperature', clampedTemp);
        }
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
        
        // Berechne Ring-Fortschritt (0° bis 180°)
        const ringProgress = progress * 0.5; // Halbkreis
        const offset = this.ringLength - (ringProgress * this.ringLength);
        this.elements.progressRing.style.strokeDashoffset = offset;
        
        // Setze Farbe basierend auf Modus
        const modeColor = this.modeColors[this.data.mode] || this.modeColors[0];
        this.elements.progressRing.style.stroke = modeColor;
        this.elements.backgroundRing.style.stroke = `hsl(220 15% 25%)`;
        this.elements.targetMarker.style.stroke = modeColor;
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
        
        // Berechne Position auf dem halben Ring (0° bis 180°)
        const angle = progress * 180; // 0° = links, 180° = rechts
        
        // Konvertiere zu SVG Position
        const radian = (angle - 90) * (Math.PI / 180); // -90° um bei 12 Uhr zu starten
        const centerX = 100;
        const centerY = 100;
        const radius = 85;
        
        const x = centerX + radius * Math.cos(radian);
        const y = centerY + radius * Math.sin(radian);
        
        this.elements.targetMarker.setAttribute('cx', x);
        this.elements.targetMarker.setAttribute('cy', y);
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
