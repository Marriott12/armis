package com.armis.forminputexample;

import android.Manifest;
import android.app.AlertDialog;
import android.app.DatePickerDialog;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Bundle;
import android.text.TextUtils;
import android.util.Patterns;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.AutoCompleteTextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.google.android.material.button.MaterialButton;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;

import java.text.SimpleDateFormat;
import java.util.Calendar;
import java.util.Locale;

/**
 * MainActivity for ARMIS Form Input Example
 * Demonstrates Android GUI development, state management, theme customization, and intent usage
 */
public class MainActivity extends AppCompatActivity {

    // Request codes
    private static final int PERMISSION_REQUEST_CALL_PHONE = 1001;

    // State keys for saving/restoring instance state
    private static final String STATE_FIRST_NAME = "state_first_name";
    private static final String STATE_LAST_NAME = "state_last_name";
    private static final String STATE_SERVICE_NUMBER = "state_service_number";
    private static final String STATE_RANK = "state_rank";
    private static final String STATE_UNIT = "state_unit";
    private static final String STATE_EMAIL = "state_email";
    private static final String STATE_PHONE = "state_phone";
    private static final String STATE_WEBSITE = "state_website";
    private static final String STATE_BIRTH_DATE = "state_birth_date";
    private static final String STATE_NOTES = "state_notes";

    // UI Components
    private TextInputEditText etFirstName, etLastName, etServiceNumber, etUnit;
    private TextInputEditText etEmail, etPhone, etWebsite, etBirthDate, etNotes;
    private AutoCompleteTextView spRank;
    private TextInputLayout tilFirstName, tilLastName, tilServiceNumber, tilRank, tilUnit;
    private TextInputLayout tilEmail, tilPhone, tilWebsite, tilBirthDate, tilNotes;
    private MaterialButton btnSave, btnClear, btnCall, btnVisitWebsite;

    // Date picker for birth date
    private Calendar calendar;
    private SimpleDateFormat dateFormat;

    // Rank options
    private String[] rankOptions;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        initializeViews();
        setupRankSpinner();
        setupDatePicker();
        setupClickListeners();

