import { mediaUrl } from "@/lib/sulu"
import { sanitize } from "@/lib/sanitize"
import { cn } from "@/lib/utils"
import type { ArticleBlock } from "./types"

const richText = cn(
  "text-base leading-7",
  "[&_p]:mb-5",
  "[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_a]:transition-all [&_a:hover]:underline-offset-2",
  "[&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:font-heading [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:tracking-tight",
  "[&_h3]:mt-8 [&_h3]:mb-2 [&_h3]:font-heading [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:tracking-tight",
  "[&_h4]:mt-6 [&_h4]:mb-1.5 [&_h4]:font-heading [&_h4]:text-base [&_h4]:font-semibold",
  "[&_ol]:mb-5 [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:mb-5 [&_ul]:list-disc [&_ul]:pl-6",
  "[&_li]:mb-2",
  "[&_strong]:font-semibold",
  "[&_blockquote]:my-6 [&_blockquote]:border-l-4 [&_blockquote]:border-primary/60 [&_blockquote]:pl-5 [&_blockquote]:text-muted-foreground [&_blockquote]:italic",
  "[&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.875em] [&_code]:text-foreground"
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
          dangerouslySetInnerHTML={{ __html: sanitize(block.text) }}
        />
      )

    case "code":
      return (
        <figure className="my-2 space-y-2">
          <div className="overflow-hidden rounded">
            <div className="flex items-center gap-3 bg-[oklch(0.123_0.022_262)] px-4 py-2.5">
              <div className="flex gap-1.5">
                <span className="size-2.5 rounded-full bg-white/15" />
                <span className="size-2.5 rounded-full bg-white/15" />
                <span className="size-2.5 rounded-full bg-white/15" />
              </div>
              <span className="font-mono text-xs text-primary/90">
                {block.language || "code"}
              </span>
            </div>
            <pre className="overflow-x-auto bg-[oklch(0.098_0.018_262)] p-5 font-mono text-sm leading-relaxed text-[oklch(0.853_0.025_237)]">
              <code>{block.code}</code>
            </pre>
          </div>
          {block.caption ? (
            <figcaption className="text-center font-mono text-xs text-muted-foreground">
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
            className="w-full rounded"
          />
          {block.caption ? (
            <figcaption className="text-center font-mono text-xs text-muted-foreground">
              {block.caption}
            </figcaption>
          ) : null}
        </figure>
      )

    case "callout":
      return (
        <aside
          className={cn(
            "rounded border-l-4 p-4",
            block.style === "warning" && "border-amber-500 bg-amber-500/8",
            block.style === "tip" && "border-primary bg-primary/8",
            (block.style === "info" ||
              !["warning", "tip"].includes(block.style)) &&
              "border-sky-500 bg-sky-500/8"
          )}
        >
          <div
            className={richText}
            dangerouslySetInnerHTML={{ __html: sanitize(block.content) }}
          />
        </aside>
      )

    default:
      return null
  }
}
