# Getting Started

## Requirements

- WordPress 6.2 or later (tested up to 6.9.1)
- PHP 8.1 or later

## Installation

### Download from GitHub Releases

1. Download the latest release zip from the [Releases page](https://github.com/chrisfromthelc/wp-system-report/releases/latest/download/wp-system-report.zip).
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. Activate the plugin.

### Manual Installation

1. Clone or download the repository into `wp-content/plugins/wp-system-report/`:

   ```bash
   cd wp-content/plugins/
   git clone https://github.com/chrisfromthelc/wp-system-report.git wp-system-report
   cd wp-system-report
   composer install --no-dev
   ```

2. Activate the plugin through **Plugins** in the WordPress admin.

## Generating Your First Report

1. Navigate to **Tools > WP System Report** in the WordPress admin.
2. The **System Report** tab displays all diagnostic data organized by section.
3. Use the action buttons to export:

   | Button | What It Does |
   |--------|-------------|
   | **Get system report** | Generates a plain text report |
   | **Copy for support** | Copies the report to your clipboard |
   | **Download for support** | Downloads a `.txt` file |
   | **Copy for GitHub** | Copies a redacted report in a `<details>` wrapper for GitHub issues |
   | **Download for AI analysis** | Downloads an AI-optimized `.md` file |

## Auto-Updates

The plugin checks for new versions on the [GitHub Releases page](https://github.com/chrisfromthelc/wp-system-report/releases) and serves updates through the standard WordPress dashboard update flow. No additional configuration is needed.

## Next Steps

- **[Error Log Viewer](error-log.md)** - Learn about the debug toggle and error log features
- **[REST API](rest-api.md)** - Access report data programmatically
- **[Extending the Plugin](extending.md)** - Add your own collectors and integrations
