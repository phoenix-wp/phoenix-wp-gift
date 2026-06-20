# Private Pro source (Gift)

**Never commit `premium/` to public GitHub.** This folder is listed in `.gitignore`.

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

## Local setup

1. Keep `premium/` only on trusted machines (or private storage).
2. Clone public `phoenix-wp-gift` → copy `premium/` from secure backup.
3. WpOrg-only work does not require `premium/`; Freemius builds and Pro QA do.

## wp.org compliance

Pro code is **physically absent** from the org ZIP — not license-gated in the same package. Free tier is fully usable without Pro files.
