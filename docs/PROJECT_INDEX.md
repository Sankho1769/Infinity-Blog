# Infinity Blog Project Index

Infinity Blog is a PHP and MySQL blog content management system. Authors manage their own articles, administrators manage system-wide content and categories, and public visitors can read published articles.

## Current implementation and verification

The repository contains the Phase 1 configuration, PDO, and versioned migration foundation; authentication and authorization; category management; author-owned post lifecycle; public published-post reads with search and pagination; author-scoped and system-wide database-derived post counts; admin post management; and admin author-account creation, updates, activation, and deactivation. Audit queries, AI provider integration, and frontend pages remain unimplemented. Oracle MySQL integration remains unverified.

The latest local checks report 26 tests passed and 0 failed, PHP lint passed for 43 files including the root autoloader, and `git diff --check` passed. Oracle MySQL integration is still unverified. The available XAMPP database binary is MariaDB 10.4.32, local ports 3306 and 33060 did not accept connections, and the MySQL test environment was not configured. Category, author/admin post, public-post, and admin author-management behavior has SQLite coverage; the Oracle MySQL integration gate remains open.

## Recommended reading order

1. [Detailed setup guide](README.md) for local configuration, migrations, secure administrator provisioning, and test setup.
2. [Formal requirements](SRS.md) and [product requirements](PRD.md) for agreed scope and acceptance criteria.
3. [Architecture](architecture.md) and [functional design](design.md) for component boundaries and user flows.
4. [API contract](API_SPEC.md) and [database design](DATABASE_DESIGN.md) for backend and persistence interfaces.
5. [Security threat model](SECURITY_THREAT_MODEL.md), [test plan](TEST_PLAN.md), and [deployment runbook](DEPLOYMENT_RUNBOOK.md) before implementation or deployment work.
6. [Task list](task.md), [wireframe mapping](WIREFRAME_BACKEND_MAPPING.md), and the [wireframe image](wireframes/infinity-blog-flow.jpeg) for implementation status and page-to-backend mapping.

## Project documents

| Document | Purpose |
|---|---|
| [README.md](README.md) | Detailed setup and operational instructions retained from the original project README. |
| [SRS.md](SRS.md) | Canonical Markdown software requirements specification. Its section 1.2 reproduces the official problem statement used by the project. |
| [SRS_Infinity_Blog.docx](SRS_Infinity_Blog.docx) | Editable Word delivery of the updated Infinity Blog SRS. |
| [SRS_Infinity_Blog.pdf](SRS_Infinity_Blog.pdf) | Formatted PDF delivery of the updated Infinity Blog SRS. |
| [SRS_CMS_Baseline.pdf](SRS_CMS_Baseline.pdf) | Older, generic CMS SRS retained for history; it is not identified as the original assignment PDF. |
| [PRD.md](PRD.md) | Product goals, users, scope, release criteria, and product decisions. |
| [architecture.md](architecture.md) | Application modules, responsibilities, data flow, and deployment shape. |
| [API_SPEC.md](API_SPEC.md) | Implemented authentication routes and planned API contracts. |
| [DATABASE_DESIGN.md](DATABASE_DESIGN.md) | Logical schema, constraints, indexes, and data lifecycle. |
| [rules.md](rules.md) | Coding, security, Git, data, and implementation rules. |
| [design.md](design.md) | Functional page structure and interactions; visual design is out of scope. |
| [memory.md](memory.md) | Stable project context and decisions for future work. |
| [task.md](task.md) | Phased implementation checklist and current progress. |
| [TEST_PLAN.md](TEST_PLAN.md) | Unit, integration, functional, authorization, and security checks. |
| [SECURITY_THREAT_MODEL.md](SECURITY_THREAT_MODEL.md) | Threats, mitigations, and known security limits. |
| [DEPLOYMENT_RUNBOOK.md](DEPLOYMENT_RUNBOOK.md) | Local setup, database TLS, release, and operations procedures. |
| [WIREFRAME_BACKEND_MAPPING.md](WIREFRAME_BACKEND_MAPPING.md) | Wireframe page/data/action mapping and implementation status. |

There is no separate `FRONTEND_BACKEND_INTEGRATION_GUIDE.md`; use `API_SPEC.md` with the wireframe mapping. There are no standalone decision records, so no `decisions/` directory was created.

## Source problem statement

No standalone original assignment PDF was found in the repository. The file previously named `SRS Document.pdf` is an 8-page software requirements specification for a generic CMS, not a clearly identified source assignment, so it was retained as `SRS_CMS_Baseline.pdf` rather than relabeled as the official problem statement. The project statement is reproduced in [SRS.md, section 1.2](SRS.md#12-official-problem-statement). If the original assignment PDF becomes available, place it here as `SOURCE_PROBLEM_STATEMENT.pdf` after confirming its identity.

## Files kept outside `docs/`

Application code remains under `app/`; CLI tools remain under `bin/`; migrations remain under `database/migrations/`; public entry points/assets remain under `public/`; tests remain under `tests/`. `.env.example` and `.gitignore` stay at the repository root. The root `autoload.php` is executable application bootstrap code required by the test runner, CLI tools, and web entry point.
