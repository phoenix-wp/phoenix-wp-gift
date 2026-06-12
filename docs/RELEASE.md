# Release & deploy scripts (agent workflow)

| Script | Purpose |
|--------|---------|
| `build-release.ps1` | ZIP only → `dist/` |
| `publish-release.ps1` | Build + verify + GitHub release asset (`gh release upload`) |

## Gift — Freemius re-deploy (same version)

```powershell
cd phoenix-wp-gift
.\scripts\publish-release.ps1 -Version 1.0.0
```

Then **Freemius Dashboard** (no API): Deployment → delete old 1.0.0 → upload `dist/phoenix-wp-gift-1.0.0.zip` → Released.

ZIP is built via `%TEMP%` staging (OneDrive-safe). Root folder inside ZIP: `phoenix-wp-gift/`.

## Bridge — live shop

```powershell
cd phoenix-wp-bridge-german-market-wcml
.\scripts\publish-release.ps1 -Deploy -SkipGitHub
```

→ `dist/phoenix-wp-bridge-german-market-wcml-1.0.0-deploy.zip`

## Core — live shop

```powershell
cd phoenix-wp-core
.\scripts\publish-release.ps1
```

→ `dist/phoenix-wp-core-1.0.0.zip`

See `phoenix-wp-core/docs/FREEMIUS-RELEASE-1.0.0.md` for Freemius dashboard steps.
