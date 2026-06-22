# wordpress.org release track (Gift)

Frozen **readme.txt** and version metadata for wp.org — separate from future Dev bumps on `main`.

| File | Purpose |
|------|---------|
| `ACTIVE_VERSION` | Current wp.org line (`1.0.3`) — used by `build-release.ps1 -Channel WpOrg` |
| `{version}/readme.txt` | Validator + SVN stable tag source for that wp.org release |

**Build:** `.\scripts\build-release.ps1 -Channel WpOrg` → `dist/phoenix-gift-for-woocommerce-{ACTIVE_VERSION}-wporg.zip`

**Bump wp.org line:** copy/adjust `{version}/readme.txt`, set `ACTIVE_VERSION`, rebuild WpOrg only.

Not shipped in plugin ZIP (`wp-org-release/` excluded from stage).

See `docs/VERSION-TRACKS.md` · Core: `phoenix-wp-core/docs/WP-ORG-VERSION-TRACKS.md`.
