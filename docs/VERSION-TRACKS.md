# Version tracks — Gift

Two parallel version lines in **one repo** (`main` = dev/staging).

| Track | Version | Source of truth | Build |
|-------|---------|-----------------|-------|
| **Dev / Staging / Freemius** | **1.0.3** | `phoenix-gift-for-woocommerce.php` + root `readme.txt` | `.\scripts\build-release.ps1 -Channel Freemius` |
| **wordpress.org (live)** | **1.0.3** | `wp-org-release/ACTIVE_VERSION` + `wp-org-release/1.0.3/readme.txt` | `.\scripts\build-release.ps1 -Channel WpOrg` |

## Rules

- **Code fixes** (Notices, Freemius, Build): always in `main` — both tracks get them on next build.
- **wp.org readme/changelog** for the active line: edit only under `wp-org-release/{version}/`.
- **Next Dev bump** (e.g. 1.0.4): header + root `readme.txt` only; wp.org stays on `ACTIVE_VERSION` until SVN tag bump.
- **wp.org patch** while Dev runs ahead: new `wp-org-release/1.0.x/`, bump `ACTIVE_VERSION`, WpOrg build only.

## `dist/` (keep only)

| File | Track |
|------|-------|
| `phoenix-gift-for-woocommerce-1.0.3.zip` | Freemius / Staging |
| `phoenix-gift-for-woocommerce-1.0.3-wporg.zip` | wordpress.org |

## Freemius vs wp.org

| Channel | Version | Dashboard / SVN |
|---------|---------|-----------------|
| wp.org | **1.0.3** | SVN `tags/1.0.3` ✅ |
| Freemius | **1.0.3** | ✅ Deployed (SDK **2.13.4**; ältere Versionen unreleased) |

See `docs/FREEMIUS-RELEASE-1.0.3.md`.

## References

- Core template: [`phoenix-wp-core/docs/WP-ORG-VERSION-TRACKS.md`](../../phoenix-wp-core/docs/WP-ORG-VERSION-TRACKS.md)
- Submit: [`phoenix-wp-core/docs/WP-ORG-SUBMISSION.md`](../../phoenix-wp-core/docs/WP-ORG-SUBMISSION.md)
