# Base3Wordpress

Base3Wordpress integrates the **BASE3 Framework** into **WordPress** and provides a complete bridge between WordPress’ runtime and BASE3’s container-driven architecture. It boots the BASE3 Framework inside WordPress, wires services through BASE3’s DI/autowiring, and exposes BASE3 endpoints through WordPress-native entry points (REST API, WP-Cron, Admin, and optional shortcodes).

This repository is part of the **BASE3 Framework** ecosystem.

---

## License

This project is licensed under the **GNU General Public License v3.0** (GPL-3.0-only).  
See the `LICENSE` file for details.

---

## Overview

WordPress does not ship with a global Composer autoloader or a DI container. Base3Wordpress solves this by:

- Bootstrapping the BASE3 Framework from within a WordPress plugin.
- Providing a **shared container** that contains both BASE3 services and WordPress-adapted services.
- Mapping BASE3 interfaces to WordPress equivalents via **adapter services** (e.g., logging, configuration, database, HTTP, caching, user context).
- Discovering BASE3 plugins located in the `base3/` directory using BASE3’s class scanning/class map facilities.
- Exposing BASE3 “outputs” and optional MCP-style function endpoints via **WordPress REST API** routes.
- Supporting WordPress lifecycle integration: activation/deactivation hooks, WP-Cron, admin menu pages, and optional shortcodes.

The result is a clean, modular setup where BASE3 plugins remain framework-native and interact with WordPress through stable, testable adapters.

---

## Directory Layout

A typical WordPress deployment places the entire BASE3 ecosystem under a single `base3/` plugin directory:

```

wp-content/plugins/
base3/
base3-wordpress.php
Base3Framework/
src/
...
Base3Wordpress/
plugin.php
src/
WordpressBootstrap.php
WordpressServiceProvider.php
Host/
Rest/
Admin/
Cron/
Adapter/
Support/
MissionBay/
src/
...
DataHawk/
src/
...
ClientStack/
src/
...

````

### Why this layout?

WordPress scans plugins only one directory deep:

- `wp-content/plugins/*.php`
- `wp-content/plugins/<dir>/*.php`

Placing `base3-wordpress.php` directly in `wp-content/plugins/base3/` ensures WordPress detects it, while all BASE3 components remain grouped in the same folder hierarchy.

---

## Installation

1. Copy the BASE3 folder into your WordPress plugin directory:

   - Destination: `wp-content/plugins/base3/`

2. Ensure the following file exists:

   - `wp-content/plugins/base3/base3-wordpress.php`

3. Activate the plugin in the WordPress admin:

   - **Plugins → Installed Plugins → BASE3 for WordPress → Activate**

---

## Plugin Loader (base3-wordpress.php)

WordPress detects the plugin via a thin loader file. This file only includes the BASE3Wordpress bootstrap.

**`wp-content/plugins/base3/base3-wordpress.php`**
```php
<?php
/**
 * Plugin Name: BASE3 for WordPress
 * Description: Integrates the BASE3 Framework into WordPress and bridges services.
 * Version: 1.0.0
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

require __DIR__ . '/Base3Wordpress/plugin.php';
````

---

## Boot Process

Base3Wordpress initializes BASE3 in a WordPress-safe way:

1. **Early hook registration** using `plugins_loaded` (or earlier when used as MU-plugin).
2. **BASE3 autoloading** and class map initialization.
3. **Container creation** and service provider registration.
4. **Adapter installation** mapping WordPress services into BASE3 interfaces.
5. **Host integrations**:

   * REST API routes
   * WP-Cron scheduling
   * optional admin UI pages
   * optional shortcodes

The bootstrap is idempotent and runs once per request.

---

## Container & Dependency Injection

BASE3 uses a DI container with autowiring. Base3Wordpress extends the container with WordPress-backed implementations.

### Service Registration

Base3Wordpress registers and/or overrides bindings for common framework-level interfaces such as:

* Logging
* Configuration
* Database
* HTTP client
* Cache
* Clock/time
* Request context
* User and permissions context
* Path/URL resolution

In BASE3 plugins, services are requested via their interfaces; adapter services transparently delegate to WordPress.

### Autowiring

Any BASE3 class that is discovered by the class map and resolved by the container can declare constructor dependencies. Base3Wordpress ensures those dependencies can be satisfied either by:

* WordPress-backed adapters, or
* framework-native services.

---

## Adapter Services

Adapter services are the core bridge layer. They provide stable BASE3 interfaces while delegating to WordPress runtime.

### Logging

A BASE3 logger adapter maps to WordPress’ logging behavior:

* error log integration (`error_log`)
* optional structured output
* optional integration with WordPress debug configuration

### Configuration

Configuration can be sourced from:

* WordPress options (`get_option`, `update_option`)
* WordPress constants (`wp-config.php`)
* environment variables

A unified config adapter resolves values consistently for BASE3.

### Database

A database adapter maps BASE3’s database abstraction to WordPress’ `$wpdb`.

Supported features typically include:

* parameterized queries
* scalar/single/multi result helpers
* escaping utilities

### HTTP Client

An HTTP adapter maps BASE3’s HTTP client abstraction to:

* `wp_remote_get`
* `wp_remote_post`

### Cache

A cache adapter uses WordPress caching primitives:

* transients (`get_transient`, `set_transient`)
* object cache when available

### User Context & Capabilities

A user context adapter provides:

* current user (`wp_get_current_user`)
* capability checks (`current_user_can`)
* authentication context for API calls

### Paths & URLs

A path adapter resolves filesystem and URL locations:

* plugin directory and URL
* upload directory
* content directory
* site URL and home URL

---

## BASE3 Plugin Discovery

Base3Wordpress discovers BASE3 plugins located under:

* `wp-content/plugins/base3/*/src`

Each plugin is treated as a BASE3 component. The BASE3 class map scans and registers classes according to standard BASE3 conventions, enabling:

* automatic instantiation of services
* discovery via interface lookup
* endpoint binding via `IOutput` and similar patterns

---

## WordPress REST API Integration

Base3Wordpress exposes BASE3 endpoints through WordPress’ REST API.

### Base Route Namespace

All routes are registered under:

* `/wp-json/base3/v1/...`

### Ping Endpoint

A basic health endpoint:

* `GET /wp-json/base3/v1/ping`

Returns a JSON payload including runtime information.

### Output Dispatcher

An output dispatcher allows BASE3 `IOutput` implementations to be invoked through REST:

* `GET /wp-json/base3/v1/output/<name>`
* `POST /wp-json/base3/v1/output/<name>`

The dispatcher resolves an output by name through the container and executes it in a WordPress-friendly request context.

### Authentication & Authorization

Routes can enforce:

* public access (no auth)
* WordPress logged-in sessions
* capability checks (e.g., `manage_options`)
* nonce verification for browser-origin requests
* application passwords for API clients

Authorization rules are applied per route group and can be overridden by configuration.

---

## WP-Cron Integration

Base3Wordpress can register scheduled tasks that execute BASE3 services.

* schedules are set on activation
* tasks can be disabled on deactivation
* handlers resolve and execute classes via the container

Typical uses:

* periodic data sync
* report generation
* cache warmup
* maintenance routines

---

## Admin UI Integration

Base3Wordpress can provide admin pages under a dedicated menu entry.

Admin pages can:

* display BASE3 status diagnostics
* list discovered BASE3 components
* configure settings stored in WordPress options
* trigger BASE3 jobs or outputs manually (capability guarded)

---

## Shortcodes (Optional)

Base3Wordpress may provide shortcodes to render BASE3-driven content in pages/posts.

Examples:

* `[base3_output name="example"]`
* `[base3_report id="monthly-overview"]`

Shortcodes resolve outputs via the container and render HTML safely in WordPress context.

---

## Configuration

Base3Wordpress supports configuration through WordPress options, constants, and environment variables.

### Common Settings

Typical settings include:

* REST route access mode (public/authenticated/capability)
* logging configuration
* plugin discovery paths
* output name mapping rules
* cache settings
* integration toggles (admin pages, shortcodes, cron)

### WordPress Options

Settings are stored under a dedicated option namespace, e.g.:

* `base3_wordpress_*`

### wp-config.php Constants

Constants can override options for immutable server configuration.

Examples:

* `BASE3_WORDPRESS_DEBUG`
* `BASE3_WORDPRESS_ROUTE_MODE`
* `BASE3_WORDPRESS_ALLOWED_CAP`

---

## Development Conventions

### Namespaces

* `Base3Wordpress\...` for the integration plugin
* `Base3Framework\...` for the core framework
* other BASE3 plugins use their own root namespaces following BASE3 conventions

### Class Discovery

Classes intended for discovery should be placed under each plugin’s `src/` directory with standard BASE3 naming and file layout rules.

### Service Keys

Interfaces serve as stable service keys in the container. Plugins request dependencies via constructor parameters.

---

## Security Notes

Base3Wordpress follows WordPress best practices:

* REST endpoints validate requests
* capability checks are enforced where appropriate
* nonce verification is used for browser-origin actions
* inputs are sanitized and validated
* outputs are escaped when rendering HTML
* database access uses safe query patterns and proper escaping

---

## Troubleshooting

### Plugin Not Visible in WordPress Admin

Ensure the loader file is placed exactly here:

* `wp-content/plugins/base3/base3-wordpress.php`

WordPress must be able to read the file and directory.

### REST Routes Not Available

* Confirm permalinks are enabled or rewrite rules are functioning.
* Check that no security plugin blocks `/wp-json/`.
* Verify that the plugin is activated.
* Inspect WordPress debug logs if enabled.

### Class Discovery Missing Components

* Confirm BASE3 plugins live under `wp-content/plugins/base3/<PluginName>/src`.
* Verify file permissions.
* Ensure the BASE3 autoloader/class map is initialized by the bootstrap.

### Database Errors

* Ensure `$wpdb` is available and WordPress is fully booted when executing DB logic.
* Verify table names use the correct WordPress prefix and/or configured schema rules.

---

## Frequently Asked Questions

### Can I keep BASE3 plugins completely unaware of WordPress?

Yes. Plugins depend on BASE3 interfaces and receive WordPress behavior through adapters registered by Base3Wordpress.

### Do I need Composer?

No. Base3Wordpress loads the BASE3 Framework directly and does not require a global WordPress Composer autoloader. If individual BASE3 plugins include vendor libraries, they can load their own autoloaders internally.

### Can I expose arbitrary BASE3 endpoints?

Yes. REST dispatchers can route to `IOutput` implementations or dedicated controllers resolved from the container.

### Can I run BASE3 jobs on a schedule?

Yes. WP-Cron handlers resolve and execute services via the container.

---

## Changelog

Changes are tracked in `CHANGELOG.md` (if present).

---

## Contributing

Contributions to BASE3 components follow the BASE3 development workflow. Please ensure contributions remain compatible with GPL v3.0 licensing and adhere to the BASE3 code conventions.

---

## Credits

BASE3 Framework and Base3Wordpress are maintained as part of the BASE3 ecosystem.

```
