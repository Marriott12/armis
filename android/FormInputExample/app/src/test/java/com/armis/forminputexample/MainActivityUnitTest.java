package com.armis.forminputexample;

import org.junit.Test;
import static org.junit.Assert.*;

/**
 * Unit tests for FormInputExample
 * Tests validation logic and utility functions
 */
public class MainActivityUnitTest {
    
    @Test
    public void testServiceNumberValidation() {
        // Test valid service numbers
        assertTrue(isValidServiceNumber("12345678"));
        assertTrue(isValidServiceNumber("87654321"));
        
        // Test invalid service numbers
        assertFalse(isValidServiceNumber("1234567"));  // Too short
        assertFalse(isValidServiceNumber("123456789")); // Too long
        assertFalse(isValidServiceNumber("1234567a"));  // Contains letter
        assertFalse(isValidServiceNumber(""));          // Empty
        assertFalse(isValidServiceNumber(null));        // Null
    }
    
    @Test
    public void testEmailValidation() {
        // Test valid emails
        assertTrue(isValidEmail("test@example.com"));
        assertTrue(isValidEmail("user.name@domain.org"));
        assertTrue(isValidEmail("admin@military.gov"));
        
        // Test invalid emails
        assertFalse(isValidEmail("invalid-email"));
        assertFalse(isValidEmail("@example.com"));
        assertFalse(isValidEmail("test@"));
        assertFalse(isValidEmail(""));
        assertFalse(isValidEmail(null));
    }
    
    @Test
    public void testPhoneNumberValidation() {
        // Test valid phone numbers
        assertTrue(isValidPhone("555-123-4567"));
        assertTrue(isValidPhone("+1-555-123-4567"));
        assertTrue(isValidPhone("(555) 123-4567"));
        assertTrue(isValidPhone("5551234567"));
        
        // Test invalid phone numbers
        assertFalse(isValidPhone("123"));          // Too short
        assertFalse(isValidPhone("abc-def-ghij")); // Contains letters
        assertFalse(isValidPhone(""));             // Empty
        assertFalse(isValidPhone(null));           // Null
    }
    
    @Test
    public void testWebsiteValidation() {
        // Test valid URLs
        assertTrue(isValidWebsite("https://example.com"));
        assertTrue(isValidWebsite("http://test.org"));
        assertTrue(isValidWebsite("https://www.military.gov"));
        
        // Test invalid URLs
        assertFalse(isValidWebsite("not-a-url"));
        assertFalse(isValidWebsite("ftp://example.com")); // Wrong protocol
        assertFalse(isValidWebsite(""));
        assertFalse(isValidWebsite(null));
    }
    
    // Helper methods that would exist in MainActivity
    private boolean isValidServiceNumber(String serviceNumber) {
        if (serviceNumber == null || serviceNumber.trim().isEmpty()) {
            return false;
        }
        String trimmed = serviceNumber.trim();
        return trimmed.length() == 8 && trimmed.matches("\\d+");
    }
    
    private boolean isValidEmail(String email) {
        if (email == null || email.trim().isEmpty()) {
            return false;
        }
        return android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches();
    }
    
    private boolean isValidPhone(String phone) {
        if (phone == null || phone.trim().isEmpty()) {
            return false;
        }
        return android.util.Patterns.PHONE.matcher(phone).matches();
    }
    
    private boolean isValidWebsite(String website) {
        if (website == null || website.trim().isEmpty()) {
            return false;
        }
        return android.util.Patterns.WEB_URL.matcher(website).matches() &&
               (website.startsWith("http://") || website.startsWith("https://"));
    }
}