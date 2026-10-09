# Infinity Blog

Infinity Blog is a PHP and MySQL content management system for authors, administrators, and public readers.

## Current status

The repository contains the database foundation, authentication, admin-only category management, author-owned post lifecycle, public published-post reads, admin post management, author-account administration, safe admin audit-log reads, and authorized AI article summaries through the initial OpenAI Responses API adapter. Local SQLite/unit tests cover current routes; Oracle MySQL integration remains unverified until the project is tested against MySQL 8.0.16 or newer.

## Start here

- [Project index](docs/PROJECT_INDEX.md): repository map, reading order, implementation status, and open verification items.
- [Setup and operations guide](docs/README.md): configuration, migrations, administrator provisioning, and tests.
- [Test plan](docs/TEST_PLAN.md): isolated checks and the safe MySQL integration procedure.

Run the available unit and safety checks from PowerShell:

```powershell
& 'C:\xampp\php\php.exe' tests/run.php
```

MySQL integration requires a separate empty database whose name ends in `_test`; see the test plan before enabling it.
