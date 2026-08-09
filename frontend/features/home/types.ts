import type { ArticleSelectionItem } from "@/features/shared/content-types"

/** `homepage` page template — an auto article feed (smart_content). */
export interface HomeContent {
  title: string
  url: string | null
  articles: ArticleSelectionItem[]
}
