# cursor.ai

IFSC Mooc project — Moodle installation and custom assets imported from `~/Sites/moodle-ifsc-mooc`.

## Import

Source: `~/Sites/moodle-ifsc-mooc` on Daniel's Mac (local, not on GitHub).

Re-run on the Mac:

```bash
./scripts/import-moodle-ifsc-mooc.sh
```

## Contents (imported 2026-09-21)

| Item | Path | Notes |
|------|------|-------|
| Moodle core | repo root | ~372 MB install tree (Moodle 4.x) |
| Custom theme | `theme/ifsc_mooc/` | IFSC Mooc child theme (Boost-based) |
| Theme archive | `theme/ifsc_mooc.zip` | Packaged theme export |
| Course format | `course/format/remuiformat/` | RemUI course format plugin |
| Site config | `config.php` | DB password redacted; copy from `config-dist.php` for new installs |

**Not included:** `moodledata` (`/Users/mazon/moodledataifscmooc`, ~38 MB) — runtime uploads/cache; stays on the Mac.
