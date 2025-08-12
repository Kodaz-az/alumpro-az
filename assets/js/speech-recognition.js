// Speech recognition functionality

class SpeechRecognitionManager {
    constructor() {
        this.recognition = null;
        this.isSupported = false;
        this.isListening = false;
        this.language = 'az-AZ';
        
        this.init();
    }

    init() {
        // Check browser support
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            this.isSupported = true;
            this.setupRecognition();
        } else {
            console.warn('Speech recognition not supported in this browser');
            this.hideVoiceButtons();
        }
    }

    setupRecognition() {
        if (!this.recognition) return;

        // Configure recognition
        this.recognition.continuous = false;
        this.recognition.interimResults = false;
        this.recognition.lang = this.language;
        this.recognition.maxAlternatives = 1;

        // Event listeners
        this.recognition.onstart = () => {
            this.isListening = true;
            this.onStart();
        };

        this.recognition.onend = () => {
            this.isListening = false;
            this.onEnd();
        };

        this.recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            const confidence = event.results[0][0].confidence;
            this.onResult(transcript, confidence);
        };

        this.recognition.onerror = (event) => {
            this.isListening = false;
            this.onError(event.error);
        };

        this.recognition.onnomatch = () => {
            this.onNoMatch();
        };
    }

    start(targetInput = null) {
        if (!this.isSupported || this.isListening) return false;

        this.targetInput = targetInput;
        
        try {
            this.recognition.start();
            return true;
        } catch (error) {
            console.error('Failed to start speech recognition:', error);
            return false;
        }
    }

    stop() {
        if (this.recognition && this.isListening) {
            this.recognition.stop();
        }
    }

    toggle(targetInput = null) {
        if (this.isListening) {
            this.stop();
        } else {
            this.start(targetInput);
        }
    }

    onStart() {
        // Update UI to show recording state
        this.updateVoiceButtons(true);
        
        // Show visual feedback
        this.showRecordingIndicator();
    }

    onEnd() {
        // Update UI to show stopped state
        this.updateVoiceButtons(false);
        
        // Hide visual feedback
        this.hideRecordingIndicator();
    }

    onResult(transcript, confidence) {
        // Fill target input with transcript
        if (this.targetInput) {
            this.targetInput.value = transcript;
            
            // Trigger input event
            const event = new Event('input', { bubbles: true });
            this.targetInput.dispatchEvent(event);
        }

        // Log result for debugging
        console.log('Speech recognition result:', transcript, 'Confidence:', confidence);
        
        // Show success feedback
        this.showSuccess(`Səs tanındı: "${transcript}"`);
    }

    onError(error) {
        console.error('Speech recognition error:', error);
        
        let errorMessage = 'Səs tanıma xətası';
        
        switch (error) {
            case 'network':
                errorMessage = 'Şəbəkə əlaqəsi xətası';
                break;
            case 'not-allowed':
                errorMessage = 'Mikrofon icazəsi verilməyib';
                break;
            case 'no-speech':
                errorMessage = 'Heç bir səs eşidilmədi';
                break;
            case 'audio-capture':
                errorMessage = 'Mikrofon əlaqə xətası';
                break;
        }
        
        this.showError(errorMessage);
    }

    onNoMatch() {
        this.showWarning('Səs tanınmadı, yenidən cəhd edin');
    }

    updateVoiceButtons(isRecording) {
        const voiceButtons = document.querySelectorAll('[data-voice-button]');
        
        voiceButtons.forEach(button => {
            const icon = button.querySelector('i');
            
            if (isRecording) {
                button.classList.add('btn-danger');
                button.classList.remove('btn-outline-secondary');
                if (icon) {
                    icon.className = 'bi bi-mic-fill';
                }
                button.title = 'Dayandir';
            } else {
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-secondary');
                if (icon) {
                    icon.className = 'bi bi-mic';
                }
                button.title = 'Səs yazma';
            }
        });
    }

    showRecordingIndicator() {
        // Create or show recording indicator
        let indicator = document.getElementById('recordingIndicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'recordingIndicator';
            indicator.className = 'recording-indicator';
            indicator.innerHTML = `
                <div class="recording-pulse"></div>
                <span>Dinləyirəm...</span>
            `;
            document.body.appendChild(indicator);
        }
        
        indicator.style.display = 'flex';
    }

    hideRecordingIndicator() {
        const indicator = document.getElementById('recordingIndicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    hideVoiceButtons() {
        const voiceButtons = document.querySelectorAll('[data-voice-button]');
        voiceButtons.forEach(button => {
            button.style.display = 'none';
        });
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showWarning(message) {
        this.showNotification(message, 'warning');
    }

    showNotification(message, type) {
        // Use global notification system if available
        if (window.AlumproApp && window.AlumproApp.showNotification) {
            window.AlumproApp.showNotification('Səs Tanıma', message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }

    // Change language
    setLanguage(lang) {
        this.language = lang;
        if (this.recognition) {
            this.recognition.lang = lang;
        }
    }

    // Get supported languages
    static getSupportedLanguages() {
        return [
            { code: 'az-AZ', name: 'Azərbaycanca' },
            { code: 'tr-TR', name: 'Türkçe' },
            { code: 'en-US', name: 'English' },
            { code: 'ru-RU', name: 'Русский' }
        ];
    }
}

// Global instance
const speechRecognition = new SpeechRecognitionManager();

// Setup voice buttons
document.addEventListener('DOMContentLoaded', function() {
    const voiceButtons = document.querySelectorAll('[data-voice-button]');
    
    voiceButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetInputId = this.getAttribute('data-target');
            let targetInput = null;
            
            if (targetInputId) {
                targetInput = document.getElementById(targetInputId);
            } else {
                // Find the nearest input
                const parent = this.closest('.input-group') || this.parentElement;
                targetInput = parent.querySelector('input[type="text"], textarea');
            }
            
            speechRecognition.toggle(targetInput);
        });
    });
});

// CSS for recording indicator
const recordingCSS = `
.recording-indicator {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(220, 53, 69, 0.95);
    color: white;
    padding: 1rem 2rem;
    border-radius: 25px;
    display: none;
    align-items: center;
    gap: 0.5rem;
    z-index: 9999;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.recording-pulse {
    width: 12px;
    height: 12px;
    background: white;
    border-radius: 50%;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
    100% { opacity: 1; transform: scale(1); }
}
`;

// Inject CSS
const style = document.createElement('style');
style.textContent = recordingCSS;
document.head.appendChild(style);

// Export for global use
window.speechRecognition = speechRecognition;