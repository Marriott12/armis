# ARMIS FormInputExample Android Application

A comprehensive Android application demonstrating form input GUI, state management, custom themes, and implicit intent usage for the Army Resource Management Information System (ARMIS).

## Features

### 📱 Form Input GUI
- **Material Design 3** components with proper constraints
- **Validation** for all input fields with real-time error display
- **AutoComplete** dropdown for military ranks
- **Date picker** for birth date selection
- **Multi-line text input** for additional notes
- **Input length constraints** to prevent data overflow

### 💾 State Management
- **onSaveInstanceState()** implementation for configuration changes
- **onRestoreInstanceState()** to recover form data
- **Automatic state restoration** with user notification
- **Data persistence** during screen rotations and app lifecycle changes

### 🎨 Custom Themes & Styles
- **Military-themed color palette** (dark green, gold, red)
- **Custom Material Design theme** with ARMIS branding
- **Responsive typography** and spacing
- **Elevated cards** and form sections
- **Touch-friendly button sizes** (minimum 48dp)
- **Consistent visual hierarchy** throughout the app

### 📞 Implicit Intent Implementation
- **Phone calls** with permission handling (CALL_PHONE)
- **Website navigation** with URL validation
- **Intent error handling** with user-friendly messages
- **Permission request dialogs** with settings redirection

### 🛡️ Security & Error Handling
- **Runtime permission requests** for phone calls
- **Input validation** with comprehensive error messages
- **AlertDialog** error handling for all operations
- **Data extraction rules** for backup/restore security
- **ProGuard configuration** for release builds

## Technical Implementation

### Architecture
- **Single Activity** with Material Design layout
- **Model-View pattern** with clear separation of concerns
- **State preservation** across configuration changes
- **Permission-based feature access**

### Key Components
- `MainActivity.java` - Main form activity with full functionality
- `activity_main.xml` - Responsive layout with Material Design
- `strings.xml` - All text resources (no hardcoded strings)
- `colors.xml` - Custom ARMIS color palette
- `themes.xml` - Material Design 3 theme with custom styles

### Form Fields
1. **Personal Information**
   - First Name (required, max 50 chars)
   - Last Name (required, max 50 chars)
   - Service Number (required, 8 digits)
   - Military Rank (dropdown selection)
   - Unit (max 100 chars)
   - Birth Date (date picker, max = today)

2. **Contact Information**
   - Email Address (email validation)
   - Phone Number (phone validation + call intent)
   - Personal Website (URL validation + browse intent)
   - Additional Notes (multi-line, max 500 chars)

### Validation Rules
- **Required fields**: First Name, Last Name, Service Number
- **Service Number**: Exactly 8 digits
- **Email**: Valid email format (when provided)
- **Phone**: Valid phone format (when provided)
- **Website**: Valid URL format (when provided)
- **Date**: Valid date format, not in future

### Permissions
- `CALL_PHONE` - Required for making phone calls
- `INTERNET` - Required for opening web links

## Usage Instructions

### Building the Application
1. Open Android Studio
2. Import the project from `/android/FormInputExample/`
3. Sync Gradle dependencies
4. Build and run on device/emulator

### Using the Application
1. **Fill Form**: Enter personnel information in the form fields
2. **Validation**: Required fields are validated on save
3. **State Persistence**: Data survives screen rotations
4. **Phone Calls**: Tap call button to dial entered phone number
5. **Website Visits**: Tap website button to open entered URL
6. **Form Management**: Save data or clear entire form

### Testing State Management
1. Fill out partial form data
2. Rotate device or change configuration
3. Verify data is restored automatically
4. Check notification confirming state restoration

### Testing Permissions
1. Enter phone number and tap call button
2. Grant/deny permission in system dialog
3. Verify appropriate handling of permission result
4. Test settings redirection for denied permissions

## Design Patterns

### Material Design Compliance
- **Touch targets**: Minimum 48dp for accessibility
- **Color contrast**: WCAG AA compliant color combinations
- **Typography**: Material Design type scale
- **Elevation**: Consistent shadow and depth hierarchy
- **Motion**: Standard Material Design transitions

### Android Best Practices
- **Resource externalization**: All strings in strings.xml
- **Meaningful IDs**: Descriptive names for all UI components
- **Input validation**: Client-side validation with server-ready patterns
- **Error handling**: Graceful failure with user guidance
- **Accessibility**: Content descriptions and semantic markup

## File Structure
```
android/FormInputExample/
├── app/
│   ├── src/main/
│   │   ├── java/com/armis/forminputexample/
│   │   │   └── MainActivity.java
│   │   ├── res/
│   │   │   ├── layout/activity_main.xml
│   │   │   ├── values/strings.xml
│   │   │   ├── values/colors.xml
│   │   │   ├── values/themes.xml
│   │   │   ├── xml/backup_rules.xml
│   │   │   ├── xml/data_extraction_rules.xml
│   │   │   └── mipmap-*/ic_launcher.xml
│   │   └── AndroidManifest.xml
│   ├── build.gradle
│   └── proguard-rules.pro
├── build.gradle
├── settings.gradle
└── gradle.properties
```

## Future Enhancements
- **Database integration** for persistent data storage
- **Network sync** with ARMIS backend services
- **Biometric authentication** for enhanced security
- **Offline mode** with sync capabilities
- **Multi-language support** for international deployments
- **Dark mode** theme variant
- **Accessibility improvements** for visually impaired users

---

**ARMIS FormInputExample** - Demonstrating Android development best practices for military applications.