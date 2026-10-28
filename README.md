# Rowdy Crowd

Custom WordPress plugin for the Rowdy Crowd WooCommerce site. It consolidates the site's custom functionality (product renderer, cart flow, quoting, geolocation, and more) into one plugin, with each feature in its own module.

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Installation

The repository root is the plugin folder. Deploy it to `wp-content/plugins/rowdy-crowd/` and activate **Rowdy Crowd** under Plugins.

Once activated, administrators see a green "Hello World from Rowdy Crowd" notice in wp-admin. That confirms the deployment worked.

## Structure

```
rowdy-crowd.php      Plugin header, global constants, bootstrap
includes/            Core: autoloader, plugin class, base module class (see includes/README.md)
modules/             One folder per feature, each with its own README (see modules/README.md)
```

## Development

See [CLAUDE.md](CLAUDE.md) for project context, coding standards and workflow rules.
