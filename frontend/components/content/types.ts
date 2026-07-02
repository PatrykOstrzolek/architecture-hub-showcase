/**
 * Template-specific content shapes for the Architecture Hub templates, layered on
 * top of the generic client in `lib/sulu.ts`. Verified against live `/.json`
 * payloads (see docs/architecture/content-types-reference.md).
 */
import type { SuluCategory, SuluContact, SuluMedia } from "@/lib/sulu"

// --- Article body blocks --------------------------------------------------

export interface TextBlock {
  type: "text"
  settings: unknown[]
  text: string
}

export interface CodeBlock {
  type: "code"
  settings: unknown[]
  language: string
  caption: string | null
  code: string
}

export interface ImageBlock {
  type: "image"
  settings: unknown[]
  /** `single_media_selection` resolves to one media object (or null). */
  image: SuluMedia | null
  caption: string | null
}

export interface CalloutBlock {
  type: "callout"
  settings: unknown[]
  style: "info" | "tip" | "warning" | string
  content: string
}

export type ArticleBlock = TextBlock | CodeBlock | ImageBlock | CalloutBlock

// --- Exercise / QuestionSet ------------------------------------------------

/**
 * `isCorrect` is never present — the QuestionSetSelectionPropertyResolver
 * deliberately never includes it in headless output (see ADR-0014), so
 * there's nothing to redact after the fact like the old
 * ExerciseAnswerRedactionSubscriber had to. A Question can have zero, one,
 * or many correct Options; the UI always renders checkboxes uniformly
 * (see ExerciseView) rather than branching on option count.
 */
export interface ExerciseOption {
  id: number
  text: string
}

export interface ExerciseQuestion {
  id: number
  text: string
  options: ExerciseOption[]
}

export interface ExerciseQuestionSet {
  id: number
  title: string
  questions: ExerciseQuestion[]
}

/** Response shape from `POST /api/exercise-attempts`. See ADR-0014. */
export interface ExerciseGradeResult {
  score: number
  total: number
  results: Array<{
    correctOptionIds: number[]
    submittedOptionIds: number[]
    isCorrect: boolean
    explanation: string | null
  }>
}

// --- Template content shapes ----------------------------------------------

/** `article` template. */
export interface ArticleContent {
  title: string
  url: string | null
  summary: string
  author: SuluContact | null
  body: ArticleBlock[]
  categories: SuluCategory[]
  tags: string[]
}

/**
 * One resolved article item as returned by either the `article_selection`
 * content type (author page) or the `articles` smart_content provider (homepage).
 * Both resolve through the same StructureResolver path, so the shape is identical;
 * `summary` is only present when the field requests it as an extra property.
 */
export interface ArticleSelectionItem {
  id: string
  type: string
  template: string
  content: { title: string; url: string | null; summary?: string | null }
  view: {
    url?: { page?: { uuid: string; path: string }; suffix?: string }
  }
}

/**
 * Build an article's href. A `page_tree_route` URL is NOT in `content.url`
 * (it's null) — it resolves into `view.url` as `{ page.path, suffix }`
 * (verified, see content-types-reference.md).
 */
export function articleHref(item: ArticleSelectionItem): string {
  if (item.content.url) return item.content.url
  const page = item.view?.url?.page
  const suffix = item.view?.url?.suffix
  if (page && suffix) return `${page.path.replace(/\/$/, "")}/${suffix}`
  return "#"
}

/** One item resolved from the `page_selection` field on the `authors` listing template. */
export interface AuthorListingItem {
  id: string
  content: {
    title: string
    url: string | null
    position: string | null
    bio: string | null
  }
}

/** `authors` listing page template — curated list of author profile pages. */
export interface AuthorsListingContent {
  title: string
  url: string | null
  authors: AuthorListingItem[]
}

/** `author` profile page template. */
export interface AuthorContent {
  title: string
  url: string | null
  photo: import("@/lib/sulu").SuluMedia | null
  position: string | null
  bio: string | null
  articles: ArticleSelectionItem[]
}

/** `default` page template — simple rich-text body. */
export interface PageContent {
  title: string
  url: string | null
  article: string
}

/** `homepage` page template — an auto article feed (smart_content). */
export interface HomeContent {
  title: string
  url: string | null
  articles: ArticleSelectionItem[]
}

/** One item resolved from the `page_selection` field on the `learning-paths` listing template. */
export interface LearningPathListingItem {
  id: string
  content: {
    title: string
    url: string | null
    description: string | null
    articles: ArticleSelectionItem[]
  }
}

/** `learning-paths` listing page template — curated list of learning path pages. */
export interface LearningPathsListingContent {
  title: string
  url: string | null
  paths: LearningPathListingItem[]
}

/** One item resolved from the `single_page_selection` field on the `learning-path` template's `exercise` field. */
export interface ExerciseLink {
  id: string
  content: {
    title: string
    url: string | null
  }
}

/** `learning-path` page template — ordered sequence of articles. */
export interface LearningPathContent {
  title: string
  url: string | null
  description: string
  articles: ArticleSelectionItem[]
  exercise: ExerciseLink | null
}

/** `exercise` page template — a quiz attached to a learning path. See ADR 0011 / ADR-0014. */
export interface ExerciseContent {
  title: string
  url: string | null
  intro: string | null
  questionSet: ExerciseQuestionSet | null
}

/** Contextual navigation passed to ArticleView when reading inside a learning path. */
export interface LearningPathContext {
  title: string
  slug: string
  current: number
  total: number
  prev: string | null
  next: string | null
}
