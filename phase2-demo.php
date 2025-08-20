<?php
/**
 * ARMIS Phase 2 Modernization Demo
 * Showcases all the new features implemented in Phase 2
 */

// Include the enhanced header with all Phase 2 features
$pageTitle = 'Phase 2 Modernization Demo';
require_once 'shared/enhanced-header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">
                    <i class="fas fa-rocket text-primary"></i>
                    <?php _e('demo.phase2_title'); ?>
                </h1>
                <div class="btn-group" role="group" aria-label="<?php _e('demo.feature_controls'); ?>">
                    <button type="button" class="btn btn-outline-primary" onclick="toggleThemeSwitcher()">
                        <i class="fas fa-palette"></i> <?php _e('common.themes'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="toggleAccessibilityPanel()">
                        <i class="fas fa-universal-access"></i> <?php _e('common.accessibility_menu'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="if(window.armisVoice) window.armisVoice.start()">
                        <i class="fas fa-microphone"></i> <?php _e('voice.voice_commands'); ?>
                    </button>
                </div>
            </div>

            <!-- Feature Showcase Grid -->
            <div class="row g-4">
                <!-- Internationalization Demo -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-globe"></i> 
                            <?php _e('demo.internationalization'); ?>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <?php _e('demo.i18n_description'); ?>
                            </p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary btn-sm" onclick="changeLanguage('en')">
                                    🇺🇸 English
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="changeLanguage('fr')">
                                    🇫🇷 Français
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="changeLanguage('ar')">
                                    🇸🇦 العربية (RTL)
                                </button>
                            </div>
                        </div>
                        <div class="card-footer small text-muted">
                            <?php _e('demo.current_language'); ?>: <strong><?php echo $currentLanguage; ?></strong>
                            | <?php _e('demo.text_direction'); ?>: <strong><?php echo $textDirection; ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Military Themes Demo -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-shield-alt"></i> 
                            <?php _e('demo.military_themes'); ?>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <?php _e('demo.themes_description'); ?>
                            </p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <button class="btn btn-outline-secondary btn-sm w-100" data-theme="light">
                                        <div class="theme-preview light d-inline-block me-1"></div>
                                        <?php _e('themes.light'); ?>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-secondary btn-sm w-100" data-theme="dark">
                                        <div class="theme-preview dark d-inline-block me-1"></div>
                                        <?php _e('themes.dark'); ?>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-secondary btn-sm w-100" data-theme="field">
                                        <div class="theme-preview field d-inline-block me-1"></div>
                                        <?php _e('themes.field'); ?>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-secondary btn-sm w-100" data-theme="night">
                                        <div class="theme-preview night d-inline-block me-1"></div>
                                        <?php _e('themes.night'); ?>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-secondary btn-sm w-100" data-theme="desert">
                                        <div class="theme-preview desert d-inline-block me-1"></div>
                                        <?php _e('themes.desert'); ?>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-secondary btn-sm w-100" data-theme="woodland">
                                        <div class="theme-preview woodland d-inline-block me-1"></div>
                                        <?php _e('themes.woodland'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accessibility Features -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-universal-access"></i> 
                            <?php _e('demo.accessibility'); ?>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <?php _e('demo.accessibility_description'); ?>
                            </p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-info btn-sm" onclick="toggleHighContrast()">
                                    <i class="fas fa-adjust"></i> <?php _e('common.high_contrast'); ?>
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="increaseFontSize()">
                                    <i class="fas fa-text-height"></i> <?php _e('common.large_text'); ?>
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="testScreenReader()">
                                    <i class="fas fa-volume-up"></i> <?php _e('demo.screen_reader_test'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="card-footer small text-muted">
                            <div class="d-flex justify-content-between">
                                <span><?php _e('demo.wcag_compliant'); ?></span>
                                <span class="badge bg-success">✓ AA</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Voice Commands Demo -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-dark">
                            <i class="fas fa-microphone"></i> 
                            <?php _e('voice.voice_commands'); ?>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <?php _e('demo.voice_description'); ?>
                            </p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-warning btn-sm" onclick="if(window.armisVoice) window.armisVoice.start()">
                                    <i class="fas fa-play"></i> <?php _e('demo.start_listening'); ?>
                                </button>
                                <button class="btn btn-outline-warning btn-sm" onclick="showVoiceHelp()">
                                    <i class="fas fa-question-circle"></i> <?php _e('demo.voice_commands_help'); ?>
                                </button>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <strong><?php _e('demo.try_saying'); ?>:</strong><br>
                                    "<?php _e('demo.sample_command_1'); ?>"<br>
                                    "<?php _e('demo.sample_command_2'); ?>"<br>
                                    "<?php _e('demo.sample_command_3'); ?>"
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PWA Features -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-danger text-white">
                            <i class="fas fa-mobile-alt"></i> 
                            <?php _e('demo.pwa_features'); ?>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <?php _e('demo.pwa_description'); ?>
                            </p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-danger btn-sm" onclick="testOfflineMode()">
                                    <i class="fas fa-wifi-slash"></i> <?php _e('demo.test_offline'); ?>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="installPWA()">
                                    <i class="fas fa-download"></i> <?php _e('demo.install_app'); ?>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="testPushNotification()">
                                    <i class="fas fa-bell"></i> <?php _e('demo.test_notification'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-secondary text-white">
                            <i class="fas fa-cogs"></i> 
                            <?php _e('demo.system_status'); ?>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 text-center">
                                <div class="col-6">
                                    <div class="p-2 bg-light rounded">
                                        <i class="fas fa-check-circle text-success"></i><br>
                                        <small><?php _e('demo.i18n_status'); ?></small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-light rounded">
                                        <i class="fas fa-check-circle text-success"></i><br>
                                        <small><?php _e('demo.accessibility_status'); ?></small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-light rounded">
                                        <i class="fas fa-check-circle text-success"></i><br>
                                        <small><?php _e('demo.themes_status'); ?></small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-light rounded">
                                        <span class="text-warning" id="voiceStatus">
                                            <i class="fas fa-question-circle"></i>
                                        </span><br>
                                        <small><?php _e('demo.voice_status'); ?></small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-light rounded">
                                        <span class="text-info" id="pwaStatus">
                                            <i class="fas fa-spinner fa-spin"></i>
                                        </span><br>
                                        <small><?php _e('demo.pwa_status'); ?></small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-light rounded">
                                        <span class="text-primary" id="onlineStatus">
                                            <i class="fas fa-wifi"></i>
                                        </span><br>
                                        <small><?php _e('demo.connection_status'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature Details Section -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> 
                                <?php _e('demo.implementation_details'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><?php _e('demo.phase2a_completed'); ?></h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> <?php _e('demo.feature_i18n'); ?></li>
                                        <li><i class="fas fa-check text-success"></i> <?php _e('demo.feature_accessibility'); ?></li>
                                        <li><i class="fas fa-check text-success"></i> <?php _e('demo.feature_themes'); ?></li>
                                        <li><i class="fas fa-check text-success"></i> <?php _e('demo.feature_voice'); ?></li>
                                        <li><i class="fas fa-check text-success"></i> <?php _e('demo.feature_pwa'); ?></li>
                                        <li><i class="fas fa-check text-success"></i> <?php _e('demo.feature_cicd'); ?></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6><?php _e('demo.technical_specs'); ?></h6>
                                    <ul class="list-unstyled">
                                        <li><strong><?php _e('demo.wcag_compliance'); ?>:</strong> 2.1 AA</li>
                                        <li><strong><?php _e('demo.languages_supported'); ?>:</strong> 8</li>
                                        <li><strong><?php _e('demo.themes_available'); ?>:</strong> 7</li>
                                        <li><strong><?php _e('demo.voice_commands'); ?>:</strong> 50+</li>
                                        <li><strong><?php _e('demo.pwa_features'); ?>:</strong> <?php _e('demo.full_offline'); ?></li>
                                        <li><strong><?php _e('demo.mobile_optimized'); ?>:</strong> <?php _e('common.yes'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Demo-specific JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Initialize demo status indicators
    updateSystemStatus();
    
    // Check online/offline status
    updateConnectionStatus();
    window.addEventListener('online', updateConnectionStatus);
    window.addEventListener('offline', updateConnectionStatus);
    
    // Theme switching for demo
    document.querySelectorAll('[data-theme]').forEach(button => {
        button.addEventListener('click', () => {
            const theme = button.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('armis_theme', theme);
            announceToScreenReader(`${theme} theme activated`);
        });
    });
});

function updateSystemStatus() {
    // Check voice command support
    const voiceStatus = document.getElementById('voiceStatus');
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        voiceStatus.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
    } else {
        voiceStatus.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
    }
    
    // Check PWA support
    const pwaStatus = document.getElementById('pwaStatus');
    if ('serviceWorker' in navigator && 'PushManager' in window) {
        pwaStatus.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
    } else {
        pwaStatus.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
    }
}

function updateConnectionStatus() {
    const onlineStatus = document.getElementById('onlineStatus');
    if (navigator.onLine) {
        onlineStatus.innerHTML = '<i class="fas fa-wifi text-success"></i>';
    } else {
        onlineStatus.innerHTML = '<i class="fas fa-wifi-slash text-danger"></i>';
    }
}

function changeLanguage(lang) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'language';
    input.value = lang;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function testScreenReader() {
    announceToScreenReader('Screen reader test: ARMIS Phase 2 modernization features are now active and accessible to all users.');
}

function showVoiceHelp() {
    if (window.armisVoice) {
        window.armisVoice.showVoiceHelp();
    } else {
        alert('Voice commands are not available in this browser.');
    }
}

function testOfflineMode() {
    if ('serviceWorker' in navigator) {
        // Simulate offline test
        alert('To test offline mode:\n1. Open browser dev tools\n2. Go to Application/Network tab\n3. Check "Offline"\n4. Refresh the page\n\nYou will see the offline page with cached content.');
    } else {
        alert('Service Worker not supported in this browser.');
    }
}

function installPWA() {
    if (window.deferredPrompt) {
        window.deferredPrompt.prompt();
        window.deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                announceToScreenReader('ARMIS app installed successfully');
            }
            window.deferredPrompt = null;
        });
    } else {
        alert('App installation prompt not available. You can manually install by using your browser\'s "Install App" option.');
    }
}

function testPushNotification() {
    if ('Notification' in window) {
        if (Notification.permission === 'granted') {
            new Notification('ARMIS Demo', {
                body: 'Push notification test successful!',
                icon: '/assets/icons/icon-192x192.png',
                badge: '/assets/icons/badge-72x72.png',
                tag: 'demo-notification'
            });
        } else if (Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    testPushNotification();
                }
            });
        } else {
            alert('Notifications are blocked. Please enable them in your browser settings.');
        }
    } else {
        alert('Notifications not supported in this browser.');
    }
}

// PWA install prompt handling
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.deferredPrompt = e;
});
</script>

<?php
// Include footer
require_once 'shared/footer.php';
?>