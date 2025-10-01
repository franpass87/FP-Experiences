# Build & Release Guide

## Prerequisites

- PHP 8.0 or higher (8.2 recommended)
- Composer
- Zip utility (`zip` command)

## Build workflow

1. Optionally bump the version (patch by default):

   ```bash
   bash build.sh --bump=patch
   ```

2. Or set an explicit version:

   ```bash
   bash build.sh --set-version=1.2.3
   ```

The script installs production dependencies, prepares a clean `build/<slug>/` directory, and produces a timestamped zip archive ready for upload to WordPress.

## GitHub Actions release

To produce the zip artifact automatically, create a Git tag following the `v*` pattern and push it:

```bash
git tag v1.2.3
git push origin v1.2.3
```

The `build-plugin-zip` workflow runs on the tag, generates the plugin zip, and attaches it as an artifact.
