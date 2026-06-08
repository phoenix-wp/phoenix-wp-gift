# PhoenixWP Gift Product — Technische Referenz

> **Kanonische Spec:** [`phoenix-wp-core/docs/plugins/PHOENIX-WP-GIFT.md`](../../phoenix-wp-core/docs/plugins/PHOENIX-WP-GIFT.md)  
> **Session-Handoff:** [`phoenix-wp-core/docs/NEXT-SESSION.md`](../../phoenix-wp-core/docs/NEXT-SESSION.md)  
> **Agent-Workflow:** [`phoenix-wp-core/docs/AGENT-HANDOFF.md`](../../phoenix-wp-core/docs/AGENT-HANDOFF.md)

| Field | Value |
|-------|-------|
| Slug | `phoenix-wp-gift` |
| Version | **1.0.0** (Launch; intern vorher 1.2.x–1.6.x auf Staging) |
| Namespace | `PhoenixWP\Gift\` |
| Type | Extension (Free + Pro via Freemius) |
| Pro-Preis | 29 € / 29 $ / Jahr |
| Staging-Tests | `staging.vitalstoffversand.com` |

---

## Repo & Abhängigkeiten

| Abhängigkeit | Pflicht? |
|--------------|----------|
| WordPress 6.7+ | ✅ |
| PHP 8.2+ | ✅ |
| WooCommerce | ✅ |
| phoenix-wp-core | Empfohlen (Registry, Lizenz-Tier) |

Sibling-Repo zu `phoenix-wp-core` — **nicht** im Core-Ordner entwickeln.

---

## Architektur (Kernklassen)

| Bereich | Klasse / Datei |
|---------|----------------|
| Bootstrap | `src/Plugin.php` |
| Settings (Free) | `src/Settings.php` |
| Regeln (Pro) | `src/Rules/Rules_Repository.php` |
| Auflösung Upgrade/Additional | `src/Rules/Rule_Resolver.php` |
| Bedingungen | `Condition_Evaluator`, `Schedule_Evaluator`, `Audience_Evaluator`, `Cart_Content_Evaluator` |
| Geschenk-Optionen | `src/Rules/Gift_Options_Helper.php` |
| Tier-Gruppen | `src/Rules/Upgrade_Group_Helper.php` |
| Warenkorb | `src/Cart/Gift_Handler.php` |
| Admin Menü | `src/Admin/Menu.php` |
| Admin Regeln | `src/Admin/Rules_Admin.php` |
| Import/Export | `src/Admin/Tools_Admin.php`, `Rules_Exporter`, `Rules_Importer` |
| Statistik | `src/Stats/Gift_Stats.php`, `src/Admin/Stats_Admin.php` |
| Progress | `src/Frontend/Progress_Shortcode.php`, `Progress_Calculator.php`, `Progress_Rest.php` |
| Customer Choice | `src/Frontend/Gift_Choice.php`, `Gift_Choice_Rest.php` |
| Lizenz | `src/Freemius/License_Bridge.php` |

### Assets

| Datei | Zweck |
|-------|-------|
| `assets/js/admin-rules.js` | Trigger-Toggle, Gift-Optionen, Variation-AJAX |
| `assets/js/admin-tools.js` | Import/Export |
| `assets/js/gift-progress.js` | Live Progress |
| `assets/js/gift-choice.js` | Customer Choice (REST) |
| `assets/js/gift-blocks.js` | Blocks-Kompatibilität |
| `assets/css/gift.css` | Frontend |
| `assets/css/admin.css` | Admin |

---

## Daten

| Option / Meta | Inhalt |
|---------------|--------|
| `phoenix_wp_gift_settings` | Free-Einstellungen (eine Regel) |
| `phoenix_wp_gift_rules` | Pro-Regeln (Array) |
| Cart-Flag | `phoenix_wp_gift` |
| Cart/Order Rule-ID | `phoenix_wp_gift_rule_id` |
| Session Choice | `phoenix_wp_gift_chosen_{rule_id}` |

### Regel-Schema (Auszug)

```php
'id', 'name', 'enabled', 'priority',
'combine_mode',      // additional | upgrade
'upgrade_group',     // z. B. default
'gift_selection',    // auto | customer
'gift_options',      // [{ product_id, variation_id }, ...]
'gift_label',
'trigger_type',      // subtotal | item_quantity
'min_subtotal', 'min_item_quantity',
'require_product_ids', 'require_category_ids', 'require_tag_ids',
'audience', 'user_roles',
'date_start', 'date_end', 'weekdays',
```

---

## Feature Gates

```php
phoenix_wp_gift_is_pro_active( 'multiple_rules' );  // Regeln, Choice
phoenix_wp_gift_is_pro_active( 'progress_hint' );   // Progress
phoenix_wp_gift_is_pro_active( 'import_export' );   // Tools
phoenix_wp_gift_is_pro_active( 'stats' );           // Statistics
```

Bridge: `License_Bridge` → Core `phoenix_wp_core_license_remote_validate` + Freemius.

---

## REST API

| Route | Methode | Auth |
|-------|---------|------|
| `/wp-json/phoenix-wp-gift/v1/progress` | GET | öffentlich (Cart-Kontext) |
| `/wp-json/phoenix-wp-gift/v1/gift-choice` | GET | öffentlich |
| `/wp-json/phoenix-wp-gift/v1/gift-choice/select` | POST | Nonce |

---

## Shortcodes

| Shortcode | Gate | Hinweis |
|-----------|------|---------|
| `[phoenix_wp_gift_progress]` | `progress_hint` | Live via `gift-progress.js` |
| `[phoenix_wp_gift_choice]` | `multiple_rules` | **Empfohlen für Cart Blocks** |

---

## Admin — Regel-Formular (1.6.6)

1. Rule name → 2. Active → 3. When this rule matches → 4. Gift tier group → 5. Gift products → 6. Gift label → 7. Customers → 8. Condition → 9. Min subtotal/qty → 10. Cart must contain → 11. Schedule → 12. Advanced

---

## Test-Status (2026-05-25)

| Test | Status |
|------|--------|
| **Abschlusstest Pro v1.0** | ✅ **komplett, keine Fehler** |
| HPOS, Customer Choice, Upgrade, Progress | ✅ |
| Import/Export/Stats | ✅ |
| Admin Feldreihenfolge | ✅ |

**Nächster Schritt:** Go-to-Market — `phoenix-wp-core/docs/NEXT-SESSION.md`

---

## Versionen 1.6.x

| Version | Fix / Feature |
|---------|---------------|
| 1.6.0 | Variations + Customer Choice |
| 1.6.1 | Blocks render_block |
| 1.6.2 | Session-basierte Choice |
| 1.6.3 | Live Picker REST GET |
| 1.6.4 | REST POST Select |
| 1.6.5–1.6.6 | Admin Feldreihenfolge |

---

## Übersetzungen (i18n)

| Datei | Zweck |
|-------|--------|
| `languages/phoenix-wp-gift.pot` | Template (~175 Strings), WP-CLI |
| `languages/phoenix-wp-gift-de_DE.po` | Deutsch — **in Loco ausfüllen** |
| `languages/phoenix-wp-gift-de_DE.mo` | Kompiliert (nach Übersetzung neu: `wp i18n make-mo`) |

Freemius: `includes/freemius/languages/` (Domain `freemius`) — nicht anfassen.

Regenerieren: `phoenix-wp-core/scripts/generate-i18n.ps1` — Details in `phoenix-wp-core/docs/I18N-STRATEGY.md`

---

## FAQ & Marketing

| Ort | Link |
|-----|------|
| phoenixwp.com DE | `/docs/phoenix-wp-gift/` |
| Plugin FAQ | [FAQ.md](FAQ.md) |
| Freemius | [FREEMIUS.md](FREEMIUS.md) |

---

## Hooks

| Hook | Type |
|------|------|
| `phoenix_wp_gift_loaded` | action |
| `phoenix_wp_gift_progress_message` | filter |
| `phoenix_wp_gift_progress_html` | filter |
| `phoenix_wp_core_register_modules` | action |
