# Agent Instructions

## Project Memory & Documentation
- Read `.codex/PROJECT_MEMORY.md` and relevant files in `.codex/docs/<tab_name>/` at the start of each session/task to understand the codebase structure and business logic before analysis, planning, or code changes.
- Before implementing or changing a report, read `.codex/docs/reports/report_creation_rules.md`; use it as the report workflow, then inspect only the assigned report's legacy evidence and the closest implementation example.
- Maintain tab-specific documentation in `.codex/docs/<tab_name>/` for each tab/feature worked on, detailing API links, components, and business constraints. Update whenever changes occur and notify of any constraint/link changes.
- When a feature intentionally keeps hard-coded data, document it under `.codex/docs/hardcoded/` with the file/location, hard-coded values, temporary reason, and condition for replacing it with DB/API data.
- Read `.codex/docs/database_mapping/README.md` before changing database tables, models, migrations, seeders, foreign keys, or legacy import logic. Update `.codex/docs/database_mapping/TABLE_MAP.md` and the corresponding `.codex/docs/database_mapping/tables/<table>.md` in the same change. Record only verified legacy-to-new mappings; mark unresolved mappings as `Chưa xác minh` and never infer import keys.
- Update `.codex/PROJECT_MEMORY.md`'s **Current Status**, **Recent Changes**, and **Known Risks** after material work.
- Record only verified facts; never add secrets, tokens, credentials, or guest data.

## Collaboration Rules
- Before editing a shared file, ask for approval and state the expected impact on other files and the current feature; explicitly state when no impact is expected.
- Treat the project as a clone of the legacy PMS: preserve legacy feature behaviour, UX/UI interactions and business logic unless a change is approved.
- Preserve import compatibility from the legacy system; document data-mapping changes before modifying models, migrations or import code.
- Responses: concise bullets only; explain only when asked.

## Package Manager
- Frontend: npm in `frontend/` (`npm run dev`, `npm run build`).
- Backend: Composer and npm in `backend/` (`composer test`, `npm run dev`).

## Verification
| Scope | Command |
|---|---|
| Laravel feature test | `cd backend; php artisan test --filter=<TestName>` |
| Laravel routes | `cd backend; php artisan route:list --path=api` |
| Frontend build | `cd frontend; npm run build` |

## Key Conventions
- Frontend requests go through `frontend/src/services/http.js` and use `/api`.
- Define Laravel API endpoints in `backend/routes/api.php`; require Sanctum unless intentionally public.
- Treat `old_database_struct/` as the legacy database structure reference for mapping, field definitions, and legacy behavior comparison (not the runtime schema).
- Preserve Vietnamese business terminology in UI and business-rule documentation.

## Commit Attribution
AI commits MUST include a `Co-Authored-By` trailer using the acting agent's identity.
