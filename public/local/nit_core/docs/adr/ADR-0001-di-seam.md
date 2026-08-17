# ADR-0001 — The dependency-injection seam

- **Status:** Accepted
- **Date:** 2026-08-04
- **Milestone:** M1 (`local_nit_core`)
- **Governs:** Reference Architecture v1.0 §6 (Core SDK), Phase 1 Spec §2.4

## Context

Every NIT plugin resolves shared SDK services (config, cache, events). Moodle 5.x
ships `\core\di` (PHP-DI, autowiring) and allows plugins to register container
definitions via the `\core\hook\di_configuration` hook. We need a resolution
seam that (a) is stable for plugins to depend on, and (b) supports swapping any
service for a double in unit tests.

Two options were considered:

- **A — Register NIT services directly in `\core\di`** via the `di_configuration`
  hook. One container; native `\core\di::set()` test overrides.
- **B — A thin NIT `service_manager` locator** that owns NIT service construction
  and delegates to `\core\di` for core services.

## Decision

Adopt **Option B** for SDK v0.x.

`\local_nit_core\service_manager` is the internal seam; `\local_nit_core\api\services`
is the stable public facade. Services are keyed by their **interface name**, built
lazily, cached as singletons, and replaceable via `override()` in tests.

## Rationale

- Insulates plugins from any future churn in core's plugin-DI registration
  ergonomics — the public id is a NIT interface, not a core mechanism.
- Test override is a first-class, explicit operation (`services::override()`),
  independent of PHP-DI's non-PSR `set()`.
- The spike at the start of M1 confirmed `di_configuration` works and could back
  Option A later; nothing here blocks migrating.

## Consequences

- One small locator to maintain (`service_manager`).
- Core services still come from `\core\di` when needed inside a NIT service.
- **Revisit trigger:** if core's plugin-DI registration stabilises and offers no
  downside for testing, a follow-up ADR may collapse Option B into `\core\di`
  directly. Because plugins depend only on `api\services`, that migration would
  not change any consumer.
