import sanitizeHtml from "sanitize-html"

const OPTIONS: sanitizeHtml.IOptions = {
  allowedTags: [
    "h2", "h3", "h4",
    "p", "br", "strong", "em", "b", "i", "u", "s",
    "a", "ul", "ol", "li",
    "blockquote", "code", "pre",
  ],
  allowedAttributes: {
    a: ["href", "target", "rel"],
  },
  allowedSchemes: ["http", "https", "mailto"],
  transformTags: {
    a: sanitizeHtml.simpleTransform("a", { rel: "noopener noreferrer" }),
  },
}

export function sanitize(html: string): string {
  return sanitizeHtml(html, OPTIONS)
}
