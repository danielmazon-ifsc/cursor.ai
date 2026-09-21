# cursor.ai

IFSC Mooc project — Moodle installation and custom assets imported from `~/Sites/moodle-ifsc-mooc`.

## Moodle version

| Field | Value |
|-------|-------|
| Release | **5.2.3+ (Build: 20260916)** |
| Core version | `2026042003.01` |
| Branch | `MOODLE_502_STABLE` (weekly/plus line) |
| Upstream commit | `3fef2ed3c955977e7759ff3d6924e3009a82d60e` |
| Upstream tag baseline | `v5.2.2` (weekly line continues as 5.2.3+) |
| Source | [github.com/moodle/moodle](https://github.com/moodle/moodle) |

Upgraded from Moodle 4.5.13 (import branch `cursor/import-moodle-ifsc-mooc-846a`). Moodle 5.2 supports a direct upgrade from 4.4+; no intermediate version is required.

### Web server

Moodle 5.2 uses a `public/` webroot. Point the document root at `public/` (not the repo root). See [Moodle 5.2 routing docs](https://moodledev.io/docs/5.2/gettingstarted/requirements).

### Runtime requirements (Moodle 5.2)

- PHP 8.3+ (64-bit), `sodium` extension, `max_input_vars` ≥ 5000
- MySQL 8.4+, MariaDB 10.11+, PostgreSQL 16+, or SQL Server 2019+

After deploying files, run `php admin/cli/upgrade.php` from the repo root (or via the web upgrade UI).

## Import (Mac source)

Source: `~/Sites/moodle-ifsc-mooc` on Daniel's Mac (local, not on GitHub).

> **Note:** The import script copies the Mac's flat 4.x layout. After upgrading to Moodle 5.2, re-importing will overwrite the `public/` structure. Use only to refresh custom assets, then move them into `public/theme/` and `public/course/format/`.

```bash
./scripts/import-moodle-ifsc-mooc.sh
```

## Contents

| Item | Path | Notes |
|------|------|-------|
| Moodle core | repo root + `public/` | 5.2.3+ install tree |
| Custom theme | `public/theme/ifsc_mooc/` | IFSC Mooc child theme (Boost-based) |
| Theme archive | `public/theme/ifsc_mooc.zip` | Packaged theme export |
| Course format | `public/course/format/remuiformat/` | RemUI course format plugin |
| Site config | `config.php` | DB password redacted; copy from `config-dist.php` for new installs |
| Version manifest | `MOODLE_VERSION` | Upstream pin and upgrade metadata |

**Not included:** `moodledata` (`/Users/mazon/moodledataifscmooc`, ~38 MB) — runtime uploads/cache; stays on the Mac.
