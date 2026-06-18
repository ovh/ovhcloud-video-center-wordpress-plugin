# OVH VideoCenter WP Plugin

**Version:** 0.0.1  
**Author:** The OVHcloud video team  
**License:** GPLv2 or later  
**Requires WordPress:** 6.0+  
**Requires PHP:** 7.4+

Manage and embed videos from OVHcloud Video Center directly in your WordPress site. Upload, list, edit, and delete videos through an intuitive admin interface, then embed them in posts and pages using shortcodes or the Gutenberg block editor.

---

## Features

- **Video Management** – Upload, list, edit (title & tags), and delete videos via the OVHcloud Video Center API.
- **Two-Step Upload** – Creates the video object via API, then uploads the file directly to the pre-signed URL with a progress bar.
- **Admin Dashboard** – Card-based video grid with poster thumbnails, search/filter, pagination, inline preview, and edit/delete actions.
- **Shortcodes** – `[ovhcloud_vc_gallery]` and `[ovhcloud_vc_video]` for embedding videos in posts/pages.
- **Gutenberg Block** – "OVHcloud Video Center" block with sidebar controls for single video or video list modes.
- **Classic Editor** – TinyMCE toolbar button for easy shortcode insertion.
- **Responsive Embeds** – 16:9 responsive iframe player that adapts to any theme.
- **Security** – Nonce verification, capability checks, input sanitization, and masked API key storage.

---

## Installation

1. **Download** the plugin folder `ovhcloud-video-center/` and place it in your WordPress `wp-content/plugins/` directory.

2. **Build the Gutenberg block** (requires Node.js 18+):
   ```bash
   cd wp-content/plugins/ovhcloud-video-center/blocks/ovhcloud-video-center
   npm install
   npm run build
   ```

3. **Activate** the plugin in WordPress → Plugins.

4. **Configure** your API credentials:
   - Go to **OVHcloud Video Center → API Settings**.
   - Enter your **API Key** and **Space ID**.
   - Click **Test Connection** to verify.

---

## Usage

### Admin Interface

- **OVHcloud Video Center → All Videos** – Browse, search, preview, edit, and delete your videos.
- **OVHcloud Video Center → Add Video** – Upload a new video with title, tags, and file.
- **OVHcloud Video Center → API Settings** – Configure API credentials and test the connection.

### Shortcodes

#### Display a video gallery

```
[ovhcloud_vc_gallery]
```

Shows a responsive grid of video thumbnails. Click a thumbnail to open the video in a modal player.

**Optional attributes:**

| Attribute | Default | Description | Example |
|-----------|---------|-------------|---------|
| `tags` | *(none)* | Comma-separated tag filter (max 5) | `tags="sport,music"` |
| `columns` | `4` | Number of columns in the grid | `columns="3"` |
| `rows` | `2` | Number of rows in the grid | `rows="3"` |
| `size` | *(auto)* | Thumbnail size as `WxH` in pixels | `size="320x180"` |
| `show_title` | `false` | Show video title under each thumbnail | `show_title="true"` |

```
[ovhcloud_vc_gallery tags="tutorial,howto" columns="3" rows="2" show_title="true"]
```

#### Display a single video

```
[ovhcloud_vc_video id="vod_xxxxxxxxxxxxxxxxxxxxxx"]
```

Embeds a specific video player by its VOD ID.

**Attributes:**

| Attribute | Default | Description | Example |
|-----------|---------|-------------|---------|
| `id` | *(required)* | The VOD ID from OVHcloud Video Center | `id="vod_abc123"` |
| `size` | `100%` | Player size: `100%`, `50%`, or explicit `WxH` | `size="640x360"` |
| `orientation` | `horizontal` | Aspect ratio: `horizontal` (16:9) or `vertical` (9:16) | `orientation="vertical"` |
| `autoplay` | `false` | Auto-start playback | `autoplay="true"` |
| `mute` | `false` | Start muted (required for autoplay in most browsers) | `mute="true"` |
| `loop` | `false` | Loop the video | `loop="true"` |
| `hide_title` | `false` | Hide the title overlay | `hide_title="true"` |
| `hide_controls` | `false` | Hide player controls | `hide_controls="true"` |

```
[ovhcloud_vc_video id="vod_abc123" size="640x360" autoplay="true" mute="true"]
```

### Gutenberg Block

1. In the Block Editor, click **+** and search for **"OVHcloud Video Center"**.
2. Add the block to your post/page.
3. In the **Block Settings** sidebar:
   - Choose **Mode**: Single Video or Video List.
   - For Single Video: enter the **Video ID**.
   - For Video List: optionally enter **Tags Filter**.
   - Select the **Player Size**.
4. The block previews the video(s) live in the editor via server-side rendering.

### Classic Editor

1. Click the **OVHcloud Video Center** button (🎬) in the TinyMCE toolbar.
2. Choose the shortcode type (Single Video or Video List).
3. Fill in the parameters and click **OK** to insert the shortcode.

---

## File Structure

```
ovhcloud-video-center/
├── ovhcloud-video-center.php              # Plugin entry point
├── includes/
│   ├── class-api-handler.php        # API wrapper (all HTTP calls)
│   ├── class-settings.php           # Settings page (API credentials)
│   ├── class-admin-page.php         # Admin pages (video list, add, AJAX)
│   └── class-shortcodes.php         # Shortcodes & block render callback
├── assets/
│   ├── css/
│   │   ├── ovh-vc-admin.css         # Admin styles
│   │   └── ovh-vc-front.css         # Front-end styles
│   └── js/
│       ├── ovh-vc-admin.js          # Admin JS (AJAX, upload, modals)
│       ├── ovh-vc-front.js          # Front-end JS (modal player)
│       └── ovh-vc-tinymce.js        # TinyMCE plugin for classic editor
├── blocks/
│   └── ovhcloud-video-center/
│       ├── block.json               # Block metadata
│       ├── package.json             # Build dependencies
│       └── src/
│           ├── index.js             # Block registration
│           ├── edit.js              # Editor component
│           ├── save.js              # Save component (null – SSR)
│           ├── editor.css           # Editor styles
│           └── style.css            # Shared styles
├── tests/
│   └── test-api-handler.php         # Unit tests
└── README.md
```

---

## API Reference

The plugin interacts with the OVHcloud Video Center API:

- **Base URL:** `https://api.eu.videocenter.ovh.net`
- **Authentication:** Basic Auth (`base64(apiKey:)`)
- **Upload flow:**
  1. `POST /space/{spaceId}/vod` → creates VOD, returns `ingest.uploadUrl`
  2. `PUT {uploadUrl}` → upload the video file to the pre-signed URL

See `api-reference.md` for full endpoint documentation.

---

## Security

- **Permissions:** Only `administrator` and `editor` roles can manage videos.
- **Nonces:** All AJAX requests and forms are protected with WordPress nonces.
- **Input Sanitization:** All user inputs are sanitized with `sanitize_text_field()`, `esc_url_raw()`, etc.
- **API Key Storage:** Stored via `update_option()` / `get_option()`, rendered as `type="password"` fields, never logged.

---

## Development

### Building the Gutenberg block

```bash
cd blocks/ovhcloud-video-center
npm install
npm run start    # Watch mode for development
npm run build    # Production build
```

### Running tests

```bash
# Requires WP test suite (wp-phpunit)
composer install
./vendor/bin/phpunit
```

---

## License

GPLv2 or later – see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
