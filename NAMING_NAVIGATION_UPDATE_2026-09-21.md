# Naming and Header Navigation Update — 2026-09-21

## Changes
- Visible system branding uses the full name: Army Resource Management Information System.
- The abbreviated system name is removed from visible header/footer/page branding; internal code identifiers such as ARMIS_* remain unchanged for compatibility.
- Header navigation is right aligned.
- Logged-in identity uses the military naming convention:
  - Commissioned Officers and WOI: Rank + first/middle initials + surname.
  - All other personnel: Rank + surname + first/middle initials.
- Middle-name initials are included only when mName is populated.
- Examples:
  - Maj M G Mumba
  - WOI M G Mumba
  - Sgt Mumba M G
  - Mr. Mumba M G
- Session/profile refresh now carries mName and personnel category/rank metadata needed for consistent formatting.
