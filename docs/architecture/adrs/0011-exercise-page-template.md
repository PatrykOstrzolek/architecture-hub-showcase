# ADR 0011: Exercise Page Template for Interactive Quizzes

*   **Status**: Accepted
*   **Date**: 2026-07-01
*   **Deciders**: Patryk O
*   **Context Docs**: [Exercises](../../product/features/exercises.md), [ADR 0004](0004-content-modeling.md)

## Context

Learning paths need an optional quiz to let a user validate understanding after
reading the path's articles. Two ways to model this: (a) embed a `questions`
block directly on `learning-path.xml`, or (b) a separate `exercise` page
template linked via a `single_page_selection`, mirroring the Author /
author-profile split already formalized in ADR 0004.

## Decision

Model **Exercise** as its own Sulu page template (`exercise`), linked from
`learning-path` via an optional `single_page_selection` field named `exercise`.
No custom backend controller — served by the existing
`HeadlessWebsiteController::indexAction`, same as every other template (ADR 0003).

### Why a separate template

*   **Own URL** (`/learning-paths/{slug}/exercise`) enables direct linking from
    an article ("test what you just read") without navigating through the
    learning-path page.
*   **Independent cache lifetime** — the learning-path page (description,
    article order) and its exercise (questions) change on different cadences
    and can be revalidated independently.
*   **Precedent**: structurally identical to the Author (Contact) /
    author-profile-page split formalized in ADR 0004 — a focused template with
    its own route, referenced by `single_page_selection` from the page that
    needs it.
*   Keeps `learning-path.xml` simple (one optional field) instead of growing a
    second, unrelated block type into it.

### Why not embed directly in learning-path.xml

Rejected: couples quiz authoring/caching to the path page, prevents direct
linking, and expresses the "one optional exercise per path" cardinality less
cleanly than a nullable page reference.

## Consequences

### Positive

*   Zero custom backend code (template + fixture only).
*   `content.exercise: { id, content: { title, url } } | null` resolves for
    free via the built-in `single_page_selection` resolver.
*   Exercise pages can be authored, previewed and published independently of
    their learning path.

### Negative / Risks

*   One more page template and one more page per learning path for content
    managers to maintain — acceptable at MVP scale (a handful of learning paths).
*   The link is one-directional (learning-path → exercise); the exercise page
    doesn't carry a back-reference to its path except by URL convention
    (`/learning-paths/{slug}/exercise`) — the frontend derives the back-link
    from the `?path=` query param, the same convention `ArticleView` already uses.

## Follow-ups

*   `backend/config/templates/pages/exercise.xml`
*   `exercise` field on `backend/config/templates/pages/learning-path.xml`
*   `ExerciseFixture`, sequenced before `LearningPathFixture` so the latter can
    resolve each exercise's UUID by convention slug

## Alternatives Considered

1.  **Blocks directly on `learning-path.xml`.** Rejected — see above.
2.  **Exercise as a Snippet.** Rejected: no route means no direct link and no
    independent cache lifetime.
