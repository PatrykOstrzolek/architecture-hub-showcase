import Link from "next/link"
import { mediaUrl } from "@/lib/sulu"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Card, CardContent } from "@/components/ui/card"
import { articleHref, type AuthorContent } from "./types"

function initials(name: string): string {
  return name
    .split(" ")
    .map((w) => w[0])
    .join("")
    .toUpperCase()
    .slice(0, 2)
}

export function AuthorView({ content }: { content: AuthorContent }) {
  const articles = content.articles ?? []

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      {/* Back */}
      <div className="mb-8">
        <Link
          href="/"
          className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
        >
          ← All articles
        </Link>
      </div>

      {/* Profile header */}
      <header className="mb-10 flex items-start gap-6">
        <Avatar className="size-20 shrink-0">
          {content.photo ? (
            <AvatarImage
              src={mediaUrl(content.photo.url)}
              alt={content.title}
            />
          ) : null}
          <AvatarFallback className="text-2xl">
            {initials(content.title)}
          </AvatarFallback>
        </Avatar>
        <div className="space-y-2 pt-1">
          <h1 className="text-3xl font-bold tracking-tight">{content.title}</h1>
          {content.position ? (
            <p className="text-base text-muted-foreground">
              {content.position}
            </p>
          ) : null}
          {content.bio ? (
            <p className="mt-3 leading-7 text-muted-foreground">
              {content.bio}
            </p>
          ) : null}
        </div>
      </header>

      <div className="border-t" />

      {/* Articles */}
      <section className="mt-10">
        <h2 className="mb-6 text-xl font-semibold">
          Articles
          {articles.length > 0 ? (
            <span className="ml-2 text-base font-normal text-muted-foreground">
              ({articles.length})
            </span>
          ) : null}
        </h2>

        {articles.length === 0 ? (
          <p className="text-sm text-muted-foreground">No articles yet.</p>
        ) : (
          <ul className="space-y-4">
            {articles.map((item) => (
              <li key={item.id}>
                <Link href={articleHref(item)} className="group block">
                  <Card className="transition-shadow group-hover:shadow-md group-hover:ring-1 group-hover:ring-foreground/20">
                    <CardContent className="py-4">
                      <h3 className="font-semibold group-hover:underline">
                        {item.content.title}
                      </h3>
                      {item.content.summary ? (
                        <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                          {item.content.summary}
                        </p>
                      ) : null}
                    </CardContent>
                  </Card>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
