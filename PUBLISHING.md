# Publishing useyona/einvoice-php

Packagist installs from git tags: there is no upload step. A release is a tag `v<version>` on `main`.

## One-time setup (owner)

1. Sign in to [packagist.org](https://packagist.org) and claim the vendor `useyona` (the first package
   submitted under a vendor name reserves it for that account).
2. Submit the package: Packagist → Submit → `https://github.com/Elyonar/einvoice-php`. Packagist reads
   `composer.json` (`useyona/einvoice-php`) and lists every tag matching `v<semver>`.
3. Install the GitHub hook so new tags appear at once: GitHub → the repository → Settings → Webhooks →
   add `https://packagist.org/api/github?username=<packagist user>` with the Packagist API token as the
   secret, content type `application/json`, event "Just the push event". (Packagist also offers the
   GitHub App integration, which does the same without a secret.)

Nothing else is stored anywhere: the release workflow needs only the repository's own `GITHUB_TOKEN`
to push the tag.

## Releasing

1. Merge the changes to `main`.
2. On `main`: set the new version in `src/Version.php` (`Version::VERSION`; it is also the
   `User-Agent`), move the CHANGELOG's unreleased section under the version and date, run
   `make guides` (it records the version in `guides/guides.json`), commit
   `chore(release): <version>` and push.
3. The release workflow runs on a `src/Version.php` change on `main`: lint → test → sync-check →
   guides-check → tag `v<version>` → GitHub Release. It skips when the tag already exists. Packagist
   picks the tag up through the hook within a minute; `composer require useyona/einvoice-php` then
   resolves the new version.

Local fallback: `git tag -a v0.1.0 -m "Release v0.1.0" && git push origin v0.1.0` (Packagist
updates from the hook; or press "Update" on the package page).

`composer.json` carries no `version` field on purpose: Packagist derives the version from the tag.

## While einvoice-js is private

CI cannot fetch the pinned einvoice-js commit without a credential, so its `sync-check` job skips with a
notice (no `EINVOICE_JS_TOKEN` secret is set; the owner chose this over storing a credential). Run the
guard locally before every release:

```bash
EINVOICE_JS_DIR=../elyonar-sdk make sync-check
```

Once einvoice-js is public, the job runs in CI unchanged.
