import type { ArticleSelectionItem } from "@/features/shared/content-types"

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
