# PhoenixWP Gift — FAQ & Anleitung

## Wo erscheint das Geschenk-Label?

| Bereich | Sichtbares Label (Einstellung „Gift label“) |
|---------|---------------------------------------------|
| Mini-Warenkorb | Ja |
| Klassischer Checkout (Shortcode) | Ja |
| Warenkorbseite | Nein |
| WooCommerce Cart Block | Nein |
| WooCommerce Checkout Block | Nein |

Das ist bewusst so: Auf Warenkorbseite und in Blocks sind Theme- und HTML-Konflikte häufig. Stattdessen markiert das Plugin die Geschenk-Zeile mit der CSS-Klasse **`phoenix-gift-for-woocommerce-cart-item`** (klassisch und in Blocks).

Preis **0,00 €**, Menge **1** und automatisches Hinzufügen/Entfernen gelten überall unverändert.

---

## Geschenk auf Warenkorbseite und mit Blocks hervorheben

### Schritt 1: Zusätzliches CSS anlegen

**WordPress:** *Design → Customizer → Zusätzliches CSS*  
(oder *Appearance → Customize → Additional CSS*)

### Schritt 2: Beispiel einfügen und Text anpassen

Der Text in `content:` ist **fest im CSS** — er übernimmt **nicht** automatisch den Wert aus den Plugin-Einstellungen („Gift label“). Trage dort den gewünschten Shop-Text ein (z. B. `Gratisgeschenk`).

**Cart Block & Checkout Block (Bestellübersicht):**

```css
.phoenix-gift-for-woocommerce-cart-item .wc-block-components-product-name::after {
	content: "Gratisgeschenk";
	display: inline-block;
	margin-inline-start: 0.35em;
	padding: 0.1em 0.45em;
	font-size: 0.75em;
	font-weight: 600;
	line-height: 1.4;
	vertical-align: middle;
	border-radius: 3px;
	background: #e8f5e9;
	color: #2e7d32;
}
```

**Klassischer Warenkorb (Shortcode, Tabellen-Template):**

```css
.woocommerce-cart .phoenix-gift-for-woocommerce-cart-item td.product-name::after {
	content: "Gratisgeschenk";
	display: inline-block;
	margin-inline-start: 0.35em;
	padding: 0.1em 0.45em;
	font-size: 0.75em;
	font-weight: 600;
	border-radius: 3px;
	background: #e8f5e9;
	color: #2e7d32;
}
```

**Optional — gesamte Zeile dezent hervorheben:**

```css
.phoenix-gift-for-woocommerce-cart-item {
	background-color: #f9fbf9;
}
```

### Hinweise

- **Theme-Test:** Nach dem Speichern Warenkorb und Checkout mit Geschenk im Warenkorb prüfen. Selektoren können je Theme leicht abweichen — im Browser „Element untersuchen“ und Klasse `phoenix-gift-for-woocommerce-cart-item` auf der Zeile suchen.
- **Kein Label nötig:** Viele Shops reichen **0,00 €** und die Produktbezeichnung; zusätzliches CSS ist optional.
- **Pro (geplant):** Sichtbares Label auch in Blocks ohne eigenes CSS.

---

## Weitere Fragen

### Warum wird das Geschenk nicht hinzugefügt?

- Regel in den Einstellungen **aktiviert**?
- **Geschenk-Produkt** gewählt und **kaufbar** (veröffentlicht, Lager ok)?
- **Schwelle** erreicht? (Mindest-**Brutto**-Warenkorbwert ohne Geschenk-Zeile **oder** Mindest-Artikelanzahl ohne Geschenk)
- Geschenk-Produkt nicht bereits manuell mit anderer Menge im Warenkorb (Konflikt vermeiden — Geschenk nur über das Plugin)

### Brauche ich PhoenixWP Core?

Nein für Free-Funktionen. Core wird für Suite/Lizenz-Integration empfohlen.

### HPOS und Blocks?

Ja — kompatibel deklariert. Geschenk-Logik läuft über WooCommerce Cart/Store API.

---

**Online (Shop-Betreiber):** [phoenixwp.com/phoenix-wp-gift/](https://phoenixwp.com/phoenix-wp-gift/) · EN: [/en/phoenix-wp-gift/](https://phoenixwp.com/en/phoenix-wp-gift/)

Siehe auch: [PLUGIN.md](PLUGIN.md) · Kanonische Spec: [PHOENIX-WP-GIFT.md](../../phoenix-wp-core/docs/plugins/PHOENIX-WP-GIFT.md)
