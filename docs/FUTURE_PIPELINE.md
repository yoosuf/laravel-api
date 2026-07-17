# Future Pipeline

## Objective

Define a practical engineering and release pipeline for the next package maturity stages.

## Pipeline stages

### Stage 1: Current baseline

Status: completed

- Unit and integration tests.
- Static analysis and style checks.
- CI workflow for package quality.
- Release checklist and SemVer policy.

### Stage 2: Hardening

Target: next minor release

- Add mutation testing for mapper and generator logic.
- Add compatibility matrix jobs for Laravel 10, 11, 12 and PHP 8.1, 8.2, 8.3.
- Add docs route snapshot tests.
- Add security audit job and dependency update bot policy.

### Stage 3: Release automation

Target: next two minors

- Add tag-triggered release workflow.
- Auto-generate changelog draft from merged PR labels.
- Publish release artifacts and checksums.
- Optional Packagist auto-update webhook verification step.

### Stage 4: Performance and reliability

Target: medium term

- Benchmark generation speed against route-count buckets.
- Add performance regression threshold gates in CI.
- Add deterministic output ordering tests.
- Add fault-injection tests for malformed provider output.

### Stage 5: Ecosystem and developer experience

Target: long term

- Provide schema DSL helpers for common Laravel patterns.
- Add optional plugin modules for auth schemes and pagination standards.
- Add docs preview deployment on pull requests.
- Add example app templates for common API styles.

## Proposed CI job matrix

- quality:
  - php 8.1 + laravel 10
  - php 8.2 + laravel 11
  - php 8.3 + laravel 12
- security:
  - composer audit and dependency policy checks
- release:
  - tag validation, release note generation, publish hooks

## Delivery governance

- Every feature PR must include tests and docs updates.
- Breaking changes require SemVer major planning record.
- Release candidate must pass release:check and matrix CI.

## KPI suggestions

- Time to merge for non-breaking features.
- Regression escape rate from release branches.
- Mean OpenAPI generation time by route bucket.
- Documentation freshness score per release.
