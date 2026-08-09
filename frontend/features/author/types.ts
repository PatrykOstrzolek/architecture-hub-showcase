import type { SuluMedia } from "@/lib/sulu"
import type { ArticleSelectionItem } from "@/features/shared/content-types"

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
  photo: SuluMedia | null
  position: string | null
  bio: string | null
  articles: ArticleSelectionItem[]
}
