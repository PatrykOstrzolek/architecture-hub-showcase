import type { PageContent } from "./types";

export function PageView({ content }: { content: PageContent }) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <h1 className="mb-6 text-4xl font-bold tracking-tight">{content.title}</h1>
      {content.article ? (
        <div
          className="leading-7 [&_p]:mb-4 [&_a]:underline"
          dangerouslySetInnerHTML={{ __html: content.article }}
        />
      ) : null}
    </div>
  );
}
