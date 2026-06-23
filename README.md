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
  --format=json,mmd,html \
  --framework=auto \
  --exclude=vendor,node_modules,storage,bootstrap/cache,var/cache
```

Options:

- `--path` project path, defaults to current directory
- `--output` output directory, defaults to `.project-map`
- `--format` comma-separated formats: `json`, `mmd`, `html`, `dot`
- `--framework` one of `auto`, `laravel`, `symfony`, `generic`
- `--exclude` comma-separated directories to skip; `vendor` is always excluded

## Output

The JSON graph payload is written to:

```text
.project-map/project-map.json
```

The Mermaid graph is written to:

```text
.project-map/project-map.mmd
```

The HTML report renders the Mermaid graph as the main project map and is written to:

```text
.project-map/index.html
```

DOT remains available as an optional extra format:

```text
.project-map/project-map.dot
```

## Current MVP

Generic PHP scanning includes namespaces, class-like declarations, inheritance, interfaces, traits, method signatures, visibility, parameters, return types, method calls, static calls and object creation. Dynamic calls that cannot be resolved are stored as `unknown_call` warnings.

Laravel support includes recursive AST parsing of `routes/`, route groups with prefix/middleware/controller context, `Route::get/post/put/patch/delete/resource/apiResource`, Eloquent models, table names, fillable/guarded/casts/hidden/appends, relations and best-effort migration fields.

Symfony support includes attribute routes and Doctrine entity attributes. YAML route parsing is reported as an MVP warning instead of failing.

## Development

```bash
composer install
composer test
```
