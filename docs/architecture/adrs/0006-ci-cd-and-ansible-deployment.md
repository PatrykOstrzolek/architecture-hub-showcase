# ADR-0006: CI/CD Pipeline with GitHub Actions and Ansible

- **Status**: Accepted (frontend deployment section superseded by [ADR 0009](0009-vercel-frontend-deployment.md))
- **Date**: 2026-06-28
- **Deciders**: Patryk O

## Context

The project needed automated quality gates and a repeatable deployment process. The requirements were:

- All code must pass static analysis, formatting checks, and tests before merging
- Production deployments must be automatic after CI passes on `main`
- Server configuration must be version-controlled (Infrastructure as Code)
- Secrets must never appear in version control

## Decision

**GitHub Actions** for CI/CD orchestration. **Ansible** for server provisioning and application deployment.

Five workflow files, split by concern:

- `ci-backend.yml` — PHP quality gate (PHPStan, CS Fixer, PHPUnit, `composer audit`)
- `ci-frontend.yml` — JS quality gate (typecheck, ESLint, Prettier, `next build`, `npm audit`)
- `ci-security.yml` — security gate (Trivy filesystem CVE scan, Semgrep SAST); runs independently on every push
- `cd-backend.yml` — triggered by `workflow_run` after `ci-backend` passes; builds Docker image, pushes to GHCR, deploys via Ansible
- `cd-frontend.yml` — triggered by `workflow_run` after `ci-frontend` passes; deploys to Vercel via `vercel deploy --prod`

Frontend and backend pipelines are fully independent — a failing backend never blocks a frontend deployment.

Ansible is run directly from the GitHub Actions runner (not a separate Ansible Tower/AWX instance), which is sufficient for a single-server setup.

## Alternatives Considered

**Docker Swarm / Kubernetes** — rejected as over-engineered for a single-server showcase. Adds significant operational complexity without benefit at this scale.

**Ansible Tower / AWX** — rejected for the same reason. Self-hosted Ansible from the CI runner is simpler and has no additional infrastructure cost.

**Server-side git pull + build** — rejected because it mixes build concerns into the production server, creates a dependency on build tooling being present on the server, and makes rollbacks harder.

## Consequences

### Positive

- Push to `main` is the only action required to release
- Server state is fully described in `ansible/` and reproducible on any fresh VPS
- Secrets are encrypted at rest (Ansible Vault) and in transit (GitHub Secrets)
- Docker images are immutable artifacts tagged by git SHA — rollback is `docker compose pull` with a previous tag
- CI checks run in parallel (backend, frontend, and security workflows are independent), keeping feedback fast

### Negative

- GitHub Actions runner must have SSH access to the production server — requires firewall rule allowing GitHub's IP ranges on port 22
- `community.docker` Ansible collection must be installed on the runner at deploy time (added as a step in `cd.yml`)
- Single-server topology has no high availability; acceptable for a showcase project
