# Freemius — Gift Product (Phase 4)

> Product ID `31421` · Slug `phoenix-wp-gift` · Plan `pro`

---

## Integration

| File | Role |
|------|------|
| `includes/freemius/` | Official [WordPress SDK](https://github.com/Freemius/wordpress-sdk) |
| `includes/freemius-gift.php` | `fs_dynamic_init()` bootstrap |
| `src/Freemius/License_Bridge.php` | Pro license → `gift_*` feature gates + Core dashboard tier |

---

## Local development (optional)

In `wp-config.php` (never commit):

```php
define( 'PHOENIX_WP_GIFT_FS_SECRET_KEY', 'sk_…' );
```

---

## Activate license

1. **PhoenixWP → Gift Product** (or top-level Gift menu without Core)
2. Freemius **Account** submenu → enter license key from purchase email
3. Redirect lands on `admin.php?page=phoenix-wp-gift` (not `/wp-admin/phoenix-wp-gift`)
4. Admin shows: *Gift Pro license is active on this site.*

`first-path` in `freemius-gift.php` must stay `admin.php?page=phoenix-wp-gift`.

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
