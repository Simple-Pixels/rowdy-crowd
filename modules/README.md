# Modules

Each feature lives in its own folder here, with its own `README.md`.

## Adding a module

1. Create a folder named after the module ID, e.g. `modules/quote-generation/`.
2. Add `class-module.php` declaring `RowdyCrowd\Modules\Quote_Generation\Module`, extending `RowdyCrowd\Core\Module`:

   ```php
   namespace RowdyCrowd\Modules\Quote_Generation;

   use RowdyCrowd\Core\Module as Base_Module;

   defined( 'ABSPATH' ) || exit;

   final class Module extends Base_Module {

   	public function get_id(): string {
   		return 'quote-generation';
   	}

   	public function register(): void {
   		// add_action() / add_filter() calls go here.
   	}
   }
   ```

3. Add the class to `Plugin::MODULES` in `includes/class-plugin.php`.
4. Write the module's `README.md`: what it does, its hooks, settings, and files.

Other classes in the module follow the same naming: `RowdyCrowd\Modules\Quote_Generation\Pdf_Builder` loads from `modules/quote-generation/class-pdf-builder.php`. Subfolders map to sub-namespaces.

## Modules

| Module | Status | Description |
| --- | --- | --- |
| [hello-world](hello-world/) | Placeholder | Confirms the plugin is deployed. |
| [geolocation](geolocation/) | In progress | AU/US regions by domain, `aunz`/`usa` visibility classes, first-visit geo redirect, `[region_switcher]`. |
