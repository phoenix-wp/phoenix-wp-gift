# Private Pro source (Gift)

**Never commit `premium/` to public GitHub.** This folder is listed in `.gitignore` until migration to a private premium repo (see below).

> **Geplant:** [`phoenix-wp-gift-premium`](../../phoenix-wp-core/docs/PREMIUM-REPOS.md) — privates Repo + Submodule `premium/`.  
> **Timing (Agents):** **Automatisch nach wp.org-Freigabe** — `phoenix-wp-core/docs/PREMIUM-REPOS.md` **§4**. **Nicht** während laufendem wp.org-Review. Script: `phoenix-wp-core/scripts/setup-premium-submodule.ps1`.

## Layout

```
premium/
  bootstrap.php          # Loaded when folder exists (Freemius ZIP / local dev)
  Premium_Module.php     # Registers Pro admin, storefront, cart handler
  src/                   # Pro PHP (same PSR-4 namespace as free src/)
    Admin/
    Cart/Gift_Handler_Pro.php
    Frontend/
    Rules/
    Stats/
```

## Builds

| Channel | Command | Output |
|---------|---------|--------|
| **wordpress.org** (free only) | `.\scripts\build-release.ps1 -Channel WpOrg` | `dist/phoenix-gift-for-woocommerce-{v}-wporg.zip` |
| **Freemius** (free + Pro) | `.\scripts\build-release.ps1 -Channel Freemius` | `dist/phoenix-gift-for-woocommerce-{v}.zip` |

WpOrg ZIP: no `premium/`, no Pro classes under `src/`, `is_premium => false` in Freemius bootstrap.

Freemius ZIP: overlays `premium/src/` onto `src/` and includes `premium/bootstrap.php`.

### Display names (backend)

| Kanal | `Plugin Name` (Plugins-Liste) | Admin-Menü |
|-------|-------------------------------|------------|
| **wp.org** | Phoenix Gift for WooCommerce | Gift / PhoenixWP Gift |
| **Freemius** | Phoenix Gift for WooCommerce **Pro** | Gift Pro / PhoenixWP Gift Pro |

Slug bleibt überall `phoenix-gift-for-woocommerce`. Der Pro-Name wird im Freemius-Build per `Set-PhoenixFreemiusPluginDisplayName` gesetzt; Laufzeit-Labels nutzen `phoenix_wp_gift_is_pro_distribution()` (`premium/` vorhanden).

**Connect opt-in:** Freemius SDK-Maske bei erster Aktivierung — WpOrg + Freemius ZIP (1.0.1 ✅). 1.0.2: Re-Audit via `Test-PhoenixFreemiusConnectBootstrap`, kein Code-Change erwartet. Checkliste: `phoenix-wp-core/docs/FREEMIUS-CONNECT-OPTIN.md`


Pro-Dateien wurden aus der **gesamten Git-Historie** entfernt (`scripts/purge-pro-from-history.ps1`):

1. 18 dedizierte Pro-Pfade aus allen Commits gelöscht  
2. Gemischte Dateien (`Gift_Handler.php`, `Plugin.php`, `Menu.php`, …) auf Free-Tier-Stand synchronisiert  
3. `git push --force` auf `main` + Tags

**Wiederholung (nur bei Bedarf):** Working tree clean → `.\scripts\purge-pro-from-history.ps1 -ForcePush`

`premium/` war nie committed und bleibt gitignored.

## Local setup

1. Keep `premium/` only on trusted machines (or private storage).
2. Clone public `phoenix-wp-gift` → copy `premium/` from secure backup.
3. WpOrg-only work does not require `premium/`; Freemius builds and Pro QA do.

## Freemius Deployment (manuell)

**Nicht** über öffentliche GitHub Releases — nur [Freemius Dashboard](https://dashboard.freemius.com) → Product **31421** → **Deployment**.

| ZIP | Pfad |
|-----|------|
| Freemius (Free + Pro) | `dist/phoenix-gift-for-woocommerce-{version}.zip` |

```powershell
.\scripts\build-release.ps1 -Channel Freemius
```

### Wann User neu hochladen muss

**Agent meldet explizit**, wenn nach einem Freemius-Upload eine **neue oder geänderte** Freemius-ZIP gebaut wurde — User ersetzt dann die Version im Dashboard (Delete → Add New Version → Released).

Neu bauen + Bescheid geben bei Änderungen an:

- `premium/` (Pro-Quellcode)
- `src/` (Free/Shared, wenn Pro-Verhalten betroffen)
- `includes/freemius-gift.php`, Build-Skripte
- Version-Bump in Hauptdatei / `readme.txt`

**Stand 2026-06-20:** Freemius **1.0.1** vom User hochgeladen — lokales `dist/…-1.0.1.zip` (11:28) entspricht `main` (nur Doku/Purge-Skript danach, **kein** Pro-Runtime-Diff). **Kein erneuter Upload nötig**, bis nächste gemeldete Änderung.

## wp.org compliance

Pro code is **physically absent** from the org ZIP — not license-gated in the same package. Free tier is fully usable without Pro files.
