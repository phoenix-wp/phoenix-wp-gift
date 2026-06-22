# Freemius — Release 1.0.3 (Gift Pro)

> Dev track **1.0.3** · wp.org **1.0.3** (SVN live)

---

## Build ZIP

```powershell
cd C:\Users\mail\OneDrive\Desktop\Phoenix-WP\phoenix-wp-gift
.\scripts\build-release.ps1 -Channel Freemius
# → dist/phoenix-gift-for-woocommerce-1.0.3.zip
```

**Voraussetzung:** lokales `premium/` ([`PREMIUM-PRIVATE.md`](PREMIUM-PRIVATE.md)).

---

## Freemius Dashboard (User)

1. Product **31421** — PhoenixWP Gift
2. **Deployment** → **+ Add New Version** → ZIP: `dist/phoenix-gift-for-woocommerce-1.0.3.zip`
3. Release notes (EN) — siehe unten
4. Status: **Released**

**Hinweis:** Wenn Dashboard noch **1.0.1** zeigt, ersetzen oder neue Version hinzufügen (nicht wp.org-Review blockieren).

---

## Release notes (EN, Kurz)

- Freemius upgrade pricing: annual price shown prominently (`show_annual_in_monthly` filter).
- Aligns with wordpress.org **1.0.3** (same codebase; dual-build WpOrg/Freemius).
- Build guards: no dev artifacts in wp.org ZIP, UTF-8 BOM fix in main plugin file.

---

## wp.org (parallel)

```powershell
.\scripts\build-release.ps1 -Channel WpOrg
# → dist/phoenix-gift-for-woocommerce-1.0.3-wporg.zip
```

Frozen readme: `wp-org-release/1.0.3/readme.txt` · `ACTIVE_VERSION` = `1.0.3`.

Siehe [`VERSION-TRACKS.md`](VERSION-TRACKS.md) · [`FREEMIUS.md`](FREEMIUS.md).
