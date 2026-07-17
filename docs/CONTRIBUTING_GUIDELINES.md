# Contributing Guidelines

## Scope

These guidelines apply to contributions inside packages/yoosuf/laravel-api.

## Contribution flow

1. Create a focused branch.
2. Implement change with tests.
3. Update docs when behavior changes.
4. Run quality gate locally:
   - composer release:check
5. Open PR with clear summary and risk notes.

## Code standards

- Follow Laravel Pint formatting.
- Keep generator behavior deterministic.
- Prefer explicit config overrides for edge cases.
- Keep public API changes documented in SemVer policy.

## Testing expectations

- Add unit tests for pure logic changes.
- Add integration tests for command and route-level behavior.
- Update fixtures when expanding inference scenarios.

## Documentation expectations

Update at least one of the following when relevant:

- CHANGELOG.md
- UPGRADE.md
- docs/CONFIG_REFERENCE.md
- docs/ARCHITECTURE.md

## Pull request checklist

- Tests added/updated.
- Static analysis and lint pass.
- Changelog entry added if user-visible.
- Upgrade note added for compatibility-impacting changes.
