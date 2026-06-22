# WordPress.org Plugin Directory assets

These PNGs are uploaded to the **SVN `assets/` folder**, not to `trunk/`.

| File | Use |
|------|-----|
| `icon-256x256.png` | Plugin icon (high-DPI) |
| `icon-128x128.png` | Plugin icon |
| `banner-772x250.png` | Plugin banner |
| `banner-1544x500.png` | Plugin banner (retina) |

**Source of truth (design):** `phoenix-wp-core/local-images/` — regenerate via `phoenix-wp-core/scripts/generate-brand-assets.ps1`

**Do not** copy this folder into SVN `trunk/` — only into SVN `assets/`.

### Screenshots (SVN `assets/` only)

| File | Caption (matches `readme.txt` == Screenshots ==) |
|------|--------------------------------------------------|
| `screenshot-1.png` | Settings — rule, threshold type, gift product |
| `screenshot-2.png` | Gift label + threshold options |
| `screenshot-3.png` | Cart with free gift line |
| `screenshot-4.png` | Mini cart with badge |
| `screenshot-5.png` | Cart/Checkout block with gift line |

Capture from a **real** WordPress admin + storefront (staging). PNG, sRGB, **min. 1200 px wide** (4:3 or 16:10). No browser chrome; EN or DE UI is fine.

**Not** included in the plugin ZIP — upload only to SVN `assets/` alongside icons/banners.

See `phoenix-wp-core/docs/WP-ORG-SUBMISSION.md`.
