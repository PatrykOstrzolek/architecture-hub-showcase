# ADR-0019: Dedicated, Narrowly-Scoped Deploy User (`ahdeploy`)

- **Status**: Accepted
- **Date**: 2026-08-05
- **Deciders**: Patryk O

## Context

[ADR-0018](0018-drop-server-provisioning-ansible.md) moved per-service user creation
into each service's own repo, but kept the `deploy` user's permission model as-is: full,
passwordless sudo (`ALL=(ALL) NOPASSWD:ALL`). The sibling `rediscovering-joomla` service
on the same VPS uses a different model for its own user (`rjdeploy`): a locked password
(SSH key only), docker-group membership for container operations, and a `sudoers.d`
drop-in scoped to exactly the four commands needed to publish its nginx vhost — nothing
else. If that key ever leaks, the blast radius is "can reload nginx and swap one vhost
file", not "root on the box".

Auditing what this repo's `deploy.yml` (roles `nginx` + `app`) actually needs root for
found the same thing true here: every Docker operation (login, pull, compose up,
migrations, `docker exec` for cache warmup) only needs docker-group membership, not
root — Ansible was defaulting these to root only because the play set `become: true`
globally and `ansible.cfg` set `become = True` by default, not because they required it.
The only genuine root-requiring steps are the same four nginx commands `rjdeploy`
already whitelists: publish the vhost into `sites-available`, symlink it into
`sites-enabled`, validate (`nginx -t`), reload.

Keeping `deploy`'s blanket sudo around meant this repo's actual exposure (an SSH key
used by every CI deploy) was strictly worse than the sibling service's, for no
functional reason — the app doesn't do anything that needs it.

## Decision

Rename the app's deploy user from `deploy` to **`ahdeploy`**, freeing `deploy` to mean
"the shared, full-sudo operator account managed by `mikrus-ansible`" (used for
re-provisioning the host — see that repo's README) rather than "this app's user".
`ahdeploy` gets:

- docker-group membership (covers every Docker operation this repo performs)
- a locked password (`password: "!"`) — SSH key only, no password login
- a `sudoers.d` drop-in scoped to exactly the four nginx commands above, via
  `roles/bootstrap_user` (mirroring `rediscovering-joomla`'s `bootstrap_user` role)
- its own home directory (`/home/ahdeploy/architecture-hub-showcase`) as `app_dir`,
  instead of `/opt/architecture-hub` — a directory the user already owns by virtue of
  `create_home: true` needs no root to create or write to, eliminating another
  root-requiring step

Ansible modules run under `become` don't match a static `sudoers.d` whitelist — `become`
invokes the module via a dynamically-pathed temporary Python interpreter, not the fixed
command line a sudoers entry requires. So the four root-requiring nginx steps are raw
`ansible.builtin.command` tasks that shell out to `sudo <exact whitelisted command>`
directly, rather than `ansible.builtin.template`/`file` under `become: true`. Everything
else (docker operations, writing to `app_dir`) runs as `ahdeploy` with no privilege
escalation at all. `ansible.cfg`'s `[privilege_escalation]` default flips from
`become = True` to `become = False` accordingly — nothing in this repo's Ansible should
escalate implicitly.

## Alternatives Considered

**Keep `deploy` name, just narrow its sudoers** — rejected. `deploy` reads as "the
account that deploys things" — ambiguous once `mikrus-ansible` also needs a full-sudo
operator account for the *whole host*. Two different privilege levels sharing one name
across two repos invites exactly the mistake ADR-0018 already flagged as a risk
(assuming any service's "deploy user" has the same access — see `mikrus-ansible`'s
README, corrected after a fresh-eyes review of ADR-0018/0019 caught this exact wording
bug already once).

**Leave `deploy` with full sudo** — rejected. Nothing in this repo's actual deploy
process needs it; the audit above found the true requirement is four fixed commands.
Carrying broader access than the workload needs is risk without corresponding benefit,
especially for a credential (the SSH private key) held by a CI runner on every push.

## Consequences

### Positive

- If `ahdeploy`'s key leaks, the attacker can reload nginx and swap this app's vhost —
  not gain root on a host shared with another service
- Consistent with `rediscovering-joomla`'s model — the same class of credential (a
  service's own CI deploy key) now carries the same class of risk on both services
- `deploy` unambiguously means "the shared host operator" going forward, not "whichever
  service happened to claim the name first"

### Negative

- More Ansible code: raw `sudo <cmd>` tasks instead of idiomatic `template`/`file`
  modules for the nginx vhost, and a `sudoers.j2` template + narrow command list to
  maintain in lockstep with `roles/nginx/tasks/main.yml` (a path or command changed in
  one without the other silently breaks the whitelist match)
- Migrating the running server required a manual, one-time cutover — **done
  2026-08-05**: `bootstrap.yml` created `ahdeploy`; `docker-compose.prod.yml` got an
  explicit top-level `name: architecture-hub` first (Compose otherwise derives the
  project name from the directory basename, which would have made
  `/home/ahdeploy/architecture-hub-showcase` create fresh, empty `db_data`/`uploads`
  volumes instead of reusing the ones already populated under the old
  `/opt/architecture-hub` project); running `deploy.yml` from the new location then
  re-templated `docker-compose.yml`/`.env` there and Docker Compose picked up the
  *existing* volumes and container names by project-name match, not by directory.
  Verified via `docker ps` (same container names, uptime showing recreation not fresh
  creation) and `/api/articles` returning real content post-cutover. CI's
  `SSH_PRIVATE_KEY` secret was then re-pointed at `ahdeploy`'s key. The old
  `/opt/architecture-hub` (stale `.env` with real secrets, no longer used by anything)
  is left in place deliberately for now as a rollback safety net, not yet deleted
