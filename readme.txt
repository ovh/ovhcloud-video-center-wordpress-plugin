=== OVHcloud Video Center ===
Contributors: ovhcloud
Tags: video, embed, ovh, vod, video center
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 0.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage and embed videos from OVHcloud Video Center directly in your WordPress site.

== Description ==

OVHcloud Video Center wordpress plugin lets you upload, manage, and embed videos to the OVHcloud Video Center platform — all without leaving WordPress.

**Key features:**

* **Upload videos** with a progress bar directly from the admin dashboard.
* **Manage videos** — list, search, edit titles & tags, and delete from a card-based grid.
* **Embed videos** using shortcodes or the Gutenberg block editor.
* **Classic Editor support** — TinyMCE toolbar button for easy shortcode insertion.
* **Responsive player** — iframe embeds that adapt to any theme.
* **Multipart upload** — files larger than 50 MB are uploaded using the multipart protocol for reliability.
* **Secure** — nonce verification, capability checks, input sanitization, and masked API key storage.

**Shortcodes:**

* `[ovhcloud_vc_gallery]` — Displays a YouTube-style mosaic of video poster thumbnails. Clicking a thumbnail opens the video in a modal player.
* `[ovhcloud_vc_video id="vod_xxx"]` — Embeds a single video player.

**Gutenberg Block:**

The "OVHcloud Video Center" block provides a visual step-by-step editor experience:

1. Choose an orientation (horizontal or vertical).
2. Pick a video from a poster grid or configure a filtered video list.
3. Live preview via server-side rendering.

== Manual installation ==

1. Upload the `ovhcloud-video-center` folder to `wp-content/plugins/`.
2. Build the Gutenberg block (requires Node.js 18+):
   `cd wp-content/plugins/ovhcloud-video-center/blocks/ovhcloud-video-center && npm install && npm run build`
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Go to **OVHcloud Video Center → API Settings** and enter your API Key and Space ID.
5. Click **Test Connection** to verify your credentials.

== Source Code ==

The full uncompressed source code for the Gutenberg block (including JSX and CSS) is located in the `blocks/ovhcloud-video-center/src/` directory of this plugin.

The compiled/minified files in `blocks/ovhcloud-video-center/build/` are generated from that source using the `@wordpress/scripts` build tool.

To rebuild from source:

1. Ensure Node.js 18+ and npm are installed.
2. Navigate to `blocks/ovhcloud-video-center/`.
3. Run `npm install` to install dependencies.
4. Run `npm run build` to generate the production build.

The public source code repository is available at:
https://github.com/ovh/ovhcloud-video-center-wordpress-plugin

== Frequently Asked Questions ==

= Where do I get my API Key and Space ID? =

You can find them in your OVHcloud Video Center dashboard. The API Key is used for authentication and the Space ID identifies your video workspace.

= What video formats are supported? =

Almost all formats are supported since it relies on ffmpeg. 

= What happens with large files? =

Files larger than 50 MB are automatically uploaded using the multipart upload protocol, which splits the file into parts for more reliable delivery.

= Can I filter videos by tags? =

Yes. Use `[ovhcloud_vc_gallery tags="sport,music"]` to display only videos matching specific tags (up to 5).

= Can I control the player behaviour? =

Yes. The `[ovhcloud_vc_video]` shortcode supports these parameters:

* `autoplay="true"` — auto-start playback
* `loop="true"` — loop the video
* `mute="true"` — start muted
* `hide_title="true"` — hide the title overlay
* `hide_controls="true"` — hide player controls

These are also available as toggles in the Gutenberg block sidebar.

= What roles can manage videos? =

Only users with `administrator` or `editor` roles can upload, edit, and delete videos.

== Changelog ==

= 0.0.1 =
* Initial release.
* Video upload with two-step flow (create VOD + upload to pre-signed URL).
* Multipart upload for files > 50 MB.
* Admin pages: All Videos, Add Video, API Settings.
* Shortcodes: `[ovhcloud_vc_gallery]` and `[ovhcloud_vc_video]`.
* Gutenberg block with orientation picker, video selector, and live preview.
* TinyMCE button for classic editor shortcode insertion.
* Player embed parameters: autoplay, loop, mute, hide_title, hide_controls.

== External Services ==

This plugin relies on the **OVHcloud Video Center** third-party service to manage and deliver video content. The OVHcloud Video Center API is required for the plugin to function.

= What the service is used for =

The OVHcloud Video Center API is used to upload, list, edit, delete, and embed videos hosted on OVHcloud's video platform.

= What data is sent and when =

* **API Key** — sent with every API request for authentication.
* **Video files** — sent when you upload a video from the WordPress admin dashboard.
* **Video metadata** (titles, tags) — sent when you create or edit a video.
* **Requests to list or delete videos** — sent when you browse or manage videos from the admin.
* **Embed requests** — when a visitor loads a page containing a video embed, their browser requests the video player iframe from OVHcloud's embed servers (e.g. `embed.eu.videocenter.ovh.net`).

The API endpoint is configurable in the plugin settings (default: `https://api.eu.videocenter.ovh.net`). No end-user personal data is collected or transmitted by this plugin beyond standard browser requests for the embedded player.

= Service provider =

This service is provided by **OVHcloud**:

* [OVHcloud Terms of Service](https://www.ovhcloud.com/en/terms-and-conditions/contracts/)
* [OVHcloud Privacy Policy](https://www.ovhcloud.com/en/terms-and-conditions/privacy-policy/)
