# WP Multi Post Adder

A simple WordPress admin plugin for creating multiple posts from one screen.

## Overview

WP Multi Post Adder adds a custom **Add Multiple Posts** page inside the WordPress admin dashboard. Instead of creating posts one by one, users can choose how many posts they want to add, fill in each post title and content, select a category, add hashtags/tags, choose featured images, and submit everything together.

The plugin is useful for WordPress sites that need to publish or prepare several posts quickly, such as blogs, content-heavy websites, news-style sites, or testing environments where many sample posts are needed.

## Features

- Add multiple WordPress posts from a single admin page
- Choose the number of posts dynamically
- Add a title and rich text content for each post
- Select one or more categories from a searchable checkbox dropdown (shown as `Parent › Child`)
- Add comma-separated hashtags/tags
- Upload or select a featured image for each post
- Add custom fields / post metadata
- Publish posts immediately, schedule them, or save them as drafts
- Basic admin styling for a cleaner editing workflow
- Translation-ready plugin structure
- Automation box: fill placeholders in each post with one click (title translation, download link, image file name)
- One-click updates from GitHub right inside the WordPress dashboard

## Technologies Used

- PHP
- WordPress Plugin API
- JavaScript / jQuery
- WordPress Media Library
- WordPress Editor API
- CSS

## Installation

1. Download or clone this repository.
2. Copy the plugin folder into your WordPress `wp-content/plugins/` directory.
3. Go to the WordPress admin dashboard.
4. Open **Plugins** and activate **Multi Post Adder**.
5. Go to **Posts → Add Multiple Posts**.

## Usage

1. Open **Posts → Add Multiple Posts** in the WordPress dashboard.
2. Enter the number of posts you want to create.
3. Select one or more categories.
4. Add optional hashtags/tags.
5. Fill in each post title, content, featured image, and custom fields.
6. Click **Publish Posts** to publish them immediately, or **Save as Draft** to save them for later editing.

## Automation

Tick **Automation** on the Add Multiple Posts page to show the Automation box and a row of buttons on every post. Put these placeholders in the post content (or in a content preset), then click the buttons to fill them in:

| Placeholder | Button | Replaced with |
| --- | --- | --- |
| `{translated_title}` | Translate title | The post title translated from Persian to English (Google Translate) |
| `{download_link}` | Download link | The **Download link pattern** from the Automation box, with `{file_name}` filled in for that post |
| `{file_name}` | File name | The featured image's file name, without extension |
| `{custom_text}` | Custom text | The **Custom text** from the Automation box, exactly as typed (text or HTML) |

Each post's buttons work independently. **Run all** runs every automation on one post, and **Run all automations on every post** does it for every post. Clicking a button again updates the value it inserted before, e.g. after changing the featured image. The Automation on/off state, the download link pattern and the custom text are remembered per user.

Example pattern: `https://dl.example.com/files/{file_name}.zip`

Developers can change the translation languages with the `mpa_translate_source_lang` and `mpa_translate_target_lang` filters.

## Scheduling

Turn on **Scheduling** (above the Publish button) to publish the posts later:

- **All at once** – every post is published at the chosen date and time.
- **One by one** – the first post at the chosen time, then one post every 15 min, 30 min, 1 h, 2 h, 3 h, 6 h, 12 h or 24 h.

Each post shows its publish time while scheduling is on, and the Publish button becomes **Schedule Posts**. Times use the site timezone (Settings → General). Save as Draft ignores scheduling. The on/off state, mode and interval are remembered per user.

## Updates from GitHub

The plugin updates itself from this GitHub repository, just like plugins from wordpress.org: new versions appear under **Dashboard → Updates** and **Plugins**, with an **Update now** link and support for WordPress auto-updates.

### Publishing a new version

1. Make your changes.
2. Increase the `Version:` number in the header of `multi-post-adder.php` (e.g. `1.5` → `1.6`). **WordPress only offers an update when this number goes up.**
3. Commit and push to `main`.

Sites check GitHub roughly every 12 hours. To check immediately, use **Settings → MPA Settings → Updates → Check for updates now**, the **Check for updates** link under the plugin on the Plugins screen, or **Dashboard → Updates → Check again**.

### Settings (Settings → MPA Settings → Updates)

| Setting | Description |
| --- | --- |
| Repository | `owner/repository` to update from. Default: `ali-soheili/wp-multipost-adder`. |
| Update source | **Branch** (default): update when the version on the branch is higher. **Releases**: update only when you publish a GitHub Release; the tag (`v1.6` or `1.6`) is the version, and an attached `.zip` is used if present. |
| Branch | Branch to follow in Branch mode. Default: `main`. |
| Access token | Only for private repositories. Create a fine-grained token with read-only **Contents** access to this repo. |

Requires WordPress 5.8 or newer (uses the `Update URI` plugin header). The plugin folder name on the site is kept as-is during updates.

## Project Structure

```text
wp-multipost-adder/
├── multi-post-adder.php   # Main WordPress plugin file
├── mpa-script.js          # Admin-side JavaScript for dynamic post fields
├── mpa-style.css          # Admin page styling
├── includes/
│   └── class-mpa-github-updater.php  # Updates from GitHub
├── languages/             # Translations (fa_IR)
├── LICENSE                # Proprietary License
└── README.md