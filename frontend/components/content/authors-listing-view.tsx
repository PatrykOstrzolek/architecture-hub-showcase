import Link from "next/link";
import { Card, CardContent } from "@/components/ui/card";
import type { AuthorsListingContent } from "./types";

export function AuthorsListingView({ content }: { content: AuthorsListingContent }) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <h1 className="mb-10 text-3xl font-bold tracking-tight">{content.title}</h1>

      {content.authors.length === 0 ? (
        <p className="text-sm text-muted-foreground">No authors yet.</p>
      ) : (
        <ul className="space-y-4">
          {content.authors.map((author) => (
            <li key={author.id}>
              <Link href={author.content.url ?? "#"} className="group block">
                <Card className="transition-shadow group-hover:shadow-md group-hover:ring-1 group-hover:ring-foreground/20">
                  <CardContent className="py-5">
                    <h2 className="font-semibold group-hover:underline">
                      {author.content.title}
                    </h2>
                    {author.content.position ? (
                      <p className="mt-0.5 text-sm text-muted-foreground">
                        {author.content.position}
                      </p>
                    ) : null}
                    {author.content.bio ? (
                      <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">
                        {author.content.bio}
                      </p>
                    ) : null}
                  </CardContent>
                </Card>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
