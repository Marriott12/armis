<?php

use PHPUnit\Framework\TestCase;

/**
 * Basic test for ARMIS Internationalization system
 * Tests the core functionality of the i18n framework
 */
class InternationalizationTest extends TestCase
{
    private $i18n;
    
    protected function setUp(): void
    {
        // Start session for testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Include the i18n system
        require_once dirname(dirname(__DIR__)) . '/shared/i18n.php';
        $this->i18n = ARMISInternationalization::getInstance();
    }
    
    public function testGetInstance()
    {
        $this->assertInstanceOf(ARMISInternationalization::class, $this->i18n);
    }
    
    public function testDefaultLanguageIsEnglish()
    {
        $this->assertEquals('en', $this->i18n->getCurrentLanguage());
    }
    
    public function testBasicTranslation()
    {
        $translation = $this->i18n->translate('common.welcome');
        $this->assertEquals('Welcome', $translation);
    }
    
    public function testTranslationWithParameters()
    {
        // This would work if we had a translation with parameters
        $translation = $this->i18n->translate('common.welcome');
        $this->assertIsString($translation);
    }
    
    public function testLanguageSwitching()
    {
        $result = $this->i18n->setLanguage('fr');
        $this->assertTrue($result);
        $this->assertEquals('fr', $this->i18n->getCurrentLanguage());
        
        // Test French translation
        $translation = $this->i18n->translate('common.welcome');
        $this->assertEquals('Bienvenue', $translation);
    }
    
    public function testInvalidLanguage()
    {
        $result = $this->i18n->setLanguage('invalid');
        $this->assertFalse($result);
        // Should remain on previous language
        $this->assertEquals('fr', $this->i18n->getCurrentLanguage());
    }
    
    public function testTextDirection()
    {
        $this->i18n->setLanguage('en');
        $this->assertEquals('ltr', $this->i18n->getTextDirection());
        
        $this->i18n->setLanguage('ar');
        $this->assertEquals('rtl', $this->i18n->getTextDirection());
    }
    
    public function testIsRTL()
    {
        $this->i18n->setLanguage('en');
        $this->assertFalse($this->i18n->isRTL());
        
        $this->i18n->setLanguage('ar');
        $this->assertTrue($this->i18n->isRTL());
    }
    
    public function testSupportedLanguages()
    {
        $languages = $this->i18n->getSupportedLanguages();
        $this->assertIsArray($languages);
        $this->assertArrayHasKey('en', $languages);
        $this->assertArrayHasKey('fr', $languages);
        $this->assertArrayHasKey('ar', $languages);
    }
    
    public function testLanguageConfig()
    {
        $config = $this->i18n->getLanguageConfig('en');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('direction', $config);
        $this->assertArrayHasKey('military_standard', $config);
    }
    
    public function testGlobalTranslationFunction()
    {
        $translation = __('common.welcome');
        $this->assertIsString($translation);
    }
    
    public function testMilitaryStandard()
    {
        $this->i18n->setLanguage('en');
        $standard = $this->i18n->getMilitaryStandard();
        $this->assertEquals('NATO', $standard);
        
        $this->i18n->setLanguage('ar');
        $standard = $this->i18n->getMilitaryStandard();
        $this->assertEquals('Arab League', $standard);
    }
    
    public function testLanguageSwitcherHTML()
    {
        $html = $this->i18n->generateLanguageSwitcher();
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('languageSwitcher', $html);
        $this->assertStringContainsString('English', $html);
        $this->assertStringContainsString('Français', $html);
    }
    
    public function testHTMLAttributes()
    {
        $this->i18n->setLanguage('en');
        $attributes = $this->i18n->getHtmlAttributes();
        $this->assertEquals('en', $attributes['lang']);
        $this->assertEquals('ltr', $attributes['dir']);
        
        $this->i18n->setLanguage('ar');
        $attributes = $this->i18n->getHtmlAttributes();
        $this->assertEquals('ar', $attributes['lang']);
        $this->assertEquals('rtl', $attributes['dir']);
    }
    
    protected function tearDown(): void
    {
        // Clean up session
        $_SESSION = [];
    }
}