# Feature Spec: Interactive Exercises

## 1. Overview

Provide users with optional quiz-style exercises attached to learning paths, allowing them to validate their understanding after working through a path's articles. An exercise is a sequence of multiple-choice questions authored in Sulu and rendered as an interactive, client-side experience on the frontend.

## 2. Goals

- Reinforce learning by giving users an active recall challenge after completing a learning path.
- Keep content authorship entirely within Sulu — no custom admin UI.
- Deliver immediate feedback per question with an optional explanation from the author.

## 3. Scope (MVP)

- New `exercise` Sulu page template referencing a `QuestionSet` (see
  [Question Sets](question-sets.md) — questions/options are normalized,
  reusable entities with their own admin screens as of
  [ADR-0014](../../architecture/adrs/0014-question-set-entities.md), not
  authored inline on this template).
- Each learning path can optionally reference one exercise page via a `single_page_selection` field.
- Frontend renders the exercise as a single-page quiz with per-question feedback.
- Grading is server-authoritative and each submission is persisted, keyed to an
  anonymous, locally-generated session id — no user accounts. See
  [ADR-0012](../../architecture/adrs/0012-assessment-bounded-context.md).

Out of scope for MVP: real user accounts (and merging anonymous attempts into one later), multiple exercise pages per path, free-text or code-challenge question types, retry-limit/cooldown rules, an admin-facing attempts dashboard.

## 4. Content Model

The exercise page template itself only carries `title`/`url`/`intro` and a
reference to a `QuestionSet`; the questions, options, and answer key live in
normalized entities with their own admin screens. See
[Question Sets §4](question-sets.md#4-content-model) for the full field
reference, and [ADR-0014](../../architecture/adrs/0014-question-set-entities.md)
for why this replaced the original inline-block design (still described
there for historical context).

### `learning-path` template change

Add one optional field to `backend/config/templates/pages/learning-path.xml`:

```xml
<property name="exercise" type="single_page_selection">
  <meta><title lang="en">Exercise</title></meta>
</property>
```

This delivers as `content.exercise: { id, content: { title, url } } | null` via the built-in resolver — no custom backend code.

## 5. API

The exercise page itself is still served by the existing `HeadlessWebsiteController`
at `/learning-paths/{slug}/exercise.json`, exactly like every other template.
The frontend fetches it with the same `getContent()` helper;
`content.questionSet` never includes the answer key — see
[Question Sets §5](question-sets.md#5-api) for the exact resolved shape and
why (a real structural boundary now, not a redaction pass).

Submitting answers is a custom endpoint, added in [ADR-0012](../../architecture/adrs/0012-assessment-bounded-context.md),
with its payload shape updated in [ADR-0014](../../architecture/adrs/0014-question-set-entities.md):

```
POST /api/exercise-attempts
{ "exerciseUuid": string, "sessionId": string, "answers": number[][] }
→ { "score": number, "total": number, "results": [{ correctOptionIds, submittedOptionIds, isCorrect, explanation }] }
```

`answers[i]` is the set of selected Option ids for question `i` — see
[Question Sets §5](question-sets.md#5-api) for the full grading contract,
including how "select all that apply" is submitted.

The browser never calls this directly — it goes through a Next.js proxy route
(`frontend/app/api/exercise-attempts/route.ts`), the same server-to-server
shape as every other `/api/*` route in this project.

## 6. Frontend

### New files

| File | Purpose |
|---|---|
| `frontend/components/content/exercise-view.tsx` | Client Component (`'use client'`); owns all quiz state |
| `frontend/lib/anonymous-session.ts` | Generates/persists the anonymous `sessionId` (`localStorage`, no cookies) |
| `frontend/app/api/exercise-attempts/route.ts` | Proxies submissions to the backend's `POST /api/exercise-attempts` |

### Modified files

| File | Change |
|---|---|
| `frontend/components/content/types.ts` | `ExerciseQuestion`/`ExerciseOption`/`ExerciseQuestionSet`, `ExerciseContent`, `ExerciseGradeResult` types |
| `frontend/components/content/learning-path-view.tsx` | Add "Test yourself →" link when `content.exercise` is set |
| `frontend/app/[[...slug]]/page.tsx` | Add `case "exercise":` to the template switch; pass the page's own `id` as `exerciseId` |

### Routing

`/learning-paths/{slug}/exercise` is caught by the existing `[[...slug]]` catch-all — no new route file needed.

### Quiz state shape

```ts
type QuizState = {
  answers: number[][]  // per question, the set of selected option ids; [] = unanswered
  submitting: boolean
  error: string | null
  result: ExerciseGradeResult | null  // non-null once the server has graded the submission
}
```

Every option renders as a checkbox uniformly — there's no radio-vs-checkbox
branching based on how many options a question has correct. See
[Question Sets](question-sets.md) for why.

The in-progress answers (and the graded result) reset on page refresh — nothing
about the *quiz UI* is persisted client-side. The submission itself is
persisted server-side (an `Attempt` row per submit), but the frontend never
reads that back.

### User flow

1. User finishes reading the learning path articles.
2. "Test yourself →" button appears at the bottom of the learning path page (only when an exercise is linked).
3. User navigates to `/learning-paths/{slug}/exercise`.
4. Breadcrumb / back link returns to the learning path (via optional `?path={slug}` query param).
5. User answers each question and submits.
6. Frontend POSTs to `/api/exercise-attempts`; per-question feedback (correct/incorrect + optional author explanation) is rendered from the server's response.
7. Summary screen shows the server-computed score.

## 7. Acceptance Criteria

- Content manager can create an exercise page under a learning path in Sulu, attach an existing `QuestionSet` via the `questionSet` field, and link the page via `learning-path.xml`'s `exercise` field.
- Exercise page displays title, optional intro, and all questions from the attached `QuestionSet`.
- Each question shows its options as checkboxes; any number can be selected (see [Question Sets](question-sets.md) for "select all that apply").
- The public exercise JSON never includes the answer key (`isCorrect`/`explanation`) before submission.
- Submitting reveals which answers are correct and shows any explanations, as returned by `POST /api/exercise-attempts`.
- "Test yourself →" link appears on the learning path page only when an exercise is linked.
- Score summary is shown after submission.
- Refreshing the page resets the quiz UI (a new submission still creates a new server-side attempt; no history is shown back to the user).

## 8. Architecture Notes

A separate `exercise` page template is preferred over embedding quiz blocks directly in the learning-path template because:

- Exercises have their own URL (enabling direct linking from articles).
- Exercises can be cached and invalidated independently of the learning path page.
- The separation keeps the learning-path template simple and follows the same `author` / `author profile` split already established in the project.

See ADR-0011 for the full decision record.
