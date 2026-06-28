import { mediaUrl } from "@/lib/sulu"
import { cn } from "@/lib/utils"
import type { ArticleBlock } from "./types"

const richText = cn(
  "text-base leading-relaxed",
  "[&_p]:mb-5",
  "[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_a]:transition-all [&_a:hover]:underline-offset-2",
  "[&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight",
  "[&_h3]:mt-7 [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold",
  "[&_ol]:mb-5 [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:mb-5 [&_ul]:list-disc [&_ul]:pl-6",
  "[&_li]:mb-1.5",
  "[&_strong]:font-semibold",
  "[&_blockquote]:my-5 [&_blockquote]:border-l-4 [&_blockquote]:border-muted-foreground/30 [&_blockquote]:pl-5 [&_blockquote]:text-muted-foreground [&_blockquote]:italic",
  "[&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-sm"
)

export function BlockRenderer({ blocks }: { blocks: ArticleBlock[] }) {
  return (
    <div className="space-y-8">
      {blocks.map((block, i) => (
        <Block key={i} block={block} />
      ))}
    </div>
  )
}

function Block({ block }: { block: ArticleBlock }) {
  switch (block.type) {
    case "text":
      return (
        <div
          className={richText}
          dangerouslySetInnerHTML={{ __html: block.text }}
        />
      )

    case "code":
      return (
        <figure className="space-y-2">
          <div className="overflow-hidden rounded-lg">
            <div className="flex items-center bg-zinc-800 px-4 py-2">
              <span className="font-mono text-xs text-zinc-400">
                {block.language || "code"}
              </span>
            </div>
            <pre className="overflow-x-auto bg-zinc-900 p-4 font-mono text-sm leading-relaxed text-zinc-100">
              <code>{block.code}</code>
            </pre>
          </div>
          {block.caption ? (
            <figcaption className="text-center text-sm text-muted-foreground">
              {block.caption}
            </figcaption>
          ) : null}
        </figure>
      )

    case "image":
      if (!block.image) return null
      return (
        <figure className="space-y-2">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={mediaUrl(block.image.url)}
            alt={block.caption ?? block.image.title ?? ""}
            className="w-full rounded-lg"
          />
          {block.caption ? (
            <figcaption className="text-center text-sm text-muted-foreground">
              {block.caption}
            </figcaption>
          ) : null}
        </figure>
      )

    case "callout":
      return (
        <aside
          className={cn(
            "rounded-lg border-l-4 p-4",
            block.style === "warning" && "border-amber-500 bg-amber-500/10",
            block.style === "tip" && "border-emerald-500 bg-emerald-500/10",
            (block.style === "info" ||
              !["warning", "tip"].includes(block.style)) &&
              "border-sky-500 bg-sky-500/10"
          )}
        >
          <div
            className={richText}
            dangerouslySetInnerHTML={{ __html: block.content }}
          />
        </aside>
      )

    default:
      return null
  }
}
