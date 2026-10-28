# Harbor AI integrated source

Laravel 12 and React/TypeScript application for agricultural trading, Producer registration/review, institutional Buyer demand review, listings, negotiation/order tracking, restricted KADIWA coordination, notifications, scoped dashboard themes and backup/recovery preparation.

This source publication is not a deployment or production-readiness certification. Real isolated cross-role browser acceptance, pinned MariaDB race validation, complete accessibility acceptance and operational recovery approval remain outstanding. Preserve all role, FCA, procurement and payment restrictions.

## Safe installation

Use an independently approved application directory, database, service identity and storage. Install Composer/npm dependencies from the committed lockfiles. Review Composer scripts before executing them: setup/create-project scripts can generate keys and apply migrations. Never run them against an existing protected database.

Copy `.env.example` to a private `.env` and supply independent secrets through an approved process. No active environment, user uploads, database dump, runtime state or installed dependency directory belongs in Git. Build assets with `npm run build`; do not carry a developer `public/hot` file into a release.

Default account creation is intentionally absent from the publication seed entry. Super Admin provisioning requires a separately approved process; there is no bundled administrative password. The optional registry seeder contains synthetic identities only. It is not an official RSBSA registry and must not authorize real registrations.

Review migrations against the intended schema before separately approving execution. The Buyer demand replay-key migration is additive source only; its presence does not authorize migration of development/production. Same Buyer/key/payload replays one persisted demand; changed payload conflicts. Distinct keyless submissions remain distinct. Persistent retry protection requires the reviewed table to be installed.

## Regression commands

Run PHP tests only with the repository's test-target guards, uncached configuration and isolated in-memory SQLite. `phpunit.xml` supplies a public test-only encryption key; never use it for a deployed application.

```text
php vendor/phpunit/phpunit/phpunit
npm run test:frontend
npm run test:contracts
npm run typecheck
npm run build
```

These regression suites are separate from browser-to-server UAT and operational recovery testing. Browser component fixtures intercept API calls and must be described as mocked. The MariaDB concurrency runner has its own strict approved target; do not weaken its identity/grant checks or run it against a convenient development database. Local Phase5 preparation guards likewise require their specifically approved independent environment.

## Backup and recovery

Keep `BACKUP_ENABLED`, `BACKUP_STORAGE_PERMISSIONS_APPROVED` and `BACKUP_RECOVERY_DRILL_ENABLED` false until independently authorized. Before activation, verify service-account filesystem permissions, encrypted working/secondary storage, a dedicated key with independent escrow, compatible absolute dump/client binary paths, scheduling/monitoring and a separately isolated recovery target. Perform an approved integrity/restore drill and measure actual RPO/RTO. A successful test suite is not recovery certification.

Backup dashboard actions remain restricted by the existing Super Admin policy. Do not enable payments, procurement authority, FCA integrations or other intentionally restricted features to make a demo pass. No GitHub deployment workflow is part of this source update.

## Privacy and release review

The publication contains source, synthetic regression fixtures, static website assets, a nonpersonal derived agricultural dataset and a blank report template. Website team attribution/portrait assets are separate from operational user records and require the owner's content review before public publication. Keep machine-specific QA reports, screenshots, session/token logs, confidential evidence and source checkpoints private.

Do not publish or merge automatically. Review the exact staged tree, security findings and test results, then approve a feature-branch commit/push separately. Source publication never authorizes a live application deployment.
