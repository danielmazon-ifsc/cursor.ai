# cursor.ai — PDI Superior

Moodle installation for **PDI Superior**, imported from the local `moodle-pdi` tree on Daniel's Mac.

## Source

`moodle-pdi` is **not** on GitHub. It lives at:

- `~/Sites/moodle-pdi` (`/Users/mazon/Sites/moodle-pdi`)
- Cursor self-hosted worker: `~/Sites/moodle-pdi @ MacBook Air de Daniel` (`c699561c-559b-44ab-8feb-4f56589150e8`)

Same pattern as the IFSC Mooc import (`~/Sites/moodle-ifsc-mooc` → PR #2).

## Import

Re-run **on the Mac** (cloud agents cannot read that filesystem):

```bash
./scripts/import-moodle-pdi.sh
```

The script rsyncs the Moodle tree onto this branch, redacts `$CFG->dbpass` in `config.php`, and pushes.

## Contents (imported 2026-09-24)

| Item | Path | Notes |
|------|------|-------|
| Moodle core | repo root | ~347 MB install tree (Moodle **4.1.22**, Build: 20251208) |
| Custom theme | `theme/pdi/` | PDI child theme (Boost-based) |
| Course formats | `course/format/specialization/`, `saladeconferencias/`, `conferenceroom/`, `videogallery/` | Custom PDI formats |
| Activity modules | `mod/video/`, `mod/cquiz/`, `mod/socialforum/` | Custom |
| Local plugins | `local/profile/`, `local/dashboard/`, `local/studypace/`, `local/coin/`, `local/notifications/`, `local/autobadge/`, `local/salagrade/` | Custom |
| Block | `blocks/onboarding/` | Custom |
| Site config | `config.php` | `$CFG->dbpass` redacted; copy from `config-dist.php` for new installs |

**Not included:** runtime `moodledata` (`/Users/mazon/moodledatapdi`, ~45 MB) and PHPUnit dataroot (`/Users/mazon/moodledatapdi-phpunit`) — stays on the Mac.
