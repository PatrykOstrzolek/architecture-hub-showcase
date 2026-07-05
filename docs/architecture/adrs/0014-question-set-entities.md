# ADR 0014: Normalized Question/QuestionSet Entities, Replacing Content-Type-Based Exercise Authoring

*   **Status**: Accepted
*   **Date**: 2026-07-02
*   **Deciders**: Patryk O
*   **Context Docs**: [Question Sets](../../product/features/question-sets.md), [Exercises](../../product/features/exercises.md), [ADR 0011](0011-exercise-page-template.md), [ADR 0012](0012-assessment-bounded-context.md), [Sulu Admin Reference](../sulu-admin-reference.md), [Assessment Domain Model](../assessment-domain-model.md)

## Context

ADR-0011/0012 modeled exercise questions as a Sulu block property
(`questions`, type `multiple_choice`) directly on the `exercise` page
template — `option_a`–`option_d` (fixed at exactly four), a `single_select`
`correct` field, and `explanation`, all living in the same content JSON as
publicly-renderable fields. Grading became server-authoritative in ADR-0012,
but the answer key itself still lived inside ordinary page content, hidden
from the public API only by `ExerciseAnswerRedactionSubscriber` — a blanket
`kernel.response` listener that scans and mutates already-serialized JSON by
template name after the fact.

This had three real problems, found while discussing the design:

1.  **Content types are for rendering, not for modeling a security-sensitive
    domain concept.** Redacting the answer key post-hoc is fragile: a new
    field, a typo'd key, or a changed response shape could silently leak it.
    There was no structural reason the answer key *couldn't* reach the
    client — only a listener remembering to strip it.
2.  **Fixed to exactly 4 options, one block type, exactly one correct
    answer.** No "select all that apply" questions, no variable option count,
    without a template/schema change.
3.  **No real persistence/domain model, and no reuse.** Each question was
    authored inline per exercise page; the same question couldn't be reused
    across multiple learning paths' exercises.

## Decision

Model `Question` (with `Option`s) as its own standalone, reusable Doctrine
aggregate with its own admin CRUD screen, and `QuestionSet` as a **separate**
aggregate that curates an ordered, many-to-many selection of existing
`Question`s (its own admin CRUD screen too) — the same `Question` can appear
in multiple `QuestionSet`s. Neither gets Sulu draft/live (plain CRUD — no
versioning need was identified). Each `Option.isCorrect` is an independent
boolean, so a `Question` can have zero, one, or many correct options —
`Grader` compares the submitted set of option ids against the set of
`isCorrect` option ids, so "select all that apply" needs no special-casing:
a single-answer question is just the `count() === 1` case of the same rule.

The `exercise` **page** template is unchanged in kind — it keeps its own
URL, `title`/`intro`, and full draft/live/publish/preview lifecycle (see
ADR-0013). It now references a `QuestionSet` by id through a new bridge
content type, `single_question_set_selection`, the same pattern Sulu itself
uses for `single_page_selection`/`single_category_selection`.

### Architecture: bounded context, aggregates, ports & adapters

`Assessment` (`App\Assessment\*`, from ADR-0012) stays a distinct bounded
context from Sulu's content model. The relationship to Sulu is an
**Anti-Corruption Layer**: Sulu-specific persistence/rendering code
(`PageDimensionContent`, raw DQL, Sulu Admin REST conventions) never appears
in Assessment's `Domain`/`Application` layers — only inside adapters
implementing explicit interfaces (ports):

```
        DRIVING SIDE                 Assessment Domain + Application            DRIVEN SIDE
      (Sulu calls IN)                   (zero knowledge of Sulu)              (we call OUT to Sulu)

 Sulu Admin (REST) ──────►  QuestionController / QuestionSetController
                                        │  (adapters, HTTP → app calls)
                                        ▼
 Sulu headless      ──────►  QuestionSetSelectionPropertyResolver
 render pipeline                       │  (adapter, stored id → resolve call)
                                        ▼
                    Port: QuestionRepositoryInterface
                    Port: QuestionSetRepositoryInterface
                    Port: AttemptRepositoryInterface
                                        │
                                        ▼
                             Grader ── QuestionSet ── Question ── Option ── Attempt
                                        ▲
                                        │  Port: ExerciseQuestionSetLocatorInterface
                                        │  findQuestionSetId(pageUuid): ?int
                                        │
                             SuluExerciseQuestionSetLocator  (adapter — the ONLY
                             class allowed to touch PageDimensionContent/raw DQL)
```

Repository ports (`QuestionRepositoryInterface`, `QuestionSetRepositoryInterface`,
`AttemptRepositoryInterface`) live in `Assessment/Domain/Repository/`;
`ExerciseQuestionSetLocatorInterface` (the Sulu ACL boundary) lives in
`Assessment/Application/Port/`. Each has exactly one Doctrine or Sulu-facing
adapter, bound via a `services.yaml` interface alias. `AnswerKey.php`
(ADR-0012) is deleted — it existed only to abstract away reading untyped
Sulu content JSON, a need real entities remove; `Grader` now operates
directly on `QuestionSet`/`Option` object references (identity comparison,
not database ids — ids don't exist until a real flush, which would have
made `Grader` untestable in pure memory).

### Aggregate boundaries

`Question` owns its `Option`s (true composition — an `Option` cannot exist
without its `Question`, and is never shared). `QuestionSet` owns
*membership and order* via a join entity, `QuestionSetItem` (`questionSet`,
`question`, `position`) — it references a `Question` by identity only, never
reaching into the `Question`'s own internals. This is what lets one
`Question` sit in many `QuestionSet`s, and what keeps "remove from this set"
from ever cascading into "delete the Question". `Attempt` (ADR-0012) stays
deliberately decoupled — no live reference to `QuestionSet`, so a graded
`Attempt` stays valid even if the `QuestionSet` is edited or deleted later.

### Sulu Admin extension (this project's first one)

`AssessmentAdmin` (`Assessment/Infrastructure/Sulu/Admin/`) registers two
nav items ("Questions", "Question Sets") and their list/form views,
following `Sulu\Bundle\TagBundle\Admin\TagAdmin`'s pattern — the simplest
real example in the Sulu vendor tree. No fine-grained `SecurityChecker`
permission gating (unlike `TagAdmin`) — this project has a single admin
role, so the standing `^/admin -> ROLE_USER` firewall rule is the only gate
needed, same as every other admin screen here.

`QuestionController`/`QuestionSetController` are plain Symfony controllers
returning `JsonResponse` (not Sulu's `AbstractRestController`/FOSRestBundle
machinery, which nothing else in this project uses either), matching this
project's existing convention (`PagePreviewController`,
`ExerciseAttemptController`). List/form metadata lives in
`backend/config/lists/`/`backend/config/forms/` (directories already wired
in `sulu_admin.yaml`, previously empty).

The `Question` form's nested `options` editor reuses Sulu's generic `block`
property type (the same mechanism page templates use) — **no new Sulu Admin
React code, no webpack build**, which matters because this project has no
working `npm`/webpack build for `backend/assets/admin`. Each block item must
carry a `"type": "default"` key matching the form XML's declared block type
name, or Sulu Admin throws `"It is impossible that a block has no type"` —
found by actually opening the form in a browser, not from documentation.

The `QuestionSet` form's "pick and order existing Questions" field
(`question_selection`) and the exercise page template's "pick one
QuestionSet" field (`single_question_set_selection`) are both Sulu's
generic, already-shipped selection field types — `selection` (multi,
ordered, like `article_selection`) and `single_selection` (exactly one,
like `single_category_selection`) — configured entirely via
`sulu_admin.yaml`'s `field_type_options` node. No new JS either way.

### The bridge content type is two separate resolution systems, not one

The `single_question_set_selection` **admin form widget** (how the picker
renders and which REST endpoint it calls) is configured via
`sulu_admin.yaml`'s `field_type_options`/`single_selection` and
`resources.<resourceKey>.routes.{list,detail}` nodes — the latter maps a
resourceKey to Symfony route names the Admin SPA's `ResourceRequester` uses
to build API URLs, a **third**, easy-to-miss config node distinct from both
`field_type_options` and the view/route registration in `AssessmentAdmin`;
omitting it produces `"There are no routes for the resourceKey ..."` in the
browser console even though the views and REST endpoints both already exist.

The **public headless JSON resolution** (what `content.questionSet` actually
contains on `/learning-paths/.../exercise.json`) is a *completely separate*
system: `sulu/headless-bundle`'s own `ContentTypeResolverInterface`
(`getContentType()`/`resolve(mixed $data, FieldMetadata, string $locale, array $attributes): ContentView`,
tagged `sulu_headless.content_type_resolver`), **not**
`sulu/sulu`'s native `Sulu\Content\Application\PropertyResolver\Resolver\PropertyResolverInterface`
(`getType()`/`resolve(mixed $data, string $locale, array $params): ContentView`,
tagged `sulu_content.property_resolver`) that Sulu's own `CategoryBundle`/
`ArticleBundle` selection resolvers use for their *non-headless* content
resolution path. `HeadlessWebsiteController` (what `exercise.xml`'s
`<controller>` declares, serving every `.json` route) only consults the
headless-bundle system. Implementing the wrong one compiles, wires, and
shows up correctly in `bin/console debug:container` — the failure only
surfaces at runtime, as `content.questionSet` staying an unresolved raw
integer in the response instead of the expected object. Both interfaces
have a near-identical shape (`getType`/`resolve` vs.
`getContentType`/`resolve`) and both use the exact same "class
implementing the interface" autoconfiguration pattern
(`registerForAutoconfiguration`), which is what makes the wrong choice easy
to make and hard to notice without actually curling the `.json` endpoint.

`QuestionSetSelectionPropertyResolver` (despite the name, kept for
continuity with the content-type name) implements the headless-bundle
interface. It resolves the stored id into
`{ id, title, questions: [{ id, text, options: [{ id, text }] }] }` via
`QuestionSetRepositoryInterface` (port) — `Option.isCorrect` and
`Question.explanation` are deliberately never included, so there's nothing
left to redact. `ExerciseAnswerRedactionSubscriber` (ADR-0012) is deleted.

### Selection-picker id resolution

Sulu's generic selection picker widgets resolve display labels for
already-selected ids via `GET /admin/api/{resourceKey}?ids=1,2,3` (confirmed
against `TagController::cgetAction`'s own handling of this exact
parameter). `QuestionController`/`QuestionSetController::cgetAction()` honor
this filter (via a new `findByIds(array $ids): array` method on both
repository ports, preserving the requested order) — omitting it caused a
real, confirmed-in-browser bug: the `QuestionSet` edit form displayed the
wrong 5 questions (a different `QuestionSet`'s content), because the widget
received every question in the table rather than only the ones it asked
for.

## Grading path changes

*   `SuluExerciseQuestionSetLocator` (replaces `ExerciseContentReader`,
    implements `ExerciseQuestionSetLocatorInterface`): same raw-DQL read of
    `PageDimensionContent.templateData` (`stage = STAGE_LIVE`), now just
    extracting the scalar `templateData['questionSet']` id instead of the
    whole `questions` array.
*   `Grader::grade(QuestionSet $questionSet, array $submittedOptionSets): GradeResult`
    takes `list<list<Option>>` — one **set** of selected `Option` objects
    per question. `GradeResult.perQuestion` gained `correctOptionIds`/
    `submittedOptionIds: list<int>` (was a single `correct: string` letter).
*   `SubmitAttemptService::submit()` now takes raw submitted option ids
    (`list<list<int>>`) from the controller and resolves them against the
    loaded `QuestionSet`'s real `Option`s before calling `Grader` —
    unresolvable ids are silently dropped, matching this codebase's
    existing tolerant "never throws on bad input" style.
*   `ExerciseAttemptController`: dropped the hardcoded
    `VALID_OPTIONS = ['a','b','c','d']` letter set; `MAX_QUESTIONS`/new
    `MAX_SELECTED_OPTIONS_PER_QUESTION` are now generous payload-shape
    sanity caps only, not real content-shape assumptions — actual bounds
    come from whatever the real `QuestionSet` has, enforced downstream by
    `Grader`.
*   `Attempt.exerciseUuid` is unchanged — still the Sulu **page** UUID, so
    the public API contract (`POST /api/exercise-attempts`) didn't need to
    change shape beyond `answers` becoming `list<list<int>>`.

## Frontend changes

`ExerciseContent.questionSet: { id, title, questions: Question[] } | null`
replaces `questions: MultipleChoiceBlock[]`. `exercise-view.tsx` dropped the
fixed `option_a`–`d`/`OPTIONS` constant and radio-button rendering in favor
of a checkbox per option, uniformly — a single-correct question is just the
`options.filter(isCorrect).length === 1` case of the same UI, so no
radio/checkbox branching is needed. `QuizState.answers` became `number[][]`
(the set of selected option ids per question). No new frontend test
harness was added for this (this project has no Vitest/RTL setup yet) —
verified by hand in the browser instead, including submitting a mix of
correct/incorrect answers and confirming the score, per-option
correctness highlighting, and explanations all render from the real
`POST /api/exercise-attempts` response.

## Consequences

### Positive

*   The answer key has a real security boundary instead of a post-hoc
    string-matching redaction pass — it structurally cannot reach the
    public API, because the resolver that builds that response never reads
    `Option.isCorrect` in the first place.
*   Questions are a reusable bank — the same `Question` can back multiple
    `QuestionSet`s, verified via a REST test creating two sets that share
    one question id.
*   "Select all that apply" questions work with zero special-casing in
    `Grader`, the controller validation, or the frontend UI — normalized
    per-option correctness was the right primitive all along.
*   `Grader` stays fully unit-testable in pure memory (no DB), same as
    ADR-0012 — constructing `QuestionSet`/`Question`/`Option` directly and
    comparing by object identity, not by database-generated ids.

### Negative / Risks

*   Meaningfully more code than ADR-0011's "zero custom backend code": a
    new Sulu Admin extension (the project's first), two REST controllers,
    a bridge content type + resolver, and four new Doctrine entities,
    versus a page template and a fixture.
*   Exercises exit Sulu's dimension-content versioning entirely for the
    *question data* (though not for the exercise page itself, which keeps
    full draft/live/preview) — no draft/live for `Question`/`QuestionSet`
    was a deliberate scope decision; editing a live quiz's questions takes
    effect immediately, with no review step. Acceptable at this project's
    scale; would need revisiting if quiz content review became a real
    requirement.
*   Two near-identical, easy-to-confuse Sulu resolver interfaces exist in
    this codebase's dependency tree (`sulu_content.property_resolver` vs.
    `sulu_headless.content_type_resolver`) — implementing the wrong one is
    a silent, compile-time-invisible bug. Documented above specifically so
    a future similar bridge type doesn't repeat the same debugging session.
*   `QuestionSetItem`'s `question` foreign key cascades on delete
    (`onDelete: 'CASCADE'`) — deleting a `Question` silently removes it
    from every `QuestionSet` that referenced it, with no warning in the
    admin UI. Acceptable for now (no soft-delete/usage-check UI exists
    anywhere else in this project either), but worth knowing before
    deleting a `Question` in production.

## Follow-ups (explicitly deferred, not built now)

*   **Draft/live for `Question`/`QuestionSet`** — see Negative/Risks above.
*   **REST test coverage for `QuestionController`/`QuestionSetController`**
    beyond what exists — the current tests cover list/get/create/update/
    delete and the cross-`QuestionSet` reuse case; broader edge cases
    (e.g. malformed nested block payloads) aren't exhaustively covered.
*   **Admin UI warning before deleting a `Question` used in a `QuestionSet`**
    — see the cascade-delete risk above.

## Alternatives Considered

1.  **Keep questions as page content, fix only the redaction mechanism**
    (e.g. a dedicated resolver instead of a blanket subscriber). Rejected —
    this was the starting point of the discussion, but it doesn't solve the
    fixed-4-option limitation or question reuse, and a "cleaner redaction"
    is still redaction: the answer key still transits the same code path a
    public response is built from, just with a better filter.
2.  **A new custom Sulu content type instead of real entities**, keeping
    everything inside `PageDimensionContent.templateData`. Rejected —
    ruled out early in the discussion: a custom content type is still just
    a different admin *widget*; it changes nothing about where the answer
    key is stored or who can reach it, since content types serialize into
    the same page-content JSON blob regardless.
3.  **`Question` owned directly by `QuestionSet`** (one-to-many, no join
    entity), matching the original nested-nested-block design before this
    was reconsidered as many-to-many. Rejected once "the same question
    reused across multiple learning paths" was named as an explicit goal —
    ownership would have required duplicating questions per set instead of
    referencing them.
