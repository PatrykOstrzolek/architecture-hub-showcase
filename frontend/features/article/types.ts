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
