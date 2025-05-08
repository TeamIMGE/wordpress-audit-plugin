# WordPress Audit Plugin

A custom WordPress plugin to automate site audits, verify SEO settings, check image metadata, and notify responsible users weekly.

## Features

- Admin settings page for configuration
- Assign responsibility to WordPress users
- Weekly audit via WP-Cron
- Checks for:
  - WordPress "Search engine visibility"
  - Yoast SEO configuration
  - Image metadata completeness
- Sends weekly audit emails to responsible users
- GitHub updater support

## Installation

1. Clone or download the plugin into your `wp-content/plugins/` directory.
2. Activate the plugin from the WordPress admin.
3. Navigate to **Settings > Audit Settings** to configure users.
4. Optionally, install the [GitHub Updater](https://github.com/afragen/github-updater) plugin to enable update tracking.

## GitHub Updater Header

Make sure your main plugin file has:
```php
/*
GitHub Plugin URI: https://github.com/teamimge/wordpress-audit-plugin
Primary Branch: main
*/