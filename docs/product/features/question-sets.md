# Feature Spec: Question Sets

## 1. Overview

`Question` and `QuestionSet` are normalized, reusable entities backing the
Interactive Exercises feature (see [Exercises](exercises.md)). A `Question`
(with its `Option`s) is authored once, in its own admin screen, and can be
attached to any number of `QuestionSet`s. A `QuestionSet` is an ordered,
curated selection of existing `Question`s, also with its own admin screen.
An `exercise` page references exactly one `QuestionSet`. See
[ADR-0014](../../architecture/adrs/0014-question-set-entities.md) for the
full decision record and why this replaced authoring questions as inline
Sulu page content.

## 2. Goals

- Let a question be written once and reused across multiple learning paths'
  exercises, instead of re-typed per exercise page.
- Give the answer key (`Option.isCorrect`) a real structural boundary — it
  must never be reachable from the public API, not just hidden by a
  post-hoc filter.
- Support "select all that apply" questions (zero, one, or many correct
  options per question) without special-casing single-answer questions.

## 3. Scope

- Two new Sulu Admin sections, "Questions" and "Question Sets" (Settings
  nav), each with a standard list + add/edit form.
- `exercise` page template references a `QuestionSet` via a single-select
  bridge field (`single_question_set_selection`) instead of authoring
  questions inline.
- No draft/live for `Question`/`QuestionSet` — plain CRUD, changes take
  effect immediately. (The `exercise` page itself keeps full draft/live/
  preview — only the question data moved out of page content.)

Out of scope: draft/live for question data, an admin UI warning before
deleting a `Question` that's in use, question types other than
multiple-choice/select-many (e.g. free text).

## 4. Content Model

### `Question` (own admin screen, resource key `questions`)

| Field | Type | Notes |
|---|---|---|
| `text` | `text_line` | The question prompt |
| `explanation` | `text_area` | Optional; shown to the end user only after they answer |
| `options` | `block` (2–8), type `default` | Nested `Option`s |

**`Option` (nested block properties):**

| Field | Type | Notes |
|---|---|---|
| `text` | `text_line` | The option's displayed text |
| `isCorrect` | `checkbox` | Independent per option — a question can have 0, 1, or many correct options |

### `QuestionSet` (own admin screen, resource key `question_sets`)

| Field | Type | Notes |
|---|---|---|
| `title` | `text_line` | Admin-facing label only — never shown to end users |
| `questionIds` | `question_selection` (multi, ordered) | Pick and order existing `Question`s; the same `Question` can be reused across multiple `QuestionSet`s |

### `exercise` page template (unchanged fields except `questions` → `questionSet`)

| Property | Type | Notes |
|---|---|---|
| `title` | `text_line` | Required |
| `url` | `route` | `/learning-paths/{slug}/exercise` |
| `intro` | `text_area` | Optional preamble |
| `questionSet` | `single_question_set_selection` | References one `QuestionSet` by id; reusable on any page template, not exercise-specific |

## 5. API

### Admin REST (authenticated, `ROLE_USER`)

```
GET    /admin/api/questions                    list (supports ?ids=1,2,3 for picker label resolution)
GET    /admin/api/questions/{id}
POST   /admin/api/questions                     { text, explanation, options: [{ text, isCorrect }] }
PUT    /admin/api/questions/{id}                same body; clears and rebuilds options
DELETE /admin/api/questions/{id}

GET    /admin/api/question-sets                 list (supports ?ids=...)
GET    /admin/api/question-sets/{id}
POST   /admin/api/question-sets                 { title, questionIds: number[] }
PUT    /admin/api/question-sets/{id}            same body; clears and rebuilds membership/order
DELETE /admin/api/question-sets/{id}
```

Unresolvable `questionIds` on save are silently dropped (tolerant, matches
this codebase's existing "never throws on bad input" style elsewhere).

### Public headless (`.json` routes)

`content.questionSet` on the exercise page resolves to:

```json
{
  "id": 2,
  "title": "Distributed Systems Fundamentals Quiz",
  "questions": [
    {
      "id": 3,
      "text": "During a network partition, the CAP theorem forces a distributed system to choose between which two guarantees?",
      "options": [
        { "id": 6, "text": "Consistency and Availability" },
        { "id": 7, "text": "Consistency and Partition Tolerance" }
      ]
    }
  ]
}
```

`isCorrect` and `explanation` are never present — see ADR-0014's "bridge
content type" section for exactly which resolver enforces this.

### Grading (`POST /api/exercise-attempts`, unchanged endpoint, changed shapes)

```
POST /api/exercise-attempts
{ "exerciseUuid": string, "sessionId": string, "answers": number[][] }
```

`answers[i]` is the **set** of selected Option ids for question `i` (in the
same order as `questionSet.questions`) — an empty array means unanswered.
Multiple ids in one question's array is how "select all that apply" is
submitted; no separate flag or question "type" distinguishes single- from
multi-answer questions.

```json
{
  "score": 4,
  "total": 5,
  "results": [
    {
      "correctOptionIds": [6],
      "submittedOptionIds": [6],
      "isCorrect": true,
      "explanation": "Partition tolerance cannot be sacrificed..."
    }
  ]
}
```

A question is correct when the submitted id set exactly equals the correct
id set (order-independent) — a single-answer question is just the
`correctOptionIds.length === 1` case of the same rule.

## 6. Frontend

`ExerciseView` (`frontend/components/content/exercise-view.tsx`) renders
every option as a checkbox, uniformly — there is no radio/checkbox
branching based on option count. `QuizState.answers: number[][]` tracks the
selected option id set per question; toggling an option adds/removes its id
from that question's array. See [Exercises](exercises.md) for the full
frontend file list and user flow, which are otherwise unchanged by this
entity model — only the content/payload shapes moved.

## 7. Acceptance Criteria

- A content editor can create a `Question` with 2–8 options and mark any
  number of them correct, independently of the "Questions" nav item's
  create-page-template flow.
- The same `Question` can be added to more than one `QuestionSet`.
- A `QuestionSet`'s question order is preserved and editable via drag
  reorder in the admin form.
- An exercise page's `questionSet` picker only offers existing
  `QuestionSet`s, never inline question authoring.
- The public exercise JSON never includes `isCorrect` on any option or
  `explanation` on any question, verified by
  `QuestionSetSelectionPropertyResolverTest`.
- A "select all that apply" question (2+ correct options) grades correctly
  only when the submitted set exactly matches, verified by `GraderTest`.
- Deleting a `Question` removes it from any `QuestionSet` it was part of,
  without deleting other questions in that set.
