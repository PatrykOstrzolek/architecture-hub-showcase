# ADR-0016: EC2 Secondary Backend as a Manual Failover Drill

- **Status**: Accepted
- **Date**: 2026-07-06
- **Deciders**: Patryk O

## Context

The backend is a single point of failure: one Mikrus VPS runs Sulu and Postgres. This is acceptable for a non-critical showcase project with no uptime SLA, but a second, differently-hosted instance is valuable as a DevOps training exercise — provisioning infrastructure with Terraform, private cross-cloud networking, and practicing a failover.

Two constraints shaped the approach:

- **No owned DNS zone.** The Mikrus subdomains (`patrykarc.tojest.dev`, `patrykapi.tojest.dev`) are Mikrus-managed — there's no zone to repoint at a second host with Route53/Cloudflare failover routing.
- **Training scope, not production hardening.** This project explicitly rejected Docker Swarm/Kubernetes as over-engineered for a single-server showcase ([ADR-0006](0006-ci-cd-and-ansible-deployment.md)); the same reasoning applies here — the goal is to practice specific skills (IaC provisioning, private networking, a failover runbook), not to build real high availability.

## Decision

Stand up a second backend instance on **AWS EC2** (Free Tier `t3.micro` — confirmed via `aws ec2 describe-instance-types --filters Name=free-tier-eligible,Values=true` that `t2.micro` isn't eligible on this account's 2025+ credit-based Free Tier), provisioned with **Terraform** (`terraform/`, local state). Link it to the Mikrus primary over a **Tailscale** private mesh so the secondary's `backend` container can reach the primary's Postgres without exposing it publicly — Postgres's published port is bound to the primary's Tailscale IP specifically, not `0.0.0.0` (Docker's port publishing bypasses UFW's chains, so the bind address, not a firewall rule, is the actual control).

Postgres is **not replicated** — both instances share the one primary database. This is a deliberate, accepted limitation, not an oversight.

Failover is a **manual drill**: since there's no owned DNS zone, the equivalent lever is flipping Vercel's `SULU_BASE_URL` environment variable (the frontend's single point of backend configuration) and redeploying. See [failover-runbook.md](../../operations/failover-runbook.md).

Both instances deploy via the existing `cd-backend.yml` pipeline, sequentially (primary's migration must land before the secondary redeploys against the shared DB — enforced via Ansible's `is_primary` gate and GitHub Actions job dependencies).

## Alternatives Considered

**Route53/Cloudflare DNS failover** — rejected: no owned domain to manage failover routing on.

**Streaming Postgres replication (read replica on EC2, promoted on failover)** — rejected as a much larger exercise (replication lag, promotion logic, split-brain handling) than this training scope calls for. The shared, unreplicated DB remains a single point of failure for data, which is explicitly documented rather than solved.

**Kubernetes / Docker Swarm** — rejected for the same reason as ADR-0006: two fixed nodes with a manual failover drill doesn't exercise what an orchestrator is actually for (dynamic scheduling, self-healing across a fleet), and would replace this exercise's Terraform/Ansible/Tailscale skills with a much larger, differently-scoped one.

**Remote Terraform state (S3 + DynamoDB)** — rejected for now; local gitignored state is sufficient for a single operator managing one instance. Noted as a possible future exercise, not a current gap.

**SSH-over-Tailscale for CI (`tailscale/github-action`)** — considered, deferred rather than rejected. The GitHub Actions runner could join the tailnet as a temporary ephemeral node and SSH to both hosts over their Tailscale IPs instead of public SSH, letting the EC2 security group drop its public port-22 rule entirely. Worthwhile hardening, but solves a different problem than this ADR's cost/HA scope (SSH exposure, not the public IPv4 charge — the Elastic IP is still required regardless, since it serves real HTTP traffic from Vercel, which isn't a tailnet member). Deferred to keep this exercise's already-applied Terraform/CI changes from growing further; a natural next step if this project continues.

## Consequences

### Positive

- Practices real, transferable DevOps skills: Terraform-provisioned cloud compute, private cross-cloud networking (Tailscale), Ansible multi-host inventory patterns, and a documented failover runbook.
- The primary's behavior and existing single-instance deployment path are unchanged — the secondary is purely additive.
- Elastic IP keeps the Ansible inventory target stable even if the instance is replaced by a future `terraform apply`.

### Negative

- Postgres remains a single point of failure — this drill only protects against the primary's backend process/host being unavailable, not database loss.
- Media uploads are not synced to the secondary (separate, empty `uploads` volume) — content referencing uploaded media will have broken images after a real failover.
- Every push to `main` now deploys to two hosts sequentially instead of one, increasing CD surface and total pipeline time.
- A real, unavoidable AWS cost applies regardless of Free Tier status: public IPv4 addresses have been billed hourly (~$3.60/month) since February 2024.
