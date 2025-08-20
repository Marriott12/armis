<?php
/**
 * ARMIS Enhanced Header - Phase 2 Modernization
 * Includes accessibility, internationalization, PWA, and voice commands
 */

// Start session with enhanced security
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Include Phase 2 modernization features
require_once dirname(__DIR__) . '/shared/i18n.php';

// Initialize internationalization
$i18n = ARMISInternationalization::getInstance();

// Get HTML attributes for accessibility and RTL support
$htmlAttributes = $i18n->getHtmlAttributes();
$currentLanguage = $i18n->getCurrentLanguage();
$textDirection = $i18n->getTextDirection();
$isRTL = $i18n->isRTL();

// Set page title with translation support
$pageTitle = isset($pageTitle) ? $pageTitle : __('navigation.dashboard');
$systemName = __('common.system_name') ?: 'ARMIS - Army Resource Management Information System';

// Security headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy for enhanced security
$csp = "default-src 'self'; " .
// Generate a nonce for inline scripts
$nonce = bin2hex(random_bytes(16));
// Content Security Policy for enhanced security
$csp = "default-src 'self'; " .
       "script-src 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
       "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
       "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
       "img-src 'self' data: https:; " .
       "connect-src 'self'; " .
       "media-src 'self'; " .
       "object-src 'none'; " .
       "base-uri 'self'; " .
       "form-action 'self'";
