# cursor.ai — PDI Superior

Moodle installation for **PDI Superior**, imported from the local `moodle-pdi` tree on Daniel's Mac.

## Source

`moodle-pdi` is **not** on GitHub. It lives at:

- `~/Sites/moodle-pdi` (`/Users/mazon/Sites/moodle-pdi`)
- Cursor self-hosted worker: `~/Sites/moodle-pdi @ MacBook Air de Daniel` (`f1fefa9c-22d0-5be4-951a-c3fcccc2e075`)

Same pattern as the IFSC Mooc import (`~/Sites/moodle-ifsc-mooc` → PR #2).

## Import

Re-run **on the Mac** (cloud agents cannot read that filesystem):

```bash
./scripts/import-moodle-pdi.sh
```

The script rsyncs the Moodle tree onto this branch, redacts `$CFG->dbpass` in `config.php`, and pushes.

## Contents

Will be filled in after the Mac-side copy lands (Moodle core, custom plugins/themes, `config.php`). Runtime `moodledata` stays on the Mac when it lives outside the code tree.
