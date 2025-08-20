/**
 * ARMIS Voice Commands Test Suite
 * Tests for voice command functionality and accessibility
 */

// Mock DOM and Web Speech API for testing
global.window = {
    SpeechRecognition: class MockSpeechRecognition {
        constructor() {
            this.continuous = false;
            this.interimResults = false;
            this.lang = 'en-US';
            this.onstart = null;
            this.onend = null;
            this.onresult = null;
            this.onerror = null;
            this.onnomatch = null;
        }
        
        start() {
            if (this.onstart) this.onstart();
        }
        
        stop() {
            if (this.onend) this.onend();
        }
    },
    
    webkitSpeechRecognition: class MockWebkitSpeechRecognition {
        constructor() {
            this.continuous = false;
            this.interimResults = false;
            this.lang = 'en-US';
        }
        
        start() {}
        stop() {}
    },
    
    speechSynthesis: {
        speak: jest.fn(),
        cancel: jest.fn(),
        pause: jest.fn(),
        resume: jest.fn()
    },
    
    SpeechSynthesisUtterance: class MockSpeechSynthesisUtterance {
        constructor(text) {
            this.text = text;
            this.lang = 'en-US';
            this.rate = 1;
            this.pitch = 1;
            this.volume = 1;
        }
    },
    
    location: {
        href: '',
        reload: jest.fn()
    },
    
    localStorage: {
        getItem: jest.fn(),
        setItem: jest.fn(),
        removeItem: jest.fn()
    },
    
    history: {
        back: jest.fn()
    }
};

global.document = {
    addEventListener: jest.fn(),
    querySelector: jest.fn(),
    querySelectorAll: jest.fn(() => []),
    createElement: jest.fn(() => ({
        id: '',
        className: '',
        innerHTML: '',
        style: {},
        setAttribute: jest.fn(),
        addEventListener: jest.fn(),
        click: jest.fn(),
        focus: jest.fn(),
        select: jest.fn()
    })),
    body: {
        appendChild: jest.fn(),
        classList: {
            add: jest.fn(),
            remove: jest.fn(),
            toggle: jest.fn(),
            contains: jest.fn(() => false)
        }
    },
    documentElement: {
        setAttribute: jest.fn(),
        getAttribute: jest.fn()
    },
    head: {
        appendChild: jest.fn()
    },
    getElementById: jest.fn()
};

global.console = {
    log: jest.fn(),
    warn: jest.fn(),
    error: jest.fn()
};

global.bootstrap = {
    Modal: class MockModal {
        constructor() {}
        show() {}
        hide() {}
    }
};

// Mock internationalization function
global.__ = jest.fn((key) => {
    const translations = {
        'voice.listening': 'Listening...',
        'voice.command_not_recognized': 'Command not recognized',
        'voice.voice_disabled': 'Voice commands disabled',
        'voice.voice_enabled': 'Voice commands enabled',
        'common.success': 'Success',
        'common.error': 'Error',
        'common.theme': 'theme',
        'common.enabled': 'enabled'
    };
    return translations[key] || key;
});

// Load the voice commands script
const fs = require('fs');
const path = require('path');
const voiceScript = fs.readFileSync(
    path.join(__dirname, '../../shared/voice-commands.js'),
    'utf8'
);

// Remove the DOMContentLoaded listener for testing
const scriptWithoutDOMListener = voiceScript.replace(
    /document\.addEventListener\('DOMContentLoaded'[\s\S]*?\}\);/,
    ''
);

eval(scriptWithoutDOMListener);

