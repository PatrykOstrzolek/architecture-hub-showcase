# ADR-0018: Drop Base Server Provisioning from This Repo's Ansible

- **Status**: Accepted (the `deploy` user this ADR describes was renamed to `ahdeploy`
  and had its sudo scope narrowed by [ADR-0019](0019-narrowly-scoped-deploy-user.md);
  everything else here still holds)
- **Date**: 2026-08-05
- **Deciders**: Patryk O

## Context

`ansible/playbooks/provision.yml` (roles `common`, `docker`, `nginx`) installed base OS
packages, Docker CE, UFW, fail2ban, created the `deploy` user, and installed the nginx
package. It was wired into CI as a manually-triggered `cd-infra.yml` workflow
(`workflow_dispatch` only — see [ADR-0006](0006-ci-cd-and-ansible-deployment.md) and
[ADR-0013](0013-draft-mode-preview.md) for the history of that gap).

In practice, the target VPS is provisioned once and never rebuilt from scratch in the
normal course of this project — re-running `provision.yml` was, in over a year of this
repo existing, never actually exercised against a fresh host. The roles doing base OS
setup added real surface area (a manual workflow, three roles, two extra Ansible
collections, sudoers/UFW/SSH-key logic) for a one-time action. This is a showcase
project with no uptime requirement — the cost of that surface area wasn't paying for
itself relative to the risk of it drifting silently unused.

Base server bootstrap (a fresh VPS: OS packages, Docker, UFW, fail2ban, and installing
the nginx binary) is being extracted to a separate repo,
[`mikrus-ansible`](https://github.com/PatrykOstrzolek/mikrus-ansible), dedicated to
server bootstrapping and decoupled from this application's release cycle. The VPS also
hosts an unrelated second service (a Joomla sandbox, `rediscovering-joomla`) — that
project already established the pattern this ADR follows: it never touches the
pre-existing `deploy` user from this repo, instead creating its own dedicated,
narrowly-scoped user (`rjdeploy`) via a local-only bootstrap playbook that's never wired
into CI. Per-service user creation is service-specific by nature — a shared bootstrap
repo has no business deciding another service's username or sudo scope — so it belongs
with each service, not in the shared host-provisioning repo.

## Decision

Remove `ansible/roles/common/`, `ansible/roles/docker/`, `ansible/playbooks/provision.yml`,
and `.github/workflows/cd-infra.yml` from this repo. This repo's `ansible/` now assumes
the target host already has Docker, nginx, UFW, and fail2ban in place (provisioned by
`mikrus-ansible`) — but *not* the `deploy` user, which this repo still owns.

The `nginx` role stays, but trimmed to only what's specific to this application: the
`app.conf.j2` vhost template and enabling the site. Installing the nginx package and
starting the service move to `mikrus-ansible`. `deploy.yml` now runs both `app` and
`nginx` on every deploy (order: `app` first, since `nginx`'s vhost-staging task writes
into `app_dir`, which `app`'s first task creates — see ADR-0019) so the vhost is
re-templated every time (the gap ADR-0013 fixed — "vhost changes require someone to
remember to run a separate playbook" — stays fixed, just without a whole second CI
workflow to remember to trigger).

A new `bootstrap_user` role + `ansible/playbooks/bootstrap.yml` creates the `deploy`
user (sudoers, docker-group membership, trusting an SSH key) — moved out of the removed
`common` role, not deleted. Unlike `deploy.yml`, `bootstrap.yml` is **never run from
CI** — it's a rare, local-only, by-hand step (mirroring `rediscovering-joomla`'s
`bootstrap.yml`), since it needs to connect as `root` before the `deploy` user exists,
which isn't something to trust to an automated pipeline.

`requirements.yml` keeps `ansible.posix` (now used by `bootstrap_user`'s
`authorized_key` task) but drops `community.general` — the only thing that used it,
UFW configuration, moved to `mikrus-ansible`.

## Alternatives Considered

**Keep `provision.yml` in this repo but mark it unused** — rejected. Code that's never
exercised isn't safer for staying in-tree; it just goes stale silently and someone
eventually trusts it by accident.

**Fold provisioning into `deploy.yml`, run on every deploy** — rejected. Base OS
provisioning tasks (apt installs, UFW rules) are almost entirely no-ops once applied,
but they're still extra API calls and privilege-escalated tasks on every release for no
benefit on an already-provisioned host.

## Consequences

### Positive

- Fewer moving parts in this repo's CI/CD: one less workflow, one less playbook, two
  fewer roles, one fewer Galaxy collection to install on every run (`community.general`;
  `ansible.posix` stays, now used by `bootstrap_user` instead of the removed `common`)
- `deploy.yml` is now the single playbook that matters day-to-day, and it always
  applies the current nginx vhost — no separate step to remember

- Consistent with the sibling `rediscovering-joomla` service already on the same VPS:
  each service owns its own deploy user and vhost; only genuinely host-wide setup is
  centralized in `mikrus-ansible`

### Negative

- This repo can no longer stand up a fresh VPS end-to-end by itself; base provisioning
  now lives in a separate repo/process not tracked here
- If `mikrus-ansible` and this repo's assumptions about the host (Docker version, nginx
  presence) drift apart, nothing in CI catches it until a deploy fails
- `bootstrap.yml` being local-only means there's no CI record of when/whether it last
  ran — recovering a lost `deploy` user relies on someone remembering to re-run it by
  hand