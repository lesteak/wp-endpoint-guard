=== WP Endpoint Guard ===
Contributors: xincdigital
Tags: rest api, api authentication, jwt, api key, rest api security
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Developer-first WordPress REST API authentication. Per-endpoint control, API Keys, JWT — zero file editing required.

== Description ==

WP Endpoint Guard gives you granular, per-endpoint control over your WordPress REST API. Out of the box, WordPress leaves every REST endpoint open to the public. This plugin lets you lock down exactly what you need — no wp-config.php editing required.

**Features:**

* **Per-Endpoint Rules** — Set each REST route to Open, Require Auth, or Block.
* **API Key Authentication** — Generate API keys for external integrations. Keys are hashed with SHA-256; the raw key is shown once and never stored.
* **JWT Authentication** — Full JSON Web Token support with a built-in token endpoint. Secret key managed through the admin UI.
* **Auto-Discovery** — Automatically detects all registered REST routes, including those from other plugins.
* **Lockdown Mode** — One-click kill switch to block all unauthenticated REST access.
* **Hide REST Index** — Return 404 for unauthenticated requests to /wp-json/.
* **Clean Admin UI** — Tabbed settings page with inline AJAX saves. No page reloads needed.

**How It Works:**

1. Install and activate the plugin.
2. Go to Settings > WP Endpoint Guard.
3. Set your global default rule (Open or Require Auth).
4. Fine-tune individual endpoints on the Endpoints tab.
5. Generate API keys or use JWT tokens for authenticated access.

**Authentication Methods:**

* **API Key** — Send via `Authorization: Bearer wpeg_yourkey` or `X-API-Key: wpeg_yourkey` header.
* **JWT** — POST credentials to `/wp-json/wp-endpoint-guard/v1/token` to receive a token, then send via `Authorization: Bearer eyJ...` header.

== Installation ==

1. Upload the `wp-endpoint-guard` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Navigate to Settings > WP Endpoint Guard to configure.

== Frequently Asked Questions ==

= Will this break my site? =

No. The default rule is "Open", which matches WordPress core behavior. Nothing changes until you explicitly set endpoints to Require Auth or Block.

= How many API keys can I create? =

The free tier supports up to 2 active API keys per site.

= Where is the JWT secret stored? =

The JWT secret is stored in the wp_options table and managed entirely through the admin UI. No wp-config.php editing is needed.

= Does this work with other authentication plugins? =

Yes. WP Endpoint Guard respects existing authentication. If another plugin has already authenticated a request, it passes through without interference.

== Screenshots ==

1. Settings page — global defaults, lockdown mode, JWT configuration.
2. API Keys page — generate and manage keys.
3. Endpoints page — per-route rule management with filters.

== Changelog ==

= 1.0.0 =
* Initial release.
* Per-endpoint rules (Open / Require Auth / Block).
* API Key authentication (2 keys on free tier).
* JWT authentication with built-in token endpoint.
* Auto-discovery of all registered REST routes.
* Lockdown mode and REST index hiding.
* Clean admin UI with tabbed navigation.