describe('ARMIS Voice Commands', () => {
    let voiceCommands;
    
    beforeEach(() => {
        jest.clearAllMocks();
        voiceCommands = new ARMISVoiceCommands();
    });
    
    afterEach(() => {
        if (voiceCommands) {
            voiceCommands.stop();
        }
    });
    
    describe('Initialization', () => {
        test('should initialize voice commands system', () => {
            expect(voiceCommands).toBeDefined();
            expect(voiceCommands.recognition).toBeDefined();
            expect(voiceCommands.synthesis).toBeDefined();
        });
        
        test('should register default commands', () => {
            expect(voiceCommands.commands.size).toBeGreaterThan(0);
            expect(voiceCommands.commands.has('go to dashboard')).toBe(true);
            expect(voiceCommands.commands.has('save')).toBe(true);
            expect(voiceCommands.commands.has('dark mode')).toBe(true);
        });
        
        test('should setup UI elements', () => {
            expect(document.createElement).toHaveBeenCalledWith('button');
            expect(document.createElement).toHaveBeenCalledWith('div');
            expect(document.body.appendChild).toHaveBeenCalled();
        });
    });
    
    describe('Command Registration', () => {
        test('should register single command phrase', () => {
            const callback = jest.fn();
            const commandId = voiceCommands.registerCommand('test command', callback);
            
            expect(commandId).toBeDefined();
            expect(voiceCommands.commands.has('test command')).toBe(true);
        });
        
        test('should register multiple command phrases', () => {
            const callback = jest.fn();
            const phrases = ['test one', 'test two', 'test three'];
            const commandId = voiceCommands.registerCommand(phrases, callback);
            
            phrases.forEach(phrase => {
                expect(voiceCommands.commands.has(phrase)).toBe(true);
            });
        });
        
        test('should handle command execution', () => {
            const callback = jest.fn();
            voiceCommands.registerCommand('execute test', callback);
            
            // Simulate speech result
            const mockEvent = {
                results: [{
                    0: {
                        transcript: 'execute test',
                        confidence: 0.9
                    }
                }],
                results: {
                    length: 1
                }
            };
            
            voiceCommands.handleSpeechResult(mockEvent);
            expect(callback).toHaveBeenCalled();
        });
    });
    
    describe('Speech Recognition', () => {
        test('should start listening', () => {
            voiceCommands.settings.enabled = true;
            const startSpy = jest.spyOn(voiceCommands.recognition, 'start');
            
            voiceCommands.start();
            expect(startSpy).toHaveBeenCalled();
        });
        
        test('should not start if disabled', () => {
            voiceCommands.settings.enabled = false;
            const startSpy = jest.spyOn(voiceCommands.recognition, 'start');
            
            voiceCommands.start();
            expect(startSpy).not.toHaveBeenCalled();
        });
        
        test('should stop listening', () => {
            voiceCommands.isListening = true;
            const stopSpy = jest.spyOn(voiceCommands.recognition, 'stop');
            
            voiceCommands.stop();
            expect(stopSpy).toHaveBeenCalled();
        });
        
        test('should handle low confidence results', () => {
            const mockEvent = {
                results: [{
                    0: {
                        transcript: 'some command',
                        confidence: 0.3 // Below threshold
                    }
                }],
                results: {
                    length: 1
                }
            };
            
            const speakSpy = jest.spyOn(voiceCommands, 'speak');
            voiceCommands.handleSpeechResult(mockEvent);
            
            expect(speakSpy).toHaveBeenCalledWith('Command not recognized');
        });
    });
    
    describe('Command Matching', () => {
        test('should match exact phrases', () => {
            expect(voiceCommands.matchesPhrase('go to dashboard', 'go to dashboard')).toBe(true);
        });
        
        test('should match contained phrases', () => {
            expect(voiceCommands.matchesPhrase('please go to dashboard now', 'go to dashboard')).toBe(true);
        });
        
        test('should handle fuzzy matching', () => {
            expect(voiceCommands.matchesPhrase('go to dashbord', 'go to dashboard')).toBe(true);
        });
        
        test('should reject very different phrases', () => {
            expect(voiceCommands.matchesPhrase('completely different', 'go to dashboard')).toBe(false);
        });
    });
    
    describe('Navigation Commands', () => {
        test('should handle dashboard navigation', () => {
            const callback = voiceCommands.commands.get('go to dashboard').callback;
            
            callback();
            expect(window.location.href).toBe('/admin_branch/index.php');
        });
        
        test('should handle staff management navigation', () => {
            const callback = voiceCommands.commands.get('go to staff').callback;
            
            callback();
            expect(window.location.href).toBe('/admin_branch/edit_staff.php');
        });
    });
    
    describe('Action Commands', () => {
        test('should handle save action', () => {
            const mockSaveButton = {
                click: jest.fn()
            };
            document.querySelector.mockReturnValue(mockSaveButton);
            
            const callback = voiceCommands.commands.get('save').callback;
            callback();
            
            expect(document.querySelector).toHaveBeenCalledWith('button[type="submit"], .btn-save, #saveBtn');
            expect(mockSaveButton.click).toHaveBeenCalled();
        });
        
        test('should handle cancel action', () => {
            const mockCancelButton = {
                click: jest.fn()
            };
            document.querySelector.mockReturnValue(mockCancelButton);
            
            const callback = voiceCommands.commands.get('cancel').callback;
            callback();
            
            expect(mockCancelButton.click).toHaveBeenCalled();
        });
        
        test('should fall back to history.back() if no cancel button', () => {
            document.querySelector.mockReturnValue(null);
            
            const callback = voiceCommands.commands.get('cancel').callback;
            callback();
            
            expect(window.history.back).toHaveBeenCalled();
        });
    });
    
    describe('Theme Commands', () => {
        test('should handle dark mode command', () => {
            const callback = voiceCommands.commands.get('dark mode').callback;
            
            callback();
            
            expect(document.documentElement.setAttribute).toHaveBeenCalledWith('data-theme', 'dark');
            expect(window.localStorage.setItem).toHaveBeenCalledWith('armis_theme', 'dark');
        });
        
        test('should handle field mode command', () => {
            const callback = voiceCommands.commands.get('field mode').callback;
            
            callback();
            
            expect(document.documentElement.setAttribute).toHaveBeenCalledWith('data-theme', 'field');
        });
    });
    
    describe('Accessibility Commands', () => {
        test('should handle high contrast toggle', () => {
            const callback = voiceCommands.commands.get('high contrast').callback;
            
            callback();
            
            expect(document.body.classList.toggle).toHaveBeenCalledWith('high-contrast');
        });
        
        test('should handle font size increase', () => {
            document.documentElement.getAttribute.mockReturnValue('normal');
            
            const callback = voiceCommands.commands.get('large text').callback;
            callback();
            
            expect(document.documentElement.setAttribute).toHaveBeenCalledWith('data-font-size', 'large');
        });
    });
    
    describe('Settings Management', () => {
        test('should enable voice commands', () => {
            voiceCommands.enable();
            
            expect(voiceCommands.settings.enabled).toBe(true);
            expect(window.localStorage.setItem).toHaveBeenCalledWith('armis_voice_enabled', 'true');
        });
        
        test('should disable voice commands', () => {
            voiceCommands.disable();
            
            expect(voiceCommands.settings.enabled).toBe(false);
            expect(window.localStorage.setItem).toHaveBeenCalledWith('armis_voice_enabled', 'false');
        });
        
        test('should toggle voice commands', () => {
            voiceCommands.settings.enabled = true;
            voiceCommands.toggle();
            expect(voiceCommands.settings.enabled).toBe(false);
            
            voiceCommands.toggle();
            expect(voiceCommands.settings.enabled).toBe(true);
        });
    });
    
    describe('Speech Synthesis', () => {
        test('should speak text when feedback enabled', () => {
            voiceCommands.settings.feedbackEnabled = true;
            
            voiceCommands.speak('Test message');
            
            expect(window.speechSynthesis.speak).toHaveBeenCalled();
        });
        
        test('should not speak when feedback disabled', () => {
            voiceCommands.settings.feedbackEnabled = false;
            
            voiceCommands.speak('Test message');
            
            expect(window.speechSynthesis.speak).not.toHaveBeenCalled();
        });
    });
    
    describe('UI Updates', () => {
        test('should update UI when listening state changes', () => {
            const mockButton = {
                classList: {
                    add: jest.fn(),
                    remove: jest.fn()
                },
                querySelector: jest.fn(() => ({ className: '' })),
                style: {}
            };
            document.getElementById.mockReturnValue(mockButton);
            
            voiceCommands.isListening = true;
            voiceCommands.updateUI();
            
            expect(mockButton.classList.add).toHaveBeenCalledWith('btn-danger');
            expect(mockButton.classList.remove).toHaveBeenCalledWith('btn-outline-primary');
        });
    });
    
    describe('Error Handling', () => {
        test('should handle speech recognition errors gracefully', () => {
            const mockError = { error: 'no-speech' };
            const showStatusSpy = jest.spyOn(voiceCommands, 'showStatus');
            
            voiceCommands.handleSpeechError(mockError);
            
            expect(showStatusSpy).toHaveBeenCalled();
            expect(voiceCommands.isListening).toBe(false);
        });
        
        test('should handle command execution errors', () => {
            const faultyCallback = () => {
                throw new Error('Command failed');
            };
            
            voiceCommands.registerCommand('faulty command', faultyCallback);
            
            const mockEvent = {
                results: [{
                    0: {
                        transcript: 'faulty command',
                        confidence: 0.9
                    }
                }],
                results: {
                    length: 1
                }
            };
            
            const speakSpy = jest.spyOn(voiceCommands, 'speak');
            voiceCommands.handleSpeechResult(mockEvent);
            
            expect(speakSpy).toHaveBeenCalledWith('Error');
        });
    });
});