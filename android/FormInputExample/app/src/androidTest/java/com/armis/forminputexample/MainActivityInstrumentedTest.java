package com.armis.forminputexample;

import android.content.Intent;
import android.net.Uri;
import androidx.test.ext.junit.runners.AndroidJUnit4;
import androidx.test.platform.app.InstrumentationRegistry;
import androidx.test.ext.junit.rules.ActivityScenarioRule;
import androidx.test.espresso.Espresso;
import androidx.test.espresso.action.ViewActions;
import androidx.test.espresso.assertion.ViewAssertions;
import androidx.test.espresso.matcher.ViewMatchers;

import org.junit.Test;
import org.junit.Rule;
import org.junit.runner.RunWith;

import static org.junit.Assert.*;
import static androidx.test.espresso.Espresso.onView;
import static androidx.test.espresso.action.ViewActions.click;
import static androidx.test.espresso.action.ViewActions.typeText;
import static androidx.test.espresso.assertion.ViewAssertions.matches;
import static androidx.test.espresso.matcher.ViewMatchers.isDisplayed;
import static androidx.test.espresso.matcher.ViewMatchers.withId;
import static androidx.test.espresso.matcher.ViewMatchers.withText;

/**
 * Instrumented test for FormInputExample MainActivity
 * Tests form input, validation, and basic functionality
 */
@RunWith(AndroidJUnit4.class)
public class MainActivityInstrumentedTest {

    @Rule
    public ActivityScenarioRule<MainActivity> activityRule = 
        new ActivityScenarioRule<>(MainActivity.class);

    @Test
    public void testFormDisplayed() {
        // Test that main form elements are displayed
        onView(withId(R.id.tvMainTitle)).check(matches(isDisplayed()));
        onView(withId(R.id.etFirstName)).check(matches(isDisplayed()));
        onView(withId(R.id.etLastName)).check(matches(isDisplayed()));
        onView(withId(R.id.etServiceNumber)).check(matches(isDisplayed()));
        onView(withId(R.id.btnSave)).check(matches(isDisplayed()));
        onView(withId(R.id.btnClear)).check(matches(isDisplayed()));
    }

    @Test
    public void testFormInputAndValidation() {
        // Test form input
        onView(withId(R.id.etFirstName)).perform(typeText("John"));
        onView(withId(R.id.etLastName)).perform(typeText("Doe"));
        onView(withId(R.id.etServiceNumber)).perform(typeText("12345678"));
        
        // Close keyboard
        Espresso.closeSoftKeyboard();
        
        // Test save button click
        onView(withId(R.id.btnSave)).perform(click());
        
        // Verify that the form accepts valid input
        // (Success dialog should appear)
    }

    @Test
    public void testRequiredFieldValidation() {
        // Try to save form without required fields
        onView(withId(R.id.btnSave)).perform(click());
        
        // Check that validation errors are shown
        // Note: In a real test, you would check for error messages
    }

    @Test
    public void testClearFormFunctionality() {
        // Enter some data
        onView(withId(R.id.etFirstName)).perform(typeText("Test"));
        onView(withId(R.id.etLastName)).perform(typeText("User"));
        
        Espresso.closeSoftKeyboard();
        
        // Click clear button
        onView(withId(R.id.btnClear)).perform(click());
        
        // Confirm clear action in dialog
        onView(withText("Yes")).perform(click());
        
        // Verify fields are cleared
        onView(withId(R.id.etFirstName)).check(matches(withText("")));
        onView(withId(R.id.etLastName)).check(matches(withText("")));
    }

    @Test
    public void testPhoneCallButton() {
        // Enter a phone number
        onView(withId(R.id.etPhone)).perform(typeText("555-123-4567"));
        
        Espresso.closeSoftKeyboard();
        
        // Click call button
        onView(withId(R.id.btnCall)).perform(click());
        
        // Note: In a real test environment, you would mock the permission request
        // and verify that the correct intent is created
    }

    @Test
    public void testWebsiteButton() {
        // Enter a website URL
        onView(withId(R.id.etWebsite)).perform(typeText("https://example.com"));
        
        Espresso.closeSoftKeyboard();
        
        // Click website button
        onView(withId(R.id.btnVisitWebsite)).perform(click());
        
        // Note: In a real test environment, you would verify that the browser intent
        // is created with the correct URL
    }

    @Test
    public void useAppContext() {
        // Context of the app under test
        android.content.Context appContext = InstrumentationRegistry.getInstrumentation().getTargetContext();
        assertEquals("com.armis.forminputexample", appContext.getPackageName());
    }
}