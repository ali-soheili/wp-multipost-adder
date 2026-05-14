# WP Multi Post Adder

A simple WordPress admin plugin for creating multiple posts from one screen.

## Overview

WP Multi Post Adder adds a custom **Add Multiple Posts** page inside the WordPress admin dashboard. Instead of creating posts one by one, users can choose how many posts they want to add, fill in each post title and content, select a category, add hashtags/tags, choose featured images, and submit everything together.

The plugin is useful for WordPress sites that need to publish or prepare several posts quickly, such as blogs, content-heavy websites, news-style sites, or testing environments where many sample posts are needed.

## Features

- Add multiple WordPress posts from a single admin page
- Choose the number of posts dynamically
- Add a title and rich text content for each post
- Select a WordPress category
- Add comma-separated hashtags/tags
- Upload or select a featured image for each post
- Add custom fields / post metadata
- Publish posts immediately or save them as drafts
- Basic admin styling for a cleaner editing workflow
- Translation-ready plugin structure

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
3. Select a category.
4. Add optional hashtags/tags.
5. Fill in each post title, content, featured image, and custom fields.
6. Click **Publish Posts** to publish them immediately, or **Save as Draft** to save them for later editing.

## Project Structure

```text
wp-multipost-adder/
├── multi-post-adder.php   # Main WordPress plugin file
├── mpa-script.js          # Admin-side JavaScript for dynamic post fields
├── mpa-style.css          # Admin page styling
├── LICENSE                # Proprietary License
└── README.md