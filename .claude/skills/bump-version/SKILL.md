---
name: bump-version
description: Bump the plugin version (patch, minor, or major) across all version files.
argument-hint: patch|minor|major
allowed-tools: Read Edit Bash
---

# Bump Version

Increment the plugin version across all files that contain it.

## Arguments

`$ARGUMENTS` — one of: `patch`, `minor`, `major`

If the argument is missing or invalid, show usage and stop:

```
Usage: /bump-version patch|minor|major

  patch  — 1.2.3 → 1.2.4  (bug fixes)
  minor  — 1.2.3 → 1.3.0  (new features, backwards-compatible)
  major  — 1.2.3 → 2.0.0  (breaking changes)
```

## Version files

This project stores the version in three places — update all of them:

| File | Location |
|------|----------|
| `ihumbak-woo-bulk-edit.php` | Header line: `* Version: X.Y.Z` |
| `ihumbak-woo-bulk-edit.php` | Constant: `define('IWBE_VERSION', 'X.Y.Z');` |
| `package.json` | Field: `"version": "X.Y.Z"` |

## Instructions

### Step 1 — Validate argument

If `$ARGUMENTS` is not exactly `patch`, `minor`, or `major` — print usage (above) and stop.

### Step 2 — Read current version

Read `ihumbak-woo-bulk-edit.php` and extract the current version from the header line (`* Version:`). This is the single source of truth.

### Step 3 — Calculate new version

Split the current version into `MAJOR.MINOR.PATCH` integers and apply the bump:

- `patch` → increment PATCH, keep MAJOR and MINOR
- `minor` → increment MINOR, reset PATCH to 0, keep MAJOR
- `major` → increment MAJOR, reset MINOR and PATCH to 0

### Step 4 — Update all version files

Update each file using exact string replacement (do not rewrite the whole file):

1. **`ihumbak-woo-bulk-edit.php` — header**: replace `* Version:           {old}` with `* Version:           {new}` (preserve spacing)
2. **`ihumbak-woo-bulk-edit.php` — constant**: replace `define('IWBE_VERSION', '{old}');` with `define('IWBE_VERSION', '{new}');`
3. **`package.json`**: replace `"version": "{old}"` with `"version": "{new}"`

### Step 5 — Verify

After editing, read back the three locations and confirm each shows the new version. If any mismatch is found, report it clearly.

### Step 6 — Report

Print a concise summary:

```
Version bumped: {old} → {new}

Updated files:
  ihumbak-woo-bulk-edit.php  (header + IWBE_VERSION constant)
  package.json

Next steps:
  1. Review changes: git diff
  2. Commit: git add -A && git commit -m "chore: bump version to {new}"
  3. Tag:    git tag v{new} && git push origin develop && git push origin v{new}
```