header("Content-Security-Policy: $csp");
?>
<!DOCTYPE html>
<html lang="<?php echo $htmlAttributes['lang']; ?>" dir="<?php echo $htmlAttributes['dir']; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" content="<?php _e('common.system_description'); ?>">
    <meta name="keywords" content="military, army, personnel, management, resource, system">
    <meta name="author" content="ARMIS Development Team">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Security Meta Tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#2d5a27">
    <meta name="application-name" content="ARMIS">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ARMIS">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-TileColor" content="#2d5a27">
    <meta name="msapplication-tap-highlight" content="no">
    
    <!-- Title with i18n support -->
    <title><?php echo htmlspecialchars($systemName . ' - ' . $pageTitle); ?></title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/icon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/assets/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/assets/icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/assets/icons/icon-120x120.png">
    
    <!-- CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- ARMIS Phase 2 Enhanced Styles -->
    <link rel="stylesheet" href="/shared/armis-styles.css">
    <link rel="stylesheet" href="/shared/accessibility.css">
    <link rel="stylesheet" href="/shared/military-themes.css">
    <link rel="stylesheet" href="/shared/mobile-responsive.css">
    
    <!-- RTL Support for Arabic and other RTL languages -->
    <?php if ($isRTL): ?>
    <link rel="stylesheet" href="/shared/rtl-support.css">
    <?php endif; ?>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="/shared/voice-commands.js" as="script">
    <link rel="preload" href="/shared/dashboard-utils.js" as="script">
    
    <!-- DNS Prefetch for external resources -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    
    <style>
        /* Critical CSS for immediate rendering */
        :root {
            --armis-primary: #2d5a27;
            --armis-secondary: #4a7c59;
            --armis-gold: #ffd700;
            --armis-dark: #1a3d1a;
        }
        
        /* Loading spinner for service worker */
        .sw-loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            display: none;
        }
        
        /* Skip links for accessibility */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--armis-primary);
            color: white;
            padding: 8px;
            text-decoration: none;
            border-radius: 4px;
            z-index: 9999;
            transition: top 0.2s ease;
        }
        
        .skip-link:focus {
            top: 6px;
        }
        
        /* Theme transition */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Skip Links for Accessibility -->
    <a href="#main-content" class="skip-link"><?php _e('common.skip_to_content'); ?></a>
    <a href="#main-navigation" class="skip-link"><?php _e('common.skip_to_navigation'); ?></a>
    
    <!-- Service Worker Loading Indicator -->
    <div class="sw-loading" id="swLoading">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only"><?php _e('common.loading'); ?></span>
        </div>
    </div>
    
    <!-- Language Switcher -->
    <div class="position-fixed top-0 end-0 m-3" style="z-index: 1060;">
        <?php echo $i18n->generateLanguageSwitcher(); ?>
    </div>
    
    <!-- Theme Switcher -->
    <div class="theme-switcher" id="themeSwitcher" style="display: none;">
        <h6><?php _e('common.themes'); ?></h6>
        <div class="theme-option" data-theme="light">
            <div class="theme-preview light"></div>
            <?php _e('themes.light'); ?>
        </div>
        <div class="theme-option" data-theme="dark">
            <div class="theme-preview dark"></div>
            <?php _e('themes.dark'); ?>
        </div>
        <div class="theme-option" data-theme="field">
            <div class="theme-preview field"></div>
            <?php _e('themes.field'); ?>
        </div>
        <div class="theme-option" data-theme="night">
            <div class="theme-preview night"></div>
            <?php _e('themes.night'); ?>
        </div>
        <div class="theme-option" data-theme="desert">
            <div class="theme-preview desert"></div>
            <?php _e('themes.desert'); ?>
        </div>
        <div class="theme-option" data-theme="woodland">
            <div class="theme-preview woodland"></div>
            <?php _e('themes.woodland'); ?>
        </div>
        <div class="theme-option" data-theme="urban">
            <div class="theme-preview urban"></div>
            <?php _e('themes.urban'); ?>
        </div>
    </div>
    
    <!-- Accessibility Panel -->
    <div class="accessibility-panel" id="accessibilityPanel" style="display: none;">
        <h6><?php _e('common.accessibility_menu'); ?></h6>
        <button class="btn btn-sm btn-outline-primary" onclick="toggleHighContrast()">
            <?php _e('common.high_contrast'); ?>
        </button>
        <button class="btn btn-sm btn-outline-primary" onclick="increaseFontSize()">
            <?php _e('common.large_text'); ?>
        </button>
        <button class="btn btn-sm btn-outline-primary" onclick="toggleVoiceCommands()">
            <?php _e('voice.voice_commands'); ?>
        </button>
    </div>
    
    <!-- Live Region for Screen Readers -->
    <div id="ariaLiveRegion" class="sr-only" aria-live="polite" aria-atomic="true"></div>
    
    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg" id="main-navigation" role="navigation" 
         aria-label="<?php _e('navigation.main_menu'); ?>">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="/" aria-label="<?php _e('common.home'); ?>">
                <img src="/logo.png" alt="<?php _e('common.armis_logo'); ?>" height="30" class="me-2" 
                     onerror="this.style.display='none'">
                <span class="system-title"><?php echo htmlspecialchars($systemName); ?></span>
            </a>
            
            <!-- Mobile Navigation Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="<?php _e('navigation.toggle_navigation'); ?>">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Navigation will be populated by specific pages -->
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <main id="main-content" role="main" tabindex="-1">
        
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
            crossorigin="anonymous"></script>
    
    <!-- ARMIS Phase 2 JavaScript -->
    <script src="/shared/dashboard-utils.js" defer></script>
    <script src="/shared/voice-commands.js" defer></script>
    
    <!-- PWA Service Worker Registration -->
    <script>
        // Service Worker Registration for PWA functionality
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('ServiceWorker registered:', registration.scope);
                    
                    // Listen for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New version available
                                if (confirm('<?php _e("pwa.update_available"); ?>')) {
                                    newWorker.postMessage({ type: 'SKIP_WAITING' });
                                    window.location.reload();
                                }
                            }
                        });
                    });
                })
                .catch(error => {
                    console.error('ServiceWorker registration failed:', error);
                });
            });
        }
        
        // Apply saved theme
        const savedTheme = localStorage.getItem('armis_theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
        
        // Apply saved accessibility settings
        const fontSize = localStorage.getItem('armis_font_size');
        if (fontSize) {
            document.documentElement.setAttribute('data-font-size', fontSize);
        }
        
        // Language switcher functionality
        document.addEventListener('DOMContentLoaded', () => {
            const languageSwitcher = document.getElementById('languageSwitcher');
            if (languageSwitcher) {
                languageSwitcher.addEventListener('change', (e) => {
                    const newLanguage = e.target.value;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'language';
                    input.value = newLanguage;
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                });
            }
            
            // Theme switcher functionality
            document.querySelectorAll('.theme-option').forEach(option => {
                option.addEventListener('click', () => {
                    const theme = option.getAttribute('data-theme');
                    document.documentElement.setAttribute('data-theme', theme);
                    localStorage.setItem('armis_theme', theme);
                    
                    // Update active state
                    document.querySelectorAll('.theme-option').forEach(opt => 
                        opt.classList.remove('active'));
                    option.classList.add('active');
                    
                    // Announce to screen readers
                    announceToScreenReader(`${theme} theme activated`);
                });
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Ctrl + Alt + T for theme switcher
                if (e.ctrlKey && e.altKey && e.key === 't') {
                    e.preventDefault();
                    toggleThemeSwitcher();
                }
                
                // Ctrl + Alt + A for accessibility panel
                if (e.ctrlKey && e.altKey && e.key === 'a') {
                    e.preventDefault();
                    toggleAccessibilityPanel();
                }
                
                // Ctrl + Alt + L for language switcher
                if (e.ctrlKey && e.altKey && e.key === 'l') {
                    e.preventDefault();
                    document.getElementById('languageSwitcher')?.focus();
                }
            });
        });
        
        // Utility functions
        function toggleThemeSwitcher() {
            const switcher = document.getElementById('themeSwitcher');
            switcher.style.display = switcher.style.display === 'none' ? 'block' : 'none';
        }
        
        function toggleAccessibilityPanel() {
            const panel = document.getElementById('accessibilityPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
        
        function toggleHighContrast() {
            document.body.classList.toggle('high-contrast');
            const isEnabled = document.body.classList.contains('high-contrast');
            announceToScreenReader(`High contrast ${isEnabled ? 'enabled' : 'disabled'}`);
        }
        
        function increaseFontSize() {
            const currentSize = document.documentElement.getAttribute('data-font-size') || 'normal';
            const sizes = ['normal', 'large', 'larger', 'largest'];
            const currentIndex = sizes.indexOf(currentSize);
            const newIndex = Math.min(currentIndex + 1, sizes.length - 1);
            
            document.documentElement.setAttribute('data-font-size', sizes[newIndex]);
            localStorage.setItem('armis_font_size', sizes[newIndex]);
            announceToScreenReader(`Font size: ${sizes[newIndex]}`);
        }
        
        function toggleVoiceCommands() {
            if (window.armisVoice) {
                window.armisVoice.toggle();
            }
        }
        
        function announceToScreenReader(message) {
            const liveRegion = document.getElementById('ariaLiveRegion');
            if (liveRegion) {
                liveRegion.textContent = message;
                setTimeout(() => {
                    liveRegion.textContent = '';
                }, 1000);
            }
        }
        
        // Handle language switching via POST
        <?php if (isset($_POST['language']) && $i18n->setLanguage($_POST['language'])): ?>
        // Language changed, reload page
        window.location.reload();
        <?php endif; ?>
    </script>