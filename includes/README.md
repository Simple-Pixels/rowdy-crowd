# Core (`includes/`)

Shared plugin infrastructure. Namespace: `RowdyCrowd\Core`.

| File | Class | Purpose |
| --- | --- | --- |
| `class-autoloader.php` | `Autoloader` | Maps `RowdyCrowd\Core\*` to `includes/` and `RowdyCrowd\Modules\*` to `modules/`, using WordPress `class-*.php` file names. |
| `class-plugin.php` | `Plugin` | Singleton. Boots on `plugins_loaded`, instantiates and registers each module in `Plugin::MODULES`. |
| `class-module.php` | `Module` | Abstract base for all modules. Provides `get_id()`, `register()`, `is_enabled()`, and `path()` / `url()` helpers. |

## Global constants

Defined in `rowdy-crowd.php`:

| Constant | Value |
| --- | --- |
| `ROWDY_CROWD_VERSION` | Plugin version. Keep in sync with the plugin header. |
| `ROWDY_CROWD_FILE` | Absolute path to the main plugin file. |
| `ROWDY_CROWD_PATH` | Absolute path to the plugin folder (trailing slash). |
| `ROWDY_CROWD_URL` | URL of the plugin folder (trailing slash). |

## Hooks

| Hook | Type | Description |
| --- | --- | --- |
| `rowdy_crowd_modules` | filter | Filters the array of module class names before they load. |
| `rowdy_crowd_loaded` | action | Fires after all modules are registered. Receives the `Plugin` instance. |

Access a loaded module with `RowdyCrowd\Core\Plugin::instance()->get_module( 'module-id' )`.
