# Changelog

All notable changes to OxyPods are documented here.

---

## [1.1.6 Beta] — 2026-03-08 — Security hardening

### Fixed
- **Password fields excluded:** Pods `password` field type is now explicitly blocked from registration. Previously it fell through to the `default` case and was exposed as a String field in the Dynamic Data picker, making plaintext credential values accessible to any user with editor access.
- **Diagnostics page scoped to published posts:** The diagnostics table previously used `post_status => 'any'`, which could expose raw meta values from draft, private, pending, or trashed posts. Restricted to `post_status => 'publish'`.
- **Explicit capability check in admin render function:** `oxypods_render_admin_page()` now calls `current_user_can('manage_options')` directly as defence-in-depth. `add_options_page()` already gates menu access, but the explicit check protects against direct callback invocation outside the normal menu flow.
- **Corrected debug function name in troubleshooting docs:** The troubleshooting section referenced the removed `pods_oxygen6_get_image_ids()` function. Updated to the correct `oxypods_get_attachment_ids()`.

---

## [1.1.5 Beta] — 2026-03-08 — Live editor fix (root cause)

### Fixed
- **Fields showing as raw shortcodes in the Oxygen 6 live editor.** The Oxygen 6 builder previews dynamic data via a POST request to the post URL (`action=breakdance_dynamic_data_get`). Previous versions used the Pods ORM (`pods()`) and custom post ID resolution logic in `handler()`. These produced stray output (PHP notices, debug messages) that Breakdance's AJAX wrapper detected and converted into exceptions, causing the entire batch to fail and the builder to fall back to displaying raw shortcode text.

  Root cause identified by reading native Breakdance field source: `PostCustomField`, `PostImageAttachments`, and `MetaboxGalleryField` all use `get_the_ID()` and `get_post_meta()` directly — no ORM, no `ob_start()` wrapper. OxyPods field handlers now mirror this pattern exactly.

### Changed
- All `handler()` methods rewritten to use `get_the_ID()` for post ID (matching native Breakdance fields)
- All `handler()` methods use `get_post_meta()` directly — no Pods ORM calls
- `ob_start()` wrappers removed from all handlers — native fields have none
- `proOnly()` overridden to return `false` in all field classes, matching `PostCustomField`
- `oxypods_get_attachment_ids()` helper reads `_pods_{field}` and plain meta rows directly
- Gallery handler mirrors `MetaboxGalleryField` and `PostImageAttachments` exactly: `array_map(ImageData::fromAttachmentId, $ids)`

---

## [1.1.4 Beta] — 2026-03-08 — Output buffering attempt

### Changed
- Added `ob_start()`/`ob_end_clean()` wrappers around all `handler()` bodies to catch stray output before it reached Breakdance's outer buffer.
- Added per-image `ob_start()` in gallery handler loop.

### Notes
- This did not resolve the live editor issue. The stray output was not the root cause — the Pods ORM calls themselves were the problem. Fixed properly in 1.1.5.

---

## [1.1.3 Beta] — 2026-03-08 — Post ID detection improvements

### Changed
- Post ID resolution expanded to five fallback sources: `$_POST['id']` → `filter_input(INPUT_POST)` → `get_queried_object_id()` → `$GLOBALS['post']->ID` → `get_the_ID()`
- Added `oxypods_image_data_from_id()` fallback: if Breakdance's `prepareMedia()` returns null, build `ImageData` directly from `wp_get_attachment_url()` and `wp_get_attachment_image_src()`.

### Notes
- Did not resolve the live editor issue. The root cause was unrelated to post ID resolution.

---

## [1.1.2 Beta] — 2026-03-08 — Rebrand to OxyPods

### Changed
- Plugin renamed from "Pods + Oxygen 6 Integration" to **OxyPods**
- Author changed to **QuirkyRobots**
- Plugin URI updated to `https://github.com/QuirkyRobots/oxypods`
- Main file renamed from `pods-oxygen6-integration.php` to `oxypods.php`
- Plugin folder renamed from `pods-oxygen6-integration/` to `oxypods/`
- Admin menu item renamed from "Pods + Oxygen 6" to **OxyPods**
- Settings page slug changed to `oxypods`
- Status message now names both plugins explicitly: "Both Pods and Oxygen 6 are active — OxyPods is running"

---

## [1.1.1 Beta] — 2026-03-08 — Gallery field registration fix

### Fixed
- **Gallery fields not appearing in the Gallery type picker.** The field registration logic gated on `file_type` (the Pods option controlling which file types are allowed). Fields configured as "All Files" (`file_type = 'other'`) were being registered as `FileUrlField` (a string) instead of `GalleryField`, so they were filtered out when Type was set to Gallery in the picker.

  Fix: removed `file_type` gating entirely. Registration now depends solely on `file_format_type`:
  - `multi` → `GalleryField`
  - `single` → `ImageField`

  The "File Type" Pods option controls what the uploader accepts, not what the field semantically is. A field set to "All Files" can still contain images.

---

## [1.1.0 Beta] — 2026-03-07 — Initial release

### Added
- Registers all Pods post-type fields with the Oxygen 6 (Breakdance) Dynamic Data controller
- Supports text, paragraph, wysiwyg, date, number, currency, email, website, phone, color, code, slug, html, oembed, pick, and boolean field types as String fields
- Supports `file` type fields as Image or Gallery fields based on `file_format_type`
- Correct bootstrap hook timing: `breakdance_loaded` → `wp_loaded`, ensuring Breakdance classes are declared and Pods is initialised before field registration
- Three-layer attachment ID retrieval: `_pods_{field}` meta → plain meta rows → Pods ORM fallback
- Admin diagnostics page at **Settings → OxyPods** showing detected fields and raw meta values

### Fixed (vs. original handover document)
- Removed dead `case 'image':` and `case 'avatar':` switch branches — Pods 3.x has no standalone `image` field type; all image uploads use `file` type. These branches caused fields to silently fall through to `default` and register as `StringField`.
