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

Two additional hardening measures came out of `ci-security.yml`'s Semgrep gate flagging real findings on the first PR push, not from upfront design: the EC2 instance enforces IMDSv2 (`metadata_options.http_tokens = "required"` in `terraform/main.tf`, blocking the SSRF-to-credential-theft path IMDSv1 allows), and the secondary's catch-all nginx vhost sets `proxy_set_header Host` to the known instance address rather than passing through the raw, attacker-controllable request Host header — there's no real `server_name` to validate against otherwise. `cd-backend.yml` also gained a Trivy scan of the *built* image itself, gating both deploy jobs; `ci-security.yml`'s existing Trivy step only ever scanned the source filesystem pre-build, which was a real pre-existing gap this work happened to surface.

The secondary later gained real TLS, added once the drill was live and running plaintext HTTP long enough to notice it was avoidable at zero cost. The first attempt used AWS's own free public DNS hostname for the Elastic IP (`ec2-<ip-with-dashes>.eu-central-1.compute.amazonaws.com`, still available as `terraform/outputs.tf: public_dns`) as the cert subject — this failed at actual issuance time, not in planning: Let's Encrypt's CA policy refuses to issue for any `*.amazonaws.com` name outright ("forbidden by policy", confirmed against the live ACME server during the `cd-infra.yml` run against the real secondary). The cert subject is a **sslip.io** hostname instead (`52-57-29-121.sslip.io`, see `ansible/group_vars/secondary/main.yml: ec2_public_hostname`) — free wildcard DNS resolving to the same Elastic IP, not subject to that blocklist. A new `certbot` Ansible role (`ansible/roles/certbot/`, gated `when: not is_primary`) obtains the cert via the HTTP-01 **webroot** challenge — chosen over the nginx plugin or standalone mode specifically because nginx never needs to stop: this instance can be serving live failover-drill traffic at the moment the cert is issued or renewed, and the nginx plugin would otherwise edit the Ansible-templated vhost file out from under Ansible's own idempotency. `app-secondary.conf.j2` resolves the bootstrap ordering problem (nginx must run before a cert can exist, but the 443 block needs the cert's file paths to exist before nginx will start) by checking for the cert file with `stat` and rendering HTTP-only until it's present; the `certbot` role re-renders the same template immediately after issuance so the 443 block appears within the same playbook run, no second `provision.yml` pass required. Renewal is handled by certbot's own `certbot.timer`, using a `--deploy-hook` that reloads nginx (without it, `certbot renew` would replace the cert files on disk but nginx would keep serving the old one from worker memory until manually reloaded). The primary's TLS remains entirely outside this repo's control — Mikrus's platform proxy terminates it in front of nginx, which is why `app.conf.j2` has no `ssl_certificate` directives at all.

## Alternatives Considered

**Route53/Cloudflare DNS failover** — rejected: no owned domain to manage failover routing on.

**Streaming Postgres replication (read replica on EC2, promoted on failover)** — rejected as a much larger exercise (replication lag, promotion logic, split-brain handling) than this training scope calls for. The shared, unreplicated DB remains a single point of failure for data, which is explicitly documented rather than solved.

**Kubernetes / Docker Swarm** — rejected for the same reason as ADR-0006: two fixed nodes with a manual failover drill doesn't exercise what an orchestrator is actually for (dynamic scheduling, self-healing across a fleet), and would replace this exercise's Terraform/Ansible/Tailscale skills with a much larger, differently-scoped one.

**Remote Terraform state (S3 + DynamoDB)** — rejected for now; local gitignored state is sufficient for a single operator managing one instance. Noted as a possible future exercise, not a current gap.

**SSH-over-Tailscale for CI (`tailscale/github-action`)** — considered, deferred rather than rejected. The GitHub Actions runner could join the tailnet as a temporary ephemeral node and SSH to both hosts over their Tailscale IPs instead of public SSH, letting the EC2 security group drop its public port-22 rule entirely. Worthwhile hardening, but solves a different problem than this ADR's cost/HA scope (SSH exposure, not the public IPv4 charge — the Elastic IP is still required regardless, since it serves real HTTP traffic from Vercel, which isn't a tailnet member). Deferred to keep this exercise's already-applied Terraform/CI changes from growing further; a natural next step if this project continues.

**No TLS on the secondary at all** — the original decision, on the reasoning that there was no ownable domain to cert and this was a short-lived drill target. Reversed once real usage showed the drill running long enough that plaintext HTTP was avoidable, not just theoretically fixable: AWS's own free public DNS hostname for the Elastic IP closes the "no domain" gap at zero cost and no new moving parts (no extra DNS provider, no dynamic-DNS account).

**AWS's own auto-generated public DNS hostname** — the original choice for the TLS cert subject, on the reasoning that it avoided depending on a third-party service for something AWS already provides natively and for free. Rejected after actually attempting cert issuance against it: Let's Encrypt's CA policy blocks `*.amazonaws.com` outright, a hard restriction with no workaround. sslip.io was the fallback, despite being a third-party dependency this project wouldn't otherwise need.

**certbot's `--nginx` plugin or `--standalone` mode** — rejected in favor of the webroot method. The nginx plugin edits the vhost file directly, which fights the Ansible-templated `app-secondary.conf.j2` on every subsequent `provision.yml` run; standalone mode needs port 80 free, which conflicts with nginx already running and serving live drill traffic. Webroot needs nginx to keep running throughout and never touches the templated file itself.

## Consequences

### Positive

- Practices real, transferable DevOps skills: Terraform-provisioned cloud compute, private cross-cloud networking (Tailscale), Ansible multi-host inventory patterns, and a documented failover runbook.
- The primary's behavior and existing single-instance deployment path are unchanged — the secondary is purely additive.
- Elastic IP keeps the Ansible inventory target stable even if the instance is replaced by a future `terraform apply`.
- Closes a repo-wide gap unrelated to this ADR's original scope: the backend image itself is now scanned in CI (`cd-backend.yml`), not just its source filesystem — this benefits every future deploy, primary or secondary.
- The secondary now terminates real TLS at zero additional cost, closing the original plaintext-HTTP gap.

### Negative

- Postgres remains a single point of failure — this drill only protects against the primary's backend process/host being unavailable, not database loss.
- Media uploads are not synced to the secondary (separate, empty `uploads` volume) — content referencing uploaded media will have broken images after a real failover.
- Every push to `main` now deploys to two hosts sequentially instead of one, increasing CD surface and total pipeline time.
- A real, unavoidable AWS cost applies regardless of Free Tier status: public IPv4 addresses have been billed hourly (~$3.60/month) since February 2024.
- `ec2_public_hostname` in `ansible/group_vars/secondary/main.yml` is a plain string derived from the Elastic IP, not read live from Terraform state — if the EIP is ever released and a new one allocated, this value (and the existing Let's Encrypt cert, tied to the old hostname) must be updated and re-issued by hand.
- The secondary's TLS now depends on a third-party service (sslip.io) it wouldn't otherwise need, purely because Let's Encrypt blocks the AWS-native alternative — an external dependency accepted only after the preferred option turned out to be a dead end.
