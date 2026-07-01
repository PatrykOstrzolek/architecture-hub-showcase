# ADR 0012: Assessment Bounded Context for Server-Graded Exercise Attempts

*   **Status**: Accepted
*   **Date**: 2026-07-01
*   **Deciders**: Patryk O
*   **Context Docs**: [Exercises](../../product/features/exercises.md), [ADR 0011](0011-exercise-page-template.md)

## Context

ADR-0011 shipped the Interactive Exercises feature entirely inside Sulu's content
model: a page template plus a fixture, zero custom backend code. Grading was a
client-side comparison against a `correct` field that shipped in the same
headless JSON payload as the questions themselves.

That worked because grading had no real business logic. It now needs some:
the answer key must not be visible to the browser before the user submits, and
a submission needs to be recorded somewhere that isn't Sulu's content storage
(an *attempt* is a transactional fact about a visitor's action, not authored
content). This is the first requirement in the project that doesn't fit
Sulu's field/resolver system, and the first place the backend needs actual
domain logic rather than template configuration.

## Decision

Introduce a small `Assessment` bounded context under `backend/src/Assessment/`,
split into `Domain` / `Application` / `Infrastructure`, alongside — not
replacing — the existing flat `src/{Controller,Entity,Repository}` structure
used for everything else:

*   **`Domain/Model`**: `Attempt` (the persisted aggregate — a Doctrine entity),
    and two plain value objects, `AnswerKey` and `GradeResult`.
*   **`Domain/Service/Grader`**: pure grading logic with no framework or
    persistence dependency — the one class this ADR is really about. It's the
    obvious place for a future pass/fail threshold or weighted scoring rule.
*   **`Infrastructure`**: `ExerciseContentReader` (loads the authoritative
    answer key straight from `Sulu\Page\Domain\Model\PageDimensionContent` via
    a raw Doctrine query, mirroring the existing style in
    `ArticlesByTaxonomyController` rather than pulling in Sulu's
    `StructureResolver`/`DocumentManager`) and `AttemptRepository`.
*   **`Application/SubmitAttemptService`**: orchestrates the three — read the
    answer key, grade, persist — and is what `ExerciseAttemptController`
    (`POST /api/exercise-attempts`, in the existing `Controller/Website`
    directory) calls.

Grading became server-authoritative as part of this: the exercise template
still declares `correct`/`explanation` as ordinary Sulu properties (needed for
admin authoring/preview), but a new `kernel.response` subscriber,
`ExerciseAnswerRedactionSubscriber`, strips those two fields from the public
headless JSON when `template === 'exercise'`, before FOSHttpCacheBundle ever
stores the response. The frontend now POSTs answers to
`/api/exercise-attempts` and renders correctness/explanations from *that*
response instead of from the initial page load.

### Anonymous identity, not accounts or cookies

Each `Attempt` is keyed by a `sessionId` the frontend generates client-side
(`crypto.randomUUID()`, persisted in `localStorage`) — not a cookie, and not a
user account. The browser never talks to the Sulu backend directly for this
feature; it POSTs to a Next.js proxy route (`app/api/exercise-attempts/route.ts`,
the same server-to-server-proxy shape as every other `/api/*` route in this
project), which forwards to the backend. Because of that, a cookie-based
session would have bought nothing but cross-site cookie/CORS complexity
between the Vercel-hosted frontend and the Sulu backend's own domain, for a
feature that explicitly still has no user accounts.

## Consequences

### Positive

*   `Grader` is fully unit-testable in isolation (no DB, no HTTP) —
    `tests/Unit/Assessment/Domain/GraderTest.php`.
*   The answer key can no longer be read from the network tab before
    submitting.
*   The `Domain/Application/Infrastructure` split gives future backend logic
    that isn't a Sulu content concern an established home, instead of forcing
    a choice between "cram it into a template" and "flat, ad-hoc classes in
    `src/`".
*   `doctrine.yaml` gained one more `orm.mappings` entry
    (`src/Assessment/Domain/Model` → `App\Assessment\Domain\Model`) so `Attempt`
    doesn't have to live under `src/Entity` just to be mapped.

### Negative / Risks

*   Second ORM mapping root and a second custom backend directory structure to
    onboard new contributors to — accepted, since the alternative (flattening
    `Attempt` into `src/Entity` and the rest into `src/`) would have erased the
    layering this ADR exists to introduce.
*   Submitting now requires a network round trip before showing feedback,
    where it was previously instant; `exercise-view.tsx` has a
    submitting/error state to cover network failure, which didn't exist
    before.
*   `sessionId` is client-generated and unauthenticated — trivially
    spoofable/resettable (clearing `localStorage` starts a "new" visitor). This
    is an accepted risk: attempts are anonymous, ungraded-for-consequence quiz
    data, not something requiring tamper-resistance.

## Follow-ups (explicitly deferred, not built now)

*   **Real user accounts** and merging anonymous attempts into an account
    later — no accounts exist anywhere in this project yet; out of scope until
    one does.
*   **Retry-limit/cooldown rules** — `Grader`/`Attempt` don't prevent
    resubmission; every submit creates a new row. Easy to add later inside
    `Grader` or `SubmitAttemptService` without touching the HTTP or
    persistence layers.
*   **Admin-facing attempts dashboard** — no reporting/aggregation UI over
    `exercise_attempt` is built.

## Alternatives Considered

1.  **Keep client-side grading, just log the client's reported score.**
    Rejected — the score would be unverified/trusted input, and there'd be no
    real domain logic to place anywhere; it would reduce `Assessment` to a
    write-only logger rather than the actual business-logic seam this ADR is
    about.
2.  **Cookie-based anonymous session (`Set-Cookie` from the Sulu backend).**
    Rejected — the browser never calls the Sulu backend directly today (every
    `/api/*` call is proxied through Next.js), so a cookie would need to
    survive a cross-site (Vercel → Sulu domain) round trip, requiring
    `SameSite=None; Secure` and CORS credential config that doesn't exist
    anywhere else in this project, for no benefit over a client-generated id.
3.  **Interface + swappable implementation for `SubmitAttemptService`.**
    Rejected as premature — there is exactly one implementation and no second
    one is anticipated; the class is `readonly` but not `final` purely so
    PHPUnit can double it in `ExerciseAttemptControllerTest`.
