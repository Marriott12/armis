/**
 * ARMIS Voice Command System
 * Web Speech API integration for hands-free operation
 * Military-friendly voice navigation and control
 */

class ARMISVoiceCommands {
    constructor() {
        this.isListening = false;
        this.recognition = null;
        this.synthesis = null;
        this.commands = new Map();
        this.currentLanguage = 'en-US';
        this.continuous = false;
        this.interimResults = true;
        this.maxAlternatives = 1;
        
        // Voice command settings
        this.settings = {
            enabled: localStorage.getItem('armis_voice_enabled') === 'true',
            feedbackEnabled: localStorage.getItem('armis_voice_feedback') !== 'false',
            confidenceThreshold: 0.7,
            timeout: 5000,
            autoStop: true
        };
        
        this.init();
    }
    
    /**
     * Initialize voice recognition system
     */
    init() {
        if (!this.checkBrowserSupport()) {
            console.warn('Voice commands not supported in this browser');
            return;
        }
        
        this.setupSpeechRecognition();
        this.setupSpeechSynthesis();
        this.registerDefaultCommands();
        this.setupUI();
        this.setupEventListeners();
        
        console.log('ARMIS Voice Commands initialized');
    }
    
    /**
     * Check browser support for Web Speech API
     */
    checkBrowserSupport() {
        return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
    }
    
    /**
     * Setup speech recognition
     */
    setupSpeechRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.recognition = new SpeechRecognition();
        
        this.recognition.continuous = this.continuous;
        this.recognition.interimResults = this.interimResults;
        this.recognition.maxAlternatives = this.maxAlternatives;
        this.recognition.lang = this.currentLanguage;
        
        // Event handlers
        this.recognition.onstart = () => {
            this.isListening = true;
            this.updateUI();
            this.showStatus(__('voice.listening'), 'info');
        };
        
        this.recognition.onend = () => {
            this.isListening = false;
            this.updateUI();
        };
        
        this.recognition.onresult = (event) => {
            this.handleSpeechResult(event);
        };
        
        this.recognition.onerror = (event) => {
            this.handleSpeechError(event);
        };
        
        this.recognition.onnomatch = () => {
            this.speak(__('voice.command_not_recognized'));
        };
    }
    
    /**
     * Setup speech synthesis
     */
    setupSpeechSynthesis() {
        this.synthesis = window.speechSynthesis;
    }
    
    /**
     * Register default voice commands
     */
    registerDefaultCommands() {
        // Navigation commands
        this.registerCommand(['go to dashboard', 'open dashboard', 'dashboard'], () => {
            this.navigateTo('/admin_branch/index.php');
        });
        
        this.registerCommand(['go to staff', 'staff management', 'manage staff'], () => {
            this.navigateTo('/admin_branch/edit_staff.php');
        });
        
        this.registerCommand(['create staff', 'add staff', 'new staff'], () => {
            this.navigateTo('/admin_branch/create_staff.php');
        });
        
        this.registerCommand(['promotions', 'promote staff'], () => {
            this.navigateTo('/admin_branch/promote_staff.php');
        });
        
        this.registerCommand(['medals', 'assign medal'], () => {
            this.navigateTo('/admin_branch/assign_medal.php');
        });
        
        this.registerCommand(['reports', 'view reports'], () => {
            this.navigateTo('/admin_branch/reports.php');
        });
        
        this.registerCommand(['training', 'training module'], () => {
            this.navigateTo('/training/index.php');
        });
        
        this.registerCommand(['operations', 'operations module'], () => {
            this.navigateTo('/operations/index.php');
        });
        
        this.registerCommand(['finance', 'finance module'], () => {
            this.navigateTo('/finance/index.php');
        });
        
        // Action commands
        this.registerCommand(['save', 'save form'], () => {
            this.executeAction('save');
        });
        
        this.registerCommand(['cancel', 'go back'], () => {
            this.executeAction('cancel');
        });
        
        this.registerCommand(['search', 'find'], () => {
            this.focusElement('#searchInput, [name="search"], .search-input');
        });
        
        this.registerCommand(['refresh', 'reload'], () => {
            location.reload();
        });
        
        this.registerCommand(['help', 'voice help'], () => {
            this.showVoiceHelp();
        });
        
        // Accessibility commands
        this.registerCommand(['high contrast', 'increase contrast'], () => {
            this.toggleHighContrast();
        });
        
        this.registerCommand(['large text', 'bigger text'], () => {
            this.increaseFontSize();
        });
        
        this.registerCommand(['normal text', 'default text'], () => {
            this.resetFontSize();
        });
        
        // Theme commands
        this.registerCommand(['dark mode', 'dark theme'], () => {
            this.setTheme('dark');
        });
        
        this.registerCommand(['light mode', 'light theme'], () => {
            this.setTheme('light');
        });
        
        this.registerCommand(['field mode', 'tactical mode'], () => {
            this.setTheme('field');
        });
        
        this.registerCommand(['night mode', 'night vision'], () => {
            this.setTheme('night');
        });
        
        // System commands
        this.registerCommand(['stop listening', 'disable voice'], () => {
            this.disable();
        });
        
        this.registerCommand(['logout', 'sign out'], () => {
            if (confirm(__('security.confirm_logout'))) {
                window.location.href = '/logout.php';
            }
        });
    }
    
    /**
     * Register a voice command
     */
    registerCommand(phrases, callback, options = {}) {
        const commandId = Math.random().toString(36).substr(2, 9);
        
        if (typeof phrases === 'string') {
            phrases = [phrases];
        }
        
        phrases.forEach(phrase => {
            this.commands.set(phrase.toLowerCase(), {
                id: commandId,
                callback,
                options,
                phrases
            });
        });
        
        return commandId;
    }
    
    /**
     * Handle speech recognition result
     */
    handleSpeechResult(event) {
        const transcript = event.results[event.results.length - 1][0].transcript.toLowerCase().trim();
        const confidence = event.results[event.results.length - 1][0].confidence;
        
        console.log(`Voice command: "${transcript}" (confidence: ${confidence})`);
        
        if (confidence < this.settings.confidenceThreshold) {
            this.speak(__('voice.command_not_recognized'));
            return;
        }
        
        // Find matching command
        let commandFound = false;
        for (const [phrase, command] of this.commands) {
            if (this.matchesPhrase(transcript, phrase)) {
                try {
                    command.callback(transcript);
                    this.speak(__('common.success'));
                    commandFound = true;
                    break;
                } catch (error) {
                    console.error('Voice command error:', error);
                    this.speak(__('common.error'));
                }
            }
        }
        
        if (!commandFound) {
            this.speak(__('voice.command_not_recognized'));
        }
        
        if (this.settings.autoStop) {
            this.stop();
        }
    }
    
    /**
     * Check if transcript matches command phrase
     */
    matchesPhrase(transcript, phrase) {
        // Exact match
        if (transcript === phrase) return true;
        
        // Contains phrase
        if (transcript.includes(phrase)) return true;
        
        // Fuzzy match for similar phrases
        const similarity = this.calculateSimilarity(transcript, phrase);
        return similarity > 0.8;
    }
    
    /**
     * Calculate string similarity using Levenshtein distance
     */
    calculateSimilarity(str1, str2) {
        const matrix = [];
        const len1 = str1.length;
        const len2 = str2.length;
        
        if (len1 === 0) return len2 === 0 ? 1 : 0;
        if (len2 === 0) return 0;
        
        for (let i = 0; i <= len2; i++) {
            matrix[i] = [i];
        }
        
        for (let j = 0; j <= len1; j++) {
            matrix[0][j] = j;
        }
        
        for (let i = 1; i <= len2; i++) {
            for (let j = 1; j <= len1; j++) {
                if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(
                        matrix[i - 1][j - 1] + 1,
                        matrix[i][j - 1] + 1,
                        matrix[i - 1][j] + 1
                    );
                }
            }
        }
        
        const distance = matrix[len2][len1];
        const maxLength = Math.max(len1, len2);
        return 1 - (distance / maxLength);
    }
    
    /**
     * Handle speech recognition errors
     */
    handleSpeechError(event) {
        console.error('Speech recognition error:', event.error);
        
        switch (event.error) {
            case 'no-speech':
                this.showStatus(__('voice.no_speech_detected'), 'warning');
                break;
            case 'audio-capture':
                this.showStatus(__('voice.microphone_not_available'), 'error');
                break;
            case 'not-allowed':
                this.showStatus(__('voice.microphone_access'), 'error');
                break;
            case 'network':
                this.showStatus(__('voice.network_error'), 'error');
                break;
            default:
                this.showStatus(__('voice.recognition_error'), 'error');
        }
        
        this.isListening = false;
        this.updateUI();
    }
    
    /**
     * Start voice recognition
     */
    start() {
        if (!this.settings.enabled || !this.recognition) {
            this.showStatus(__('voice.voice_disabled'), 'warning');
            return;
        }
        
        if (this.isListening) {
            this.stop();
            return;
        }
        
        try {
            this.recognition.start();
        } catch (error) {
            console.error('Failed to start voice recognition:', error);
            this.showStatus(__('voice.start_error'), 'error');
        }
    }
    
    /**
     * Stop voice recognition
     */
    stop() {
        if (this.recognition && this.isListening) {
            this.recognition.stop();
        }
    }
    
    /**
     * Enable voice commands
     */
    enable() {
        this.settings.enabled = true;
        localStorage.setItem('armis_voice_enabled', 'true');
        this.updateUI();
        this.speak(__('voice.voice_enabled'));
    }
    
    /**
     * Disable voice commands
     */
    disable() {
        this.settings.enabled = false;
        localStorage.setItem('armis_voice_enabled', 'false');
        this.stop();
        this.updateUI();
        this.speak(__('voice.voice_disabled'));
    }
    
    /**
     * Toggle voice commands
     */
    toggle() {
        if (this.settings.enabled) {
            this.disable();
        } else {
            this.enable();
        }
    }
    
    /**
     * Speak text using speech synthesis
     */
    speak(text, options = {}) {
        if (!this.settings.feedbackEnabled || !this.synthesis) return;
        
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = this.currentLanguage;
        utterance.rate = options.rate || 1;
        utterance.pitch = options.pitch || 1;
        utterance.volume = options.volume || 0.8;
        
        this.synthesis.speak(utterance);
    }
    
    /**
     * Navigation helper
     */
    navigateTo(url) {
        window.location.href = url;
    }
    
    /**
     * Execute form actions
     */
    executeAction(action) {
        const actions = {
            save: () => {
                const saveBtn = document.querySelector('button[type="submit"], .btn-save, #saveBtn');
                if (saveBtn) saveBtn.click();
            },
            cancel: () => {
                const cancelBtn = document.querySelector('.btn-cancel, #cancelBtn');
                if (cancelBtn) {
                    cancelBtn.click();
                } else {
                    history.back();
                }
            },
            reset: () => {
                const form = document.querySelector('form');
                if (form) form.reset();
            }
        };
        
        if (actions[action]) {
            actions[action]();
        }
    }
    
    /**
     * Focus on an element
     */
    focusElement(selector) {
        const element = document.querySelector(selector);
        if (element) {
            element.focus();
            if (element.select) element.select();
        }
    }
    
    /**
     * Toggle high contrast mode
     */
    toggleHighContrast() {
        document.body.classList.toggle('high-contrast');
        const isEnabled = document.body.classList.contains('high-contrast');
        this.speak(isEnabled ? __('common.high_contrast') + ' ' + __('common.enabled') : 
                             __('common.high_contrast') + ' ' + __('common.disabled'));
    }
    
    /**
     * Increase font size
     */
    increaseFontSize() {
        const currentSize = document.documentElement.getAttribute('data-font-size') || 'normal';
        const sizes = ['normal', 'large', 'larger', 'largest'];
        const currentIndex = sizes.indexOf(currentSize);
        const newIndex = Math.min(currentIndex + 1, sizes.length - 1);
        
        document.documentElement.setAttribute('data-font-size', sizes[newIndex]);
        this.speak(__('common.large_text') + ' ' + __('common.enabled'));
    }
    
    /**
     * Reset font size
     */
    resetFontSize() {
        document.documentElement.setAttribute('data-font-size', 'normal');
        this.speak(__('common.normal_text'));
    }
    
    /**
     * Set theme
     */
    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('armis_theme', theme);
        this.speak(`${theme} ${__('common.theme')} ${__('common.enabled')}`);
    }
    
    /**
     * Show voice help
     */
    showVoiceHelp() {
        const helpModal = document.getElementById('voiceHelpModal');
        if (helpModal) {
            const modal = new bootstrap.Modal(helpModal);
            modal.show();
        }
    }
    
    /**
     * Setup UI elements
     */
    setupUI() {
        this.createVoiceButton();
        this.createVoiceIndicator();
        this.createVoiceHelpModal();
    }
    
    /**
     * Create voice activation button
     */
    createVoiceButton() {
        const button = document.createElement('button');
        button.id = 'voiceCommandBtn';
        button.className = 'btn btn-outline-primary btn-sm voice-btn';
        button.setAttribute('aria-label', __('voice.voice_commands'));
        button.innerHTML = `<i class="fas fa-microphone"></i>`;
        button.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1060;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        `;
        
        document.body.appendChild(button);
    }
    
    /**
     * Create voice status indicator
     */
    createVoiceIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'voiceIndicator';
        indicator.className = 'voice-indicator';
        indicator.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1070;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: none;
            font-size: 0.875rem;
        `;
        
        document.body.appendChild(indicator);
    }
    
    /**
     * Create voice help modal
     */
    createVoiceHelpModal() {
        const modal = document.createElement('div');
        modal.id = 'voiceHelpModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${__('voice.voice_commands')}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <h6>${__('navigation.navigation')}</h6>
                        <ul>
                            <li>"Go to dashboard" - ${__('navigation.dashboard')}</li>
                            <li>"Staff management" - ${__('navigation.staff_management')}</li>
                            <li>"Create staff" - ${__('navigation.create_staff')}</li>
                            <li>"Promotions" - ${__('navigation.promotions')}</li>
                            <li>"Reports" - ${__('navigation.reports')}</li>
                        </ul>
                        <h6>${__('common.actions')}</h6>
                        <ul>
                            <li>"Save" - ${__('common.save')}</li>
                            <li>"Cancel" - ${__('common.cancel')}</li>
                            <li>"Search" - ${__('common.search')}</li>
                            <li>"Help" - ${__('common.help')}</li>
                        </ul>
                        <h6>${__('common.accessibility')}</h6>
                        <ul>
                            <li>"High contrast" - ${__('common.high_contrast')}</li>
                            <li>"Large text" - ${__('common.large_text')}</li>
                            <li>"Dark mode" - Dark theme</li>
                            <li>"Field mode" - Tactical theme</li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Voice button click
        document.addEventListener('click', (e) => {
            if (e.target.closest('#voiceCommandBtn')) {
                this.start();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl + Alt + V to toggle voice commands
            if (e.ctrlKey && e.altKey && e.key === 'v') {
                e.preventDefault();
                this.toggle();
            }
            
            // Ctrl + Alt + Space to start listening
            if (e.ctrlKey && e.altKey && e.code === 'Space') {
                e.preventDefault();
                this.start();
            }
        });
    }
    
    /**
     * Update UI based on current state
     */
    updateUI() {
        const button = document.getElementById('voiceCommandBtn');
        if (button) {
            if (this.isListening) {
                button.classList.add('btn-danger');
                button.classList.remove('btn-outline-primary');
                button.querySelector('i').className = 'fas fa-stop';
                button.style.animation = 'pulse 1s infinite';
            } else {
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-primary');
                button.querySelector('i').className = 'fas fa-microphone';
                button.style.animation = '';
            }
            
            button.disabled = !this.settings.enabled;
            button.style.opacity = this.settings.enabled ? '1' : '0.5';
        }
    }
    
    /**
     * Show status message
     */
    showStatus(message, type = 'info') {
        const indicator = document.getElementById('voiceIndicator');
        if (indicator) {
            indicator.textContent = message;
            indicator.className = `voice-indicator voice-${type}`;
            indicator.style.display = 'block';
            
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 3000);
        }
    }
}

// Initialize voice commands when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof ARMISInternationalization !== 'undefined') {
        window.armisVoice = new ARMISVoiceCommands();
    }
});

// Add CSS animation for pulse effect
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .voice-indicator.voice-info { background: rgba(23, 162, 184, 0.9); }
    .voice-indicator.voice-warning { background: rgba(255, 193, 7, 0.9); color: #000; }
    .voice-indicator.voice-error { background: rgba(220, 53, 69, 0.9); }
    .voice-indicator.voice-success { background: rgba(25, 135, 84, 0.9); }
`;
document.head.appendChild(style);