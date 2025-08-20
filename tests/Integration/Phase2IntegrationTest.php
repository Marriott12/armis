<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration test for ARMIS Phase 2 modernization features
 * Tests the integration between i18n, themes, and accessibility
 */
class Phase2IntegrationTest extends TestCase
{
    private $i18n;
    
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        require_once dirname(dirname(__DIR__)) . '/shared/i18n.php';
        $this->i18n = ARMISInternationalization::getInstance();
    }
    
    public function testMultiLanguageSystemIntegration()
    {
        // Test English (default)
        $this->assertEquals('en', $this->i18n->getCurrentLanguage());
        $this->assertEquals('Welcome', $this->i18n->translate('common.welcome'));
        $this->assertEquals('ltr', $this->i18n->getTextDirection());
        $this->assertEquals('NATO', $this->i18n->getMilitaryStandard());
        
        // Test French
        $this->assertTrue($this->i18n->setLanguage('fr'));
        $this->assertEquals('fr', $this->i18n->getCurrentLanguage());
        $this->assertEquals('Bienvenue', $this->i18n->translate('common.welcome'));
        $this->assertEquals('ltr', $this->i18n->getTextDirection());
        $this->assertEquals('NATO', $this->i18n->getMilitaryStandard());
        
        // Test Arabic (RTL)
        $this->assertTrue($this->i18n->setLanguage('ar'));
        $this->assertEquals('ar', $this->i18n->getCurrentLanguage());
        $this->assertEquals('rtl', $this->i18n->getTextDirection());
        $this->assertTrue($this->i18n->isRTL());
        $this->assertEquals('Arab League', $this->i18n->getMilitaryStandard());
    }
    
    public function testAccessibilityIntegration()
    {
        // Test HTML attributes for accessibility
        $this->i18n->setLanguage('en');
        $attributes = $this->i18n->getHtmlAttributes();
        $this->assertArrayHasKey('lang', $attributes);
        $this->assertArrayHasKey('dir', $attributes);
        $this->assertEquals('en', $attributes['lang']);
        $this->assertEquals('ltr', $attributes['dir']);
        
        // Test RTL attributes
        $this->i18n->setLanguage('ar');
        $attributes = $this->i18n->getHtmlAttributes();
        $this->assertEquals('ar', $attributes['lang']);
        $this->assertEquals('rtl', $attributes['dir']);
    }
    
    public function testVoiceCommandTranslations()
    {
        // Test voice command translations
        $this->i18n->setLanguage('en');
        $this->assertEquals('Voice Commands', $this->i18n->translate('voice.voice_commands'));
        $this->assertEquals('Listening...', $this->i18n->translate('voice.listening'));
        
        $this->i18n->setLanguage('fr');
        $this->assertEquals('Commandes vocales', $this->i18n->translate('voice.voice_commands'));
        $this->assertEquals('Écoute...', $this->i18n->translate('voice.listening'));
    }
    
    public function testMilitaryNavigationTranslations()
    {
        // Test military navigation terms
        $this->i18n->setLanguage('en');
        $this->assertEquals('Dashboard', $this->i18n->translate('navigation.dashboard'));
        $this->assertEquals('Staff Management', $this->i18n->translate('navigation.staff_management'));
        $this->assertEquals('Operations', $this->i18n->translate('navigation.operations'));
        
        $this->i18n->setLanguage('fr');
        $this->assertEquals('Tableau de bord', $this->i18n->translate('navigation.dashboard'));
        $this->assertEquals('Gestion du personnel', $this->i18n->translate('navigation.staff_management'));
        $this->assertEquals('Opérations', $this->i18n->translate('navigation.operations'));
    }
    
    public function testLanguageSwitcherGeneration()
    {
        $html = $this->i18n->generateLanguageSwitcher();
        
        // Check basic structure
        $this->assertStringContainsString('<div class="language-switcher"', $html);
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('id="languageSwitcher"', $html);
        
        // Check accessibility attributes
        $this->assertStringContainsString('role="navigation"', $html);
        $this->assertStringContainsString('aria-label=', $html);
        
        // Check language options
        $this->assertStringContainsString('🇺🇸', $html); // US flag
        $this->assertStringContainsString('🇫🇷', $html); // French flag
        $this->assertStringContainsString('🇸🇦', $html); // Saudi flag for Arabic
        $this->assertStringContainsString('English', $html);
        $this->assertStringContainsString('Français', $html);
        $this->assertStringContainsString('العربية', $html);
    }
    
    public function testGlobalFunctionIntegration()
    {
        // Test global translation functions
        $this->i18n->setLanguage('en');
        $this->assertEquals('Welcome', __('common.welcome'));
        $this->assertEquals('en', getCurrentLanguage());
        $this->assertEquals('ltr', getTextDirection());
        $this->assertFalse(isRTL());
        
        // Test language switching through global function
        $this->assertTrue(setLanguage('ar'));
        $this->assertEquals('ar', getCurrentLanguage());
        $this->assertEquals('rtl', getTextDirection());
        $this->assertTrue(isRTL());
    }
    
    public function testParameterizedTranslations()
    {
        $this->i18n->setLanguage('en');
        
        // This would work with parameterized translations
        $translation = $this->i18n->translate('common.welcome');
        $this->assertIsString($translation);
        $this->assertNotEmpty($translation);
    }
    
    public function testMissingTranslationHandling()
    {
        $this->i18n->setLanguage('en');
        
        // Test missing translation key
        $result = $this->i18n->translate('nonexistent.key');
        $this->assertEquals('nonexistent.key', $result);
    }
    
    public function testDateAndNumberFormatting()
    {
        $this->i18n->setLanguage('en');
        
        // Test date formatting
        $date = new DateTime('2024-01-15');
        $formatted = $this->i18n->formatDate($date);
        $this->assertIsString($formatted);
        
        // Test number formatting
        $number = 1234.56;
        $formatted = $this->i18n->formatNumber($number);
        $this->assertIsString($formatted);
    }
    
    protected function tearDown(): void
    {
        $_SESSION = [];
    }
}