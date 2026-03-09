# Scheduled Announcements Design

## Summary

Replace the single announcement bar with support for multiple scheduled announcements. Each announcement has a start date and end date. Only one can be active at a time (no overlapping date ranges). When nothing is scheduled, the bar disappears.

## Data Model

Replace `mcab_settings` with `mcab_announcements` — a serialized array in `wp_options`.

Each announcement entry:
- `content` — HTML/text
- `text_color` — hex color
- `background_color` — hex color
- `text_size` — e.g. "16px"
- `custom_css` — optional CSS
- `start_date` — datetime (Y-m-d H:i)
- `end_date` — datetime (Y-m-d H:i)

Validation: no two announcements may have overlapping date ranges. Enforced on save.

## Admin UI

Same single admin page, reworked:
- Table of all announcements (content preview, start/end date, status: active/scheduled/expired), sorted by start date
- "Add New" button expands a form with all fields
- Edit/Delete links per row load data into the form
- `datetime-local` HTML inputs for dates
- Live preview of the announcement being edited
- Overlap validation on save with WordPress admin error notice

## Frontend Display

- Load `mcab_announcements`
- Find announcement where `start_date <= now < end_date`
- Render it (same div + inline styles as current)
- If none found, render nothing

No cron jobs — just a time check on page load against autoloaded option.

## Migration

On plugin load, if `mcab_settings` exists and `mcab_announcements` does not, migrate the old single announcement into the new format (with no dates set, treated as always-active for backwards compatibility). Delete `mcab_settings` after migration.

## Version

Bump to 2.0.0 (breaking change in data model).
