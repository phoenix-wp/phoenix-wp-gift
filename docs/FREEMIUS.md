# Freemius — Gift Product (Phase 4)

> Product ID `31421` · Slug `phoenix-gift-for-woocommerce` · Plan `pro`

---

## Connect / Opt-in (Aktivierung)

Beim **ersten Aktivieren** zeigt das Freemius SDK die Connect-Maske („Verpasse nie wieder ein wichtiges Update“). Gilt für **WpOrg-ZIP** und **Freemius-ZIP**.

| Status | Version |
|--------|---------|
| ✅ Verifiziert | **1.0.1** (beide Kanäle) |
| ✅ Build-Guards | **1.0.3** WpOrg + Freemius (`Test-PhoenixWpOrgZipPhpNoBom`, unexpected artifacts) |

**Freemius Dashboard:** Upload **1.0.3** wenn noch älter — siehe [`FREEMIUS-RELEASE-1.0.3.md`](FREEMIUS-RELEASE-1.0.3.md).

Checkliste: [`FREEMIUS-CONNECT-OPTIN.md`](../../phoenix-wp-core/docs/FREEMIUS-CONNECT-OPTIN.md)

---

## Integration

| File | Role |
|------|------|
| `vendor/freemius/` | Official [WordPress SDK](https://github.com/Freemius/wordpress-sdk) (wp.org-konform) |
| `includes/freemius-gift.php` | `fs_dynamic_init()` bootstrap |
| `src/Freemius/License_Bridge.php` | Pro license → `gift_*` feature gates + Core dashboard tier |

---

## Local development (optional)

In `wp-config.php` (never commit):

```php
define( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_FS_SECRET_KEY', 'sk_…' );
```

---

## Activate license

1. **PhoenixWP Gift → License** (standalone) or **PhoenixWP → Gift → License** (with Core)
2. Enter license key — or Freemius **Account** for full account UI
3. Optional first connect: link *First-time setup* on License page (Free or license + email opt-in)
4. `first-path` in `freemius-gift.php`: `admin.php?page=phoenix-gift-for-woocommerce`

**Freemius Dashboard (SDK Integration):** Top-level menu · slug `'phoenix-gift-for-woocommerce'` · path `admin.php?page=phoenix-gift-for-woocommerce` — see Core `FREEMIUS-PRODUCT-PLAYBOOK.md` C.1.2.

---

## Feature gates

```php
phoenix_wp_gift_is_pro_active( 'multiple_rules' );
```

Maps to Core slugs `gift_multiple_rules`, etc. (see `Plugin::register_feature_tiers()`).

**Pro cart/admin features** (multiple rules engine) — next implementation step.

---

## Upgrade URL

Filter `phoenix_wp_gift_upgrade_url` — Freemius `get_upgrade_url()` when SDK loaded.

Pricing pages on phoenixwp.com re-enable before public sales.