        // Restore state if available
        if (savedInstanceState != null) {
            restoreInstanceState(savedInstanceState);
            showToast(getString(R.string.state_restored_message));
        }
    }

    /**
     * Initialize all view components
     */
    private void initializeViews() {
        // Text input fields
        etFirstName = findViewById(R.id.etFirstName);
        etLastName = findViewById(R.id.etLastName);
        etServiceNumber = findViewById(R.id.etServiceNumber);
        etUnit = findViewById(R.id.etUnit);
        etEmail = findViewById(R.id.etEmail);
        etPhone = findViewById(R.id.etPhone);
        etWebsite = findViewById(R.id.etWebsite);
        etBirthDate = findViewById(R.id.etBirthDate);
        etNotes = findViewById(R.id.etNotes);

        // Spinner
        spRank = findViewById(R.id.spRank);

        // Text input layouts for validation
        tilFirstName = findViewById(R.id.tilFirstName);
        tilLastName = findViewById(R.id.tilLastName);
        tilServiceNumber = findViewById(R.id.tilServiceNumber);
        tilRank = findViewById(R.id.tilRank);
        tilUnit = findViewById(R.id.tilUnit);
        tilEmail = findViewById(R.id.tilEmail);
        tilPhone = findViewById(R.id.tilPhone);
        tilWebsite = findViewById(R.id.tilWebsite);
        tilBirthDate = findViewById(R.id.tilBirthDate);
        tilNotes = findViewById(R.id.tilNotes);

        // Buttons
        btnSave = findViewById(R.id.btnSave);
        btnClear = findViewById(R.id.btnClear);
        btnCall = findViewById(R.id.btnCall);
        btnVisitWebsite = findViewById(R.id.btnVisitWebsite);

        // Initialize date components
        calendar = Calendar.getInstance();
        dateFormat = new SimpleDateFormat("MM/dd/yyyy", Locale.US);
    }

    /**
     * Setup rank spinner with predefined options
     */
    private void setupRankSpinner() {
        rankOptions = new String[]{
                getString(R.string.rank_private),
                getString(R.string.rank_corporal),
                getString(R.string.rank_sergeant),
                getString(R.string.rank_lieutenant),
                getString(R.string.rank_captain),
                getString(R.string.rank_major),
                getString(R.string.rank_colonel),
                getString(R.string.rank_general)
        };

        ArrayAdapter<String> adapter = new ArrayAdapter<>(this,
                android.R.layout.simple_dropdown_item_1line, rankOptions);
        spRank.setAdapter(adapter);
    }

    /**
     * Setup date picker for birth date field
     */
    private void setupDatePicker() {
        etBirthDate.setOnClickListener(v -> showDatePicker());
    }

    /**
     * Setup click listeners for all interactive elements
     */
    private void setupClickListeners() {
        btnSave.setOnClickListener(v -> validateAndSaveForm());
        btnClear.setOnClickListener(v -> showClearConfirmation());
        btnCall.setOnClickListener(v -> handlePhoneCall());
        btnVisitWebsite.setOnClickListener(v -> handleWebsiteVisit());
    }

    /**
     * Show date picker dialog
     */
    private void showDatePicker() {
        DatePickerDialog datePickerDialog = new DatePickerDialog(
                this,
                R.style.AppDialog,
                (view, year, month, dayOfMonth) -> {
                    calendar.set(Calendar.YEAR, year);
                    calendar.set(Calendar.MONTH, month);
                    calendar.set(Calendar.DAY_OF_MONTH, dayOfMonth);
                    etBirthDate.setText(dateFormat.format(calendar.getTime()));
                },
                calendar.get(Calendar.YEAR),
                calendar.get(Calendar.MONTH),
                calendar.get(Calendar.DAY_OF_MONTH)
        );
        
        // Set max date to today (no future birth dates)
        datePickerDialog.getDatePicker().setMaxDate(System.currentTimeMillis());
        datePickerDialog.show();
    }

    /**
     * Validate form inputs and save if valid
     */
    private void validateAndSaveForm() {
        boolean isValid = true;

        // Clear previous errors
        clearAllErrors();

        // Validate required fields
        if (TextUtils.isEmpty(etFirstName.getText())) {
            tilFirstName.setError(getString(R.string.error_first_name_required));
            isValid = false;
        }

        if (TextUtils.isEmpty(etLastName.getText())) {
            tilLastName.setError(getString(R.string.error_last_name_required));
            isValid = false;
        }

        if (TextUtils.isEmpty(etServiceNumber.getText())) {
            tilServiceNumber.setError(getString(R.string.error_service_number_required));
            isValid = false;
        } else {
            String serviceNumber = etServiceNumber.getText().toString().trim();
            if (serviceNumber.length() != 8 || !serviceNumber.matches("\\d+")) {
                tilServiceNumber.setError(getString(R.string.error_service_number_invalid));
                isValid = false;
            }
        }

        // Validate email if provided
        String email = etEmail.getText().toString().trim();
        if (!TextUtils.isEmpty(email) && !Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            tilEmail.setError(getString(R.string.error_email_invalid));
            isValid = false;
        }

        // Validate phone if provided
        String phone = etPhone.getText().toString().trim();
        if (!TextUtils.isEmpty(phone) && !Patterns.PHONE.matcher(phone).matches()) {
            tilPhone.setError(getString(R.string.error_phone_invalid));
            isValid = false;
        }

        // Validate website if provided
        String website = etWebsite.getText().toString().trim();
        if (!TextUtils.isEmpty(website) && !Patterns.WEB_URL.matcher(website).matches()) {
            tilWebsite.setError(getString(R.string.error_website_invalid));
            isValid = false;
        }

        if (isValid) {
            // Simulate saving data
            showSuccessDialog();
        } else {
            showErrorDialog();
        }
    }

    /**
     * Clear all validation errors
     */
    private void clearAllErrors() {
        tilFirstName.setError(null);
        tilLastName.setError(null);
        tilServiceNumber.setError(null);
        tilEmail.setError(null);
        tilPhone.setError(null);
        tilWebsite.setError(null);
        tilBirthDate.setError(null);
    }

    /**
     * Show confirmation dialog before clearing form
     */
    private void showClearConfirmation() {
        new AlertDialog.Builder(this, R.style.AppDialog)
                .setTitle(getString(R.string.confirm_clear_title))
                .setMessage(getString(R.string.confirm_clear_message))
                .setPositiveButton(getString(R.string.yes), (dialog, which) -> clearForm())
                .setNegativeButton(getString(R.string.no), null)
                .show();
    }

    /**
     * Clear all form fields
     */
    private void clearForm() {
        etFirstName.setText("");
        etLastName.setText("");
        etServiceNumber.setText("");
        spRank.setText("");
        etUnit.setText("");
        etEmail.setText("");
        etPhone.setText("");
        etWebsite.setText("");
        etBirthDate.setText("");
        etNotes.setText("");
        
        clearAllErrors();
        showToast(getString(R.string.form_cleared_message));
    }

    /**
     * Handle phone call intent
     */
    private void handlePhoneCall() {
        String phoneNumber = etPhone.getText().toString().trim();
        
        if (TextUtils.isEmpty(phoneNumber)) {
            showErrorDialog(getString(R.string.invalid_phone_message));
            return;
        }

        if (!Patterns.PHONE.matcher(phoneNumber).matches()) {
            showErrorDialog(getString(R.string.invalid_phone_message));
            return;
        }

        // Check for CALL_PHONE permission
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CALL_PHONE) 
                != PackageManager.PERMISSION_GRANTED) {
            
            // Request permission
            ActivityCompat.requestPermissions(this,
                    new String[]{Manifest.permission.CALL_PHONE},
                    PERMISSION_REQUEST_CALL_PHONE);
        } else {
            // Permission already granted, make the call
            makePhoneCall(phoneNumber);
        }
    }

    /**
     * Make phone call using implicit intent
     */
    private void makePhoneCall(String phoneNumber) {
        Intent callIntent = new Intent(Intent.ACTION_CALL);
        callIntent.setData(Uri.parse("tel:" + phoneNumber));
        
        try {
            startActivity(callIntent);
        } catch (SecurityException e) {
            showErrorDialog(getString(R.string.permission_denied_message));
        }
    }

    /**
     * Handle website visit intent
     */
    private void handleWebsiteVisit() {
        String website = etWebsite.getText().toString().trim();
        
        if (TextUtils.isEmpty(website)) {
            showErrorDialog(getString(R.string.invalid_website_message));
            return;
        }

        if (!Patterns.WEB_URL.matcher(website).matches()) {
            showErrorDialog(getString(R.string.invalid_website_message));
            return;
        }

        // Ensure URL has a protocol
        if (!website.startsWith("http://") && !website.startsWith("https://")) {
            website = "https://" + website;
        }

        Intent browserIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(website));
        
        try {
            startActivity(browserIntent);
        } catch (Exception e) {
            showErrorDialog("Unable to open website. Please check the URL.");
        }
    }

    /**
     * Show success dialog
     */
    private void showSuccessDialog() {
        new AlertDialog.Builder(this, R.style.AppDialog)
                .setTitle("Success")
                .setMessage(getString(R.string.form_saved_message))
                .setPositiveButton(getString(R.string.ok), null)
                .show();
    }

    /**
     * Show error dialog with custom message
     */
    private void showErrorDialog(String message) {
        new AlertDialog.Builder(this, R.style.AppDialog)
                .setTitle("Error")
                .setMessage(message)
                .setPositiveButton(getString(R.string.ok), null)
                .show();
    }

    /**
     * Show generic error dialog
     */
    private void showErrorDialog() {
        showErrorDialog("Please correct the errors and try again.");
    }

    /**
     * Show toast message
     */
    private void showToast(String message) {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }

    /**
     * Save instance state for configuration changes
     */
    @Override
    protected void onSaveInstanceState(@NonNull Bundle outState) {
        super.onSaveInstanceState(outState);
        
        outState.putString(STATE_FIRST_NAME, etFirstName.getText().toString());
        outState.putString(STATE_LAST_NAME, etLastName.getText().toString());
        outState.putString(STATE_SERVICE_NUMBER, etServiceNumber.getText().toString());
        outState.putString(STATE_RANK, spRank.getText().toString());
        outState.putString(STATE_UNIT, etUnit.getText().toString());
        outState.putString(STATE_EMAIL, etEmail.getText().toString());
        outState.putString(STATE_PHONE, etPhone.getText().toString());
        outState.putString(STATE_WEBSITE, etWebsite.getText().toString());
        outState.putString(STATE_BIRTH_DATE, etBirthDate.getText().toString());
        outState.putString(STATE_NOTES, etNotes.getText().toString());
    }

    /**
     * Restore instance state after configuration changes
     */
    private void restoreInstanceState(@NonNull Bundle savedInstanceState) {
        etFirstName.setText(savedInstanceState.getString(STATE_FIRST_NAME, ""));
        etLastName.setText(savedInstanceState.getString(STATE_LAST_NAME, ""));
        etServiceNumber.setText(savedInstanceState.getString(STATE_SERVICE_NUMBER, ""));
        spRank.setText(savedInstanceState.getString(STATE_RANK, ""));
        etUnit.setText(savedInstanceState.getString(STATE_UNIT, ""));
        etEmail.setText(savedInstanceState.getString(STATE_EMAIL, ""));
        etPhone.setText(savedInstanceState.getString(STATE_PHONE, ""));
        etWebsite.setText(savedInstanceState.getString(STATE_WEBSITE, ""));
        etBirthDate.setText(savedInstanceState.getString(STATE_BIRTH_DATE, ""));
        etNotes.setText(savedInstanceState.getString(STATE_NOTES, ""));
    }

    /**
     * Handle permission request result
     */
    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions,
                                           @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        
        if (requestCode == PERMISSION_REQUEST_CALL_PHONE) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                // Permission granted, make the call
                String phoneNumber = etPhone.getText().toString().trim();
                makePhoneCall(phoneNumber);
            } else {
                // Permission denied
                showPermissionDeniedDialog();
            }
        }
    }

    /**
     * Show permission denied dialog
     */
    private void showPermissionDeniedDialog() {
        new AlertDialog.Builder(this, R.style.AppDialog)
                .setTitle(getString(R.string.permission_denied_title))
                .setMessage(getString(R.string.permission_phone_message))
                .setPositiveButton(getString(R.string.settings), (dialog, which) -> {
                    // Open app settings
                    Intent intent = new Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS);
                    intent.setData(Uri.parse("package:" + getPackageName()));
                    startActivity(intent);
                })
                .setNegativeButton(getString(R.string.cancel), null)
                .show();
    }
}