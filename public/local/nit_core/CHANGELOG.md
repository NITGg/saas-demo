# Changelog — local_nit_core (NIT Core SDK)

All notable changes to the SDK are recorded here. The public API is **unstable
(v0.x)** until `0.x` graduates to `1.0.0`; until then it may change between
milestones without a deprecation window (Reference Architecture §17, §2.6).

## [0.2.0] — Unreleased (Phase 4, M4 — Rendering Layer)

### Added
- `output\view_model` base (`@api`) — the "what to show" half of the rendering
  seam (renderable + templatable + `template_name()`).
- Reference `output\welcome_panel` view-model + SDK default template
  `templates/output/welcome_panel.mustache` (themes override it).
- Output hook callback (`hook\output_callbacks`) injecting the panel on the
  dashboard, gated by the `showwelcomepanel` setting (off by default); `db/hooks.php`.
- First plugin setting (`settings.php`): `showwelcomepanel`.

### Notes
- The theme (`theme_nit`) now depends on `local_nit_core` (M4 convergence) and
  renders view-models via a thin `core_renderer` extending Boost's renderer.

## [0.1.0] — Unreleased (Phase 1, M0 + M1)

### Added
- SDK skeleton: DI seam (`service_manager` + `api\services`), typed config
  manager, MUC-backed cache manager, event dispatcher.
- Entity base (`base\entity`) on `\core\persistent` with uniform audit-event
  emission via `traits\audits_changes`; generic audit events
  (`entity_created|updated|deleted`).
- Base classes: `base\repository`, `base\service`, `base\scheduled_task`,
  `base\adhoc_task`.
- Contracts (`contract\*`), formatter helper, `nit_exception`, Privacy
  null-provider, `db/caches.php`.
- Unit + integration tests; M0 CI, quality configs, ADR-0001, bundle schema.

### Implementation notes — refinements vs the approved Phase 1 spec

These are deliberate, low-risk corrections found during implementation and are
logged here per our ADR-driven process. None change architecture; all reduce
code or track the real Moodle 5.2 API.

1. **`has_timestamps` trait dropped.** `\core\persistent` already defines and
   populates `timecreated`/`timemodified`/`usermodified`. Re-adding them would be
   forwarding-only. The entity base's value is audit-event emission, not columns.
2. **Config manager does not cache.** `get_config()` is already cached by core
   per request; a second MUC layer would be forwarding-only. The manager adds
   typing + component scoping only.
3. **No `db/install.xml` production table.** M1 ships zero business tables. The
   entity base is exercised by a fixture table created at test runtime.
4. **Namespace `traits\` (not `trait\`).** `trait` is a reserved word and is
   unsafe as a namespace segment.
5. **No `db/hooks.php` yet.** M1 has no hooks to register; the DI seam is the NIT
   `service_manager` (ADR-0001). The `\core\hook\di_configuration` route is
   documented for a possible future migration.
6. **Cache API uses `core_cache\*`.** Moodle 5.x moved MUC to the `core_cache`
   namespace; we use `\core_cache\cache` / `\core_cache\store`, not the legacy
   `\cache` aliases.

### Environment
- Built and targeted against Moodle 5.2 (branch 502); CI matrix also covers
  4.5 LTS and 5.0 per the support policy.
