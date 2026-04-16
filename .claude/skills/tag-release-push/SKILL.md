---
name: tag-release-push
description: Bump version, commit, tag, and push to trigger a GitHub release. Runs bump-version internally.
argument-hint: patch|minor|major
allowed-tools: Read Edit Bash Skill
---

# Tag, Release & Push

Bump the plugin version, commit the changes, create a git tag, and push everything to trigger the GitHub Actions release workflow.

## Arguments

`$ARGUMENTS` — one of: `patch`, `minor`, `major`

If the argument is missing or invalid, show usage and stop:

```
Usage: /tag-release-push patch|minor|major

  patch  — 1.2.3 → 1.2.4
  minor  — 1.2.3 → 1.3.0
  major  — 1.2.3 → 2.0.0
```

## Instructions

### Step 1 — Validate argument

If `$ARGUMENTS` is not exactly `patch`, `minor`, or `major` — print usage (above) and stop.

### Step 2 — Check working tree

Run `git status --short`. If there are any uncommitted changes, stop and tell the user:

```
Error: working tree has uncommitted changes. Commit or stash them first.
```

### Step 3 — Read current version (before bump)

Read `ihumbak-woo-bulk-edit.php` and extract the current version from the `* Version:` header line. Store it as OLD_VERSION.

### Step 4 — Run bump-version

Invoke the `bump-version` skill with `$ARGUMENTS` as the argument. Wait for it to complete.

### Step 5 — Read new version (after bump)

Read `ihumbak-woo-bulk-edit.php` again and extract the new version from the `* Version:` header. Store it as NEW_VERSION.

Confirm NEW_VERSION differs from OLD_VERSION. If they are the same, stop and report an error.

### Step 6 — Commit

Stage the three version files and create a commit:

```bash
git add ihumbak-woo-bulk-edit.php package.json
git commit -m "chore: bump version to {NEW_VERSION}"
```

### Step 7 — Create tag

```bash
git tag v{NEW_VERSION}
```

### Step 8 — Push branch and tag

```bash
git push origin HEAD
git push origin v{NEW_VERSION}
```

### Step 9 — Report

```
Released: {OLD_VERSION} → {NEW_VERSION}

Commits pushed to:  origin/{current-branch}
Tag pushed:         v{NEW_VERSION}

GitHub Actions release workflow should now be running.
Check: https://github.com/michalstaniecko/ihumbak-woo-bulk-edit/actions
```
