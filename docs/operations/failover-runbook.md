# Failover Runbook — EC2 Secondary (Manual Drill)

## What this is (and isn't)

This is a manual active-passive failover **drill**, built as a DevOps training exercise — not automated high availability. There is no health monitoring that triggers a failover automatically; you decide when to flip, and you flip it yourself.

The secondary (AWS EC2) runs its own `backend` container but shares the **same Postgres** as the primary (Mikrus), reached privately over a Tailscale link (see [infrastructure.md](infrastructure.md)). Failing over protects against the primary's **backend process or host** being unavailable. It does **not** protect against:

- **Database loss.** Postgres is not replicated. If the primary's disk or DB dies, the secondary loses access too — there is nothing to fail over to for the data layer.
- **Media uploads.** The secondary's `uploads` Docker volume is its own, separate, empty volume — it is not synced from the primary. After failing over, previously uploaded media (images, etc.) referenced by existing content will 404 on the secondary. This is a known, accepted gap for this exercise (fixing it would mean shared object storage, e.g. S3 — out of scope for a training drill).

## Fail over

1. **Verify the secondary is actually healthy** before sending traffic to it:
   ```bash
   curl -f https://52-57-29-121.sslip.io/admin/
   curl -f https://52-57-29-121.sslip.io/<some-known-page>.json
   ```
   Both should return real content, not a timeout or 404. Use the hostname, not the raw IP — that's the TLS cert's subject (see [infrastructure.md](infrastructure.md#ec2-secondary-manual-failover-drill)); hitting the bare IP over HTTPS fails certificate validation.
2. **Point the frontend at it** — Vercel's `SULU_BASE_URL` is the single place that decides which backend the whole system talks to:
   ```bash
   vercel env rm SULU_BASE_URL production
   vercel env add SULU_BASE_URL production
   # value: https://52-57-29-121.sslip.io
   ```
   (Or via the Vercel dashboard: Project → Settings → Environment Variables.)
3. **Redeploy** so the new env var actually takes effect:
   ```bash
   vercel deploy --prod
   ```
   (Or trigger a redeploy from the Vercel dashboard.)
4. **Spot-check the live site** — load a few real pages, confirm content renders, note any missing media (see limitation above).

## Fail back

Same steps, in reverse, once the primary (Mikrus) is confirmed healthy again:

1. `curl -f https://patrykapi.tojest.dev/admin/` (and a known `.json` page) to confirm the primary is actually back.
2. Set `SULU_BASE_URL` back to `https://patrykapi.tojest.dev`.
3. `vercel deploy --prod` again.
4. Spot-check the live site.

## Why there's no DNS-based failover

Mikrus's `tojest.dev` subdomains are Mikrus-managed DNS records — there's no owned zone to repoint at EC2 with a Route53/Cloudflare failover policy. Flipping `SULU_BASE_URL` is the equivalent lever available here, since Vercel is the only consumer of the backend.

## Bootstrap and provisioning

See [ADR-0016](../architecture/adrs/0016-ec2-secondary-failover-drill.md) for how the secondary was stood up (Terraform, Tailscale, Ansible `--limit secondary`) and why. If the EC2 instance is ever replaced (e.g. a future `terraform apply` picks up a newer AMI), the Elastic IP re-associates automatically but Tailscale needs to rejoin — re-run `ansible-playbook playbooks/provision.yml --limit secondary` (or trigger `cd-infra.yml` with `target: secondary`) before expecting the secondary to work again.
