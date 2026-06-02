# PhoenixWP Gift Product

> Portfolio-Spec: [`phoenix-wp-core/docs/plugins/PHOENIX-WP-GIFT.md`](../../phoenix-wp-core/docs/plugins/PHOENIX-WP-GIFT.md) (kanonisch).

| Field | Value |
|-------|-------|
| Slug | `phoenix-wp-gift` |
| Type | Modul (Extension) |
| Tier | Free (+ Pro via Lizenz) |
| Pro-Preis | 29 € / 29 $ / Jahr |

## Free / Pro (Kurz)

Siehe Feature-Matrix in der kanonischen Doku.

- **Schwelle:** nur **Brutto** (inkl. Zeilen-MwSt.)
- **Geschenk-Menge:** immer **1** pro Geschenk-Zeile (Free + Pro); Pro = mehrere Geschenke über **Regeln**, nicht Mengen-Stepper
- **`gift_multi_quantity`:** gestrichen
- **Geschenk-Label:** nur Mini-Warenkorb + klassischer Checkout; Warenkorbseite / Blocks → nur CSS-Klasse `.phoenix-wp-gift-cart-item`

## FAQ & Anleitung (Shop-Betreiber)

| Ort | URL |
|-----|-----|
| **phoenixwp.com (öffentlich)** | [DE](https://phoenixwp.com/docs/phoenix-wp-gift/) · [EN](https://phoenixwp.com/en/docs/phoenix-wp-gift/) |
| Plugin-Repo (Kurzfassung) | [docs/FAQ.md](FAQ.md) |
| Quelltext für WP-Seiten | [phoenix-wp-core/docs/site/](../../phoenix-wp-core/docs/site/README.md) |

## Integration hooks

| Hook | Type |
|------|------|
| `phoenix_wp_gift_loaded` | action |
| `phoenix_wp_core_register_modules` | action (constructor) |

## Feature gates

| Slug | Tier |
|------|------|
| `gift_advanced_rules` | pro |
