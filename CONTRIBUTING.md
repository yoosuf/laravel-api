# Contributing

Thanks for improving yoosuf/laravel-api.

For full contribution workflow, coding standards, testing expectations, and
release checklist, see:

- docs/CONTRIBUTING_GUIDELINES.md

## Quick Start

1. Fork and clone the repository.
2. Create a focused branch for your change.
3. Install dependencies:
   - composer install
4. Run the quality gate before opening a PR:
   - composer release:check
5. Update relevant docs (for user-visible behavior changes):
   - CHANGELOG.md
   - UPGRADE.md
   - docs/CONFIG_REFERENCE.md

## Pull Request Expectations

- Keep PRs focused and minimal.
- Add or update tests for behavior changes.
- Explain risk and migration impact clearly.
- Ensure CI is green.
