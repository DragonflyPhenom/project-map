# Project Map

`iaroslav-khmel/project-map` is a Composer package and CLI tool that statically scans PHP projects and builds a first technical map: classes, methods, method calls, framework routes and models.

It is framework-agnostic by default and includes static MVP adapters for Laravel and Symfony. It never scans `vendor/` and does not execute the analysed application in generic mode.

## Requirements

- PHP `^8.2|^8.3|^8.4`
- Composer

## Local Development Via Path Repository

In the project where you want to test the package, add a path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../project-map",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Install it:

```bash
composer require --dev iaroslav-khmel/project-map:@dev
```

Run the scanner:

```bash
vendor/bin/project-map scan
```

## CLI

```bash
vendor/bin/project-map scan \
  --path=. \
  --output=storage/project-map \
  --format=json,dot,html \
  --framework=auto \
  --exclude=vendor,node_modules,storage,bootstrap/cache,var/cache
```

Options:

- `--path` project path, defaults to current directory
- `--output` output directory, defaults to `.project-map`
- `--format` comma-separated formats: `json`, `dot`, `html`
- `--framework` one of `auto`, `laravel`, `symfony`, `generic`
- `--exclude` comma-separated directories to skip; `vendor` is always excluded

## Output

The JSON file is written to:

```text
.project-map/project-map.json
```

The DOT graph is written to:

```text
.project-map/project-map.dot
```

The optional HTML report is written to:

```text
.project-map/index.html
```

## Current MVP

Generic PHP scanning includes namespaces, class-like declarations, inheritance, interfaces, traits, method signatures, visibility, parameters, return types, method calls, static calls and object creation. Dynamic calls that cannot be resolved are stored as `unknown_call`.

Laravel support includes static AST parsing of `Route::get/post/put/patch/delete/resource/apiResource`, Eloquent models, table names, fillable/guarded/casts/hidden/appends, relations and best-effort migration fields.

Symfony support includes attribute routes and Doctrine entity attributes. YAML route parsing is reported as an MVP warning instead of failing.

## Development

```bash
composer install
composer test
```
