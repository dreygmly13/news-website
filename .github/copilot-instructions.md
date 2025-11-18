# Copilot Instructions for AI Agents

## Project Overview
This is a PHP-based news website with multiple standalone scripts for administration, SMS integration, news import, and user management. The codebase is organized as a flat structure with each file serving a distinct purpose. There is no framework or MVC pattern; scripts interact directly with each other and the database.

## Key Components
- **admin.php, login.php, logout.php**: User authentication and admin dashboard logic.
- **import_news.php, index.php, announcement.php**: News and announcement display/import.
- **subscribers.php**: Manages newsletter/SMS subscribers.
- **send_sms.php, sms_gateways.php, arduino_sms.php, test_sim800c.php, test_semaphore.php, test_infobip.php, test_single_sms.php, check_sms_status.php, diagnose_semaphore.php**: SMS sending, gateway integration, diagnostics, and testing.
- **config.php**: Central configuration (database, API keys, etc.).
- **styles.css**: Main stylesheet for the site.

## Developer Workflows
- **No build step required**: PHP files run directly on a local server (e.g., XAMPP).
- **Testing**: Manual via browser or direct script execution. Test scripts are named with `test_` prefix.
- **Debugging**: Use inline `echo`/`var_dump` or browser-based inspection. No integrated test framework.

## Project-Specific Patterns
- **Direct file inclusion**: Common use of `include`/`require` for shared config and logic.
- **Flat file structure**: All scripts are in the root directory; no subfolders for modules or assets.
- **SMS Integration**: Multiple gateways supported (Semaphore, Infobip, SIM800C, Arduino). Each has a dedicated script for sending and diagnostics.
- **Minimal error handling**: Most scripts use basic error reporting; check for `die()` or direct output.
- **Configuration**: All sensitive keys and DB credentials are in `config.php`. Always reference this file for integration points.

## External Dependencies
- **PHP extensions**: Ensure required extensions (e.g., cURL, MySQLi) are enabled in your PHP environment.
- **XAMPP**: Project is designed for local development using XAMPP. Place files in `htdocs` and access via `localhost`.

## Examples
- To send an SMS via Semaphore, use `send_sms.php` (references `config.php` for API key).
- To test Infobip integration, run `test_infobip.php` in browser or CLI.
- To add a news item, use `import_news.php` or edit `index.php` for display logic.

## Conventions
- Scripts are self-contained; avoid creating new folders unless necessary.
- Use existing test scripts as templates for new gateway integrations.
- Always update `config.php` when adding new external services or credentials.

---

For questions about unclear workflows or missing conventions, please provide feedback so this guide can be improved.