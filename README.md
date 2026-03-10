# ![OxyPods logo](https://raw.githubusercontent.com/QuirkyRobots/oxypods/main/assets/icon-512x512.png)

**OxyPods** is a WordPress plugin that exposes [Pods](https://wordpress.org/plugins/pods/) custom fields in the [Oxygen 6](https://oxygenbuilder.com/) (Breakdance) Dynamic Data picker — including live preview in the visual editor.

- **Author:** [QuirkyRobots](https://github.com/QuirkyRobots)
- **License:** GPL-2.0-or-later
- **Requires PHP:** 8.0
- **Requires:** Pods 3.3+, Oxygen 6 (Breakdance)

---

## What it does

When you build a page in Oxygen 6, the Dynamic Data picker lets you bind elements to live data sources. Out of the box, Oxygen 6 has no knowledge of Pods custom fields. OxyPods registers every field from every post-type pod with Oxygen's Dynamic Data system, so they appear in the picker grouped under **Pods → [Pod Name]**.

Fields render live in the visual editor, not as raw shortcodes.

### Supported field types

| Pods type | Registered as | Notes |
|---|---|---|
| `text`, `paragraph`, `wysiwyg`, `date`, `number`, `email`, `website`, `phone`, `color`, `code`, `slug`, `html` | String field | Rendered as text in the editor |
| `file` (single) | Image field | Binds to Image elements |
| `file` (multi) | Gallery field | Binds to Gallery elements |
| `pick` | String field | Returns related post title |
| `boolean` | String field | Returns stored value |
| `oembed` | String field | Returns URL |
| `password` | **Excluded** | Never exposed — security |

---

## Example Screenshots

<img width="959" height="473" alt="image" src="https://github.com/user-attachments/assets/ee287de1-73ed-4b5e-a7a2-ce35e20b9dcc" />

<img width="959" height="473" alt="image" src="https://github.com/user-attachments/assets/bb84a2eb-bd8b-4ed6-969f-bdc211094d4f" />

---

## Installation

1. Download the latest release zip from [Releases](https://github.com/QuirkyRobots/oxypods/releases)
2. In WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and activate
4. Both Pods and Oxygen 6 must be active — OxyPods will show a warning banner if either is missing

---

## Usage

1. Open any post or template in the Oxygen 6 builder
2. Add an element that supports Dynamic Data (Text, Image, Gallery, etc.)
3. Click the Dynamic Data icon
4. Scroll to the **Pods** category — your pod fields appear grouped by pod name
5. For Gallery elements, set **Type: Gallery** in the filter to see only gallery-compatible fields

---

## Diagnostics

OxyPods adds a settings page at **Settings → OxyPods** which shows:

- Status banner confirming both Pods and Oxygen 6 are active
- A table of all detected pods and fields, showing how each is registered and its Dynamic Data slug
- A diagnostics table showing the raw `_pods_{field}` and plain meta values for the most recently published post of each pod type — useful for confirming data is stored correctly

---

## Troubleshooting

**Fields show as raw shortcodes in the editor**
This means the Dynamic Data AJAX call is returning empty. Check the Diagnostics page — if `_pods_{field}` is empty for the relevant field, re-save the post through the WordPress editor with Pods active.

**Gallery field not visible in the picker**
Make sure **Type** is set to **Gallery** (not All) in the Dynamic Data picker. Gallery fields are filtered by return type.

**Images empty after selecting the field**
Confirm the attachment IDs shown in Diagnostics exist as posts with `post_status = inherit` in `wp_posts`. Run:
```sql
SELECT ID, post_status FROM wp_posts WHERE ID IN (30, 51, 52);
```

**Debug attachment ID resolution**
Add temporarily to `functions.php`:
```php
add_action('wp_loaded', function() {
    $ids = oxypods_get_attachment_ids( YOUR_POST_ID, 'YOUR_FIELD_NAME' );
    error_log( 'OxyPods attachment IDs: ' . wp_json_encode( $ids ) );
});
```
Then check `wp-content/debug.log`.

---

## How it works (technical)

### Hook timing

Oxygen 6 fires `breakdance_loaded` on `plugins_loaded`. OxyPods listens for `breakdance_loaded` and registers its own `wp_loaded` callback. This guarantees:

1. The Breakdance class hierarchy is fully declared before OxyPods extends it
2. Pods is fully initialised (`pods_api()` is callable) by `wp_loaded`
3. Fields are registered before `template_include`, where Breakdance fires its `breakdance_dynamic_data_get` AJAX handler

### Meta storage

Pods stores file/image relationship data two ways:

- `_pods_{field_name}` — a single array-typed meta key containing all attachment IDs (written by `PodsAPI::save_relationships()`)
- `{field_name}` — one individual meta row per attachment ID

OxyPods reads both layers, trying the `_pods_` key first for speed.

### Live editor AJAX

The Oxygen 6 builder previews dynamic data by sending a POST request to the post's URL with `action=breakdance_dynamic_data_get`. OxyPods field handlers use `get_the_ID()` and `get_post_meta()` directly — the same pattern as native Breakdance fields — ensuring they work correctly in this AJAX context.

---

## Contributing

Pull requests welcome at [github.com/QuirkyRobots/oxypods](https://github.com/QuirkyRobots/oxypods).
