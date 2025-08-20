<?php
/**
 * ARMIS Internationalization Framework
 * Multi-language support for international military units
 * Supports WCAG 2.1 AA compliance with RTL languages
 */

class ARMISInternationalization {
    private static $instance = null;
    private $currentLanguage = 'en';
    private $supportedLanguages = [];
    private $translations = [];
    private $fallbackLanguage = 'en';
    private $textDirection = 'ltr';
    
    /**
     * Supported languages with their configurations
     */
    private $languageConfig = [
        'en' => [
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
            'locale' => 'en_US',
            'flag' => '🇺🇸',
            'military_standard' => 'NATO'
        ],
        'fr' => [
            'name' => 'French',
            'native_name' => 'Français',
            'direction' => 'ltr',
            'locale' => 'fr_FR',
            'flag' => '🇫🇷',
            'military_standard' => 'NATO'
        ],
        'de' => [
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
            'locale' => 'de_DE',
            'flag' => '🇩🇪',
            'military_standard' => 'NATO'
        ],
        'es' => [
            'name' => 'Spanish',
            'native_name' => 'Español',
            'direction' => 'ltr',
            'locale' => 'es_ES',
            'flag' => '🇪🇸',
            'military_standard' => 'NATO'
        ],
        'ar' => [
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'direction' => 'rtl',
            'locale' => 'ar_SA',
            'flag' => '🇸🇦',
            'military_standard' => 'Arab League'
        ],
        'zh' => [
            'name' => 'Chinese',
            'native_name' => '中文',
            'direction' => 'ltr',
            'locale' => 'zh_CN',
            'flag' => '🇨🇳',
            'military_standard' => 'PLA'
        ],
        'ja' => [
            'name' => 'Japanese',
            'native_name' => '日本語',
            'direction' => 'ltr',
            'locale' => 'ja_JP',
            'flag' => '🇯🇵',
            'military_standard' => 'JSDF'
        ],
        'ko' => [
            'name' => 'Korean',
            'native_name' => '한국어',
            'direction' => 'ltr',
            'locale' => 'ko_KR',
            'flag' => '🇰🇷',
            'military_standard' => 'ROK'
        ]
    ];
    
    private function __construct() {
        $this->initializeLanguages();
        $this->detectLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize supported languages from configuration
     */
    private function initializeLanguages() {
        $this->supportedLanguages = array_keys($this->languageConfig);
    }
    
    /**
     * Detect user's preferred language
     */
    private function detectLanguage() {
        // Check session/cookie first
        if (isset($_SESSION['armis_language']) && $this->isLanguageSupported($_SESSION['armis_language'])) {
            $this->currentLanguage = $_SESSION['armis_language'];
            return;
        }
        
        // Check browser language
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLanguages = $this->parseBrowserLanguages($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($browserLanguages as $lang) {
                if ($this->isLanguageSupported($lang)) {
                    $this->currentLanguage = $lang;
                    return;
                }
            }
        }
        
        // Fall back to default
        $this->currentLanguage = $this->fallbackLanguage;
    }
    
    /**
     * Parse browser Accept-Language header
     */
    private function parseBrowserLanguages($acceptLanguage) {
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, ';') !== false) {
                list($lang, $q) = explode(';', $part, 2);
                $lang = trim($lang);
            } else {
                $lang = $part;
            }
            
            // Extract language code (e.g., 'en' from 'en-US')
            if (strpos($lang, '-') !== false) {
                $lang = substr($lang, 0, strpos($lang, '-'));
            }
            
            $languages[] = strtolower($lang);
        }
        
        return array_unique($languages);
    }
    
    /**
     * Check if language is supported
     */
    private function isLanguageSupported($language) {
        return in_array($language, $this->supportedLanguages);
    }
    
    /**
     * Load translations for current language
     */
    private function loadTranslations() {
        $translationFile = __DIR__ . "/translations/{$this->currentLanguage}.php";
        
        if (file_exists($translationFile)) {
            $this->translations = include $translationFile;
        } else {
            // Load fallback translations
            $fallbackFile = __DIR__ . "/translations/{$this->fallbackLanguage}.php";
            if (file_exists($fallbackFile)) {
                $this->translations = include $fallbackFile;
            }
        }
        
        // Set text direction
        $this->textDirection = $this->languageConfig[$this->currentLanguage]['direction'] ?? 'ltr';
    }
    
    /**
     * Translate a string
     */
    public function translate($key, $params = []) {
        $translation = $this->getNestedTranslation($key);
        
        if ($translation === null) {
            // Log missing translation
            error_log("Missing translation for key: {$key} in language: {$this->currentLanguage}");
            return $key; // Return the key as fallback
        }
        
        // Replace parameters in translation
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $translation = str_replace('{' . $param . '}', $value, $translation);
            }
        }
        
        return $translation;
    }
    
    /**
     * Get nested translation using dot notation
     */
    private function getNestedTranslation($key) {
        $keys = explode('.', $key);
        $translation = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($translation[$k])) {
                $translation = $translation[$k];
            } else {
                return null;
            }
        }
        
        return is_string($translation) ? $translation : null;
    }
    
    /**
     * Set current language
     */
    public function setLanguage($language) {
        if ($this->isLanguageSupported($language)) {
            $this->currentLanguage = $language;
            $_SESSION['armis_language'] = $language;
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }
    
    /**
     * Get text direction for current language
     */
    public function getTextDirection() {
        return $this->textDirection;
    }
    
    /**
     * Get all supported languages
     */
    public function getSupportedLanguages() {
        return $this->languageConfig;
    }
    
    /**
     * Get language configuration
     */
    public function getLanguageConfig($language = null) {
        $language = $language ?: $this->currentLanguage;
        return $this->languageConfig[$language] ?? null;
    }
    
    /**
     * Format date according to current locale
     */
    public function formatDate($date, $format = 'medium') {
        $config = $this->getLanguageConfig();
        $locale = $config['locale'] ?? 'en_US';
        
        if ($date instanceof DateTime) {
            $timestamp = $date->getTimestamp();
        } elseif (is_string($date)) {
            $timestamp = strtotime($date);
        } else {
            $timestamp = $date;
        }
        
        // Format according to locale
        $fmt = new IntlDateFormatter($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
        return $fmt->format($timestamp);
    }
    
    /**
     * Format number according to current locale
     */
    public function formatNumber($number, $decimals = 0) {
        $config = $this->getLanguageConfig();
        $locale = $config['locale'] ?? 'en_US';
        
        $fmt = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        // Check if intl extension is loaded and NumberFormatter exists
        if (extension_loaded('intl') && class_exists('NumberFormatter')) {
            try {
                $fmt = new NumberFormatter($locale, NumberFormatter::DECIMAL);
                $result = $fmt->format($number);
                if ($result !== false) {
                    return $result;
                }
            } catch (\Exception $e) {
                // Fall through to fallback
            }
        }
        // Fallback: use PHP's number_format
        return number_format($number, $decimals, '.', ',');
    }
    
    /**
     * Generate language switcher HTML
     */
    public function generateLanguageSwitcher() {
        $html = '<div class="language-switcher" role="navigation" aria-label="' . $this->translate('common.language_switcher') . '">';
        $html .= '<select class="form-select form-select-sm" id="languageSwitcher" aria-label="' . $this->translate('common.select_language') . '">';
        
        foreach ($this->languageConfig as $code => $config) {
            $selected = ($code === $this->currentLanguage) ? 'selected' : '';
            $html .= sprintf(
                '<option value="%s" %s>%s %s</option>',
                $code,
                $selected,
                $config['flag'],
                $config['native_name']
            );
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get HTML attributes for current language
     */
    public function getHtmlAttributes() {
        return [
            'lang' => $this->currentLanguage,
            'dir' => $this->textDirection
        ];
    }
    
    /**
     * Check if current language is RTL
     */
    public function isRTL() {
        return $this->textDirection === 'rtl';
    }
    
    /**
     * Get military standard for current language
     */
    public function getMilitaryStandard() {
        $config = $this->getLanguageConfig();
        return $config['military_standard'] ?? 'NATO';
    }
}

/**
 * Global translation function
 */
function __($key, $params = []) {
    return ARMISInternationalization::getInstance()->translate($key, $params);
}

/**
 * Echo translation function
 */
function _e($key, $params = []) {
    echo __($key, $params);
}

/**
 * Get current language function
 */
function getCurrentLanguage() {
    return ARMISInternationalization::getInstance()->getCurrentLanguage();
}

/**
 * Set language function
 */
function setLanguage($language) {
    return ARMISInternationalization::getInstance()->setLanguage($language);
}

/**
 * Get text direction function
 */
function getTextDirection() {
    return ARMISInternationalization::getInstance()->getTextDirection();
}

/**
 * Check if RTL function
 */
function isRTL() {
    return ARMISInternationalization::getInstance()->isRTL();
}