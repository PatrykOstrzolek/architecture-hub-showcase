# Feature Spec: Interactive Exercises

## 1. Overview

Provide users with optional quiz-style exercises attached to learning paths, allowing them to validate their understanding after working through a path's articles. An exercise is a sequence of multiple-choice questions authored in Sulu and rendered as an interactive, client-side experience on the frontend.

## 2. Goals

- Reinforce learning by giving users an active recall challenge after completing a learning path.
- Keep content authorship entirely within Sulu — no custom admin UI.
- Deliver immediate feedback per question with an optional explanation from the author.

## 3. Scope (MVP)

- New `exercise` Sulu page template with a `multiple_choice` block type.
- Each learning path can optionally reference one exercise page via a `single_page_selection` field.
- Frontend renders the exercise as a single-page quiz with per-question feedback.
- State is client-side only — no persistence, no user accounts required.

Out of scope for MVP: progress persistence (localStorage or server), multiple exercise pages per path, free-text or code-challenge question types.

## 4. Content Model

### `exercise` page template (`backend/config/templates/pages/exercise.xml`)

| Property | Type | Notes |
|---|---|---|
| `title` | `text_line` | Required; used as page heading and `sulu.rlp.part` |
| `url` | `route` | Auto-generated slug; lives under `/learning-paths/{slug}/exercise` |
| `intro` | `text_area` | Optional preamble shown before the first question |
| `questions` | `block` (1–20) | One block type: `multiple_choice` |

**`multiple_choice` block properties:**

| Property | Type | Notes |
|---|---|---|
| `question` | `text_line` | Mandatory |
| `option_a` – `option_d` | `text_line` | Four answer options |
| `correct` | `single_select` | Values: `a`, `b`, `c`, `d` |
| `explanation` | `text_area` | Optional; shown after the user answers |

### `learning-path` template change

Add one optional field to `backend/config/templates/pages/learning-path.xml`:

```xml
<property name="exercise" type="single_page_selection">
  <meta><title lang="en">Exercise</title></meta>
</property>
```

This delivers as `content.exercise: { id, content: { title, url } } | null` via the built-in resolver — no custom backend code.

## 5. API

No custom endpoint. The exercise page is served by the existing `HeadlessWebsiteController` at `/learning-paths/{slug}/exercise.json`, exactly like every other template. The frontend fetches it with the same `getContent()` helper.

## 6. Frontend

### New files

| File | Purpose |
|---|---|
| `frontend/components/content/exercise-view.tsx` | Client Component (`'use client'`); owns all quiz state |

### Modified files

| File | Change |
|---|---|
| `frontend/components/content/types.ts` | Add `MultipleChoiceBlock`, `ExerciseBlock`, `ExerciseContent` types |
| `frontend/components/content/learning-path-view.tsx` | Add "Test yourself →" link when `content.exercise` is set |
| `frontend/app/[[...slug]]/page.tsx` | Add `case "exercise":` to the template switch |

### Routing

`/learning-paths/{slug}/exercise` is caught by the existing `[[...slug]]` catch-all — no new route file needed.

### Quiz state shape

```ts
type QuizState = {
  answers: (string | null)[]  // indexed per question; null = unanswered
  submitted: boolean
}
```

State resets on page refresh. Accepted limitation for MVP.

### User flow

1. User finishes reading the learning path articles.
2. "Test yourself →" button appears at the bottom of the learning path page (only when an exercise is linked).
3. User navigates to `/learning-paths/{slug}/exercise`.
4. Breadcrumb / back link returns to the learning path (via optional `?path={slug}` query param).
5. User answers each question and submits.
6. Immediate per-question feedback shown: correct/incorrect + optional author explanation.
7. Summary screen shows score.

## 7. Acceptance Criteria

- Content manager can create an exercise page under a learning path in Sulu and link it via the `exercise` field.
- Exercise page displays title, optional intro, and all questions.
- Each question shows four answer options; only one can be selected.
- Submitting reveals which answers are correct and shows any explanations.
- "Test yourself →" link appears on the learning path page only when an exercise is linked.
- Score summary is shown after submission.
- Refreshing the page resets the quiz.

## 8. Architecture Notes

A separate `exercise` page template is preferred over embedding quiz blocks directly in the learning-path template because:

- Exercises have their own URL (enabling direct linking from articles).
- Exercises can be cached and invalidated independently of the learning path page.
- The separation keeps the learning-path template simple and follows the same `author` / `author profile` split already established in the project.

See ADR-0011 for the full decision record.
