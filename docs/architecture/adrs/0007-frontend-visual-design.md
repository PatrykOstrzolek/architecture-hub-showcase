# ADR-007: Frontend Visual Design Direction

## Status
Accepted

## Context
The initial frontend used shadcn/ui defaults with the `radix-mira` style preset and `mist` base colour. While functional, the result was a generic templated look indistinguishable from other shadcn-based projects. The audience — software architects and senior backend engineers — spends most of its day in terminals and IDEs, which informs what feels native rather than foreign to them.

## Decision
Adopt a deliberate visual identity built around three choices:

**Palette**
- Light background: cool blue-gray (`oklch(0.966 0.004 230)`) instead of pure white — cards on white lift from it.
- Dark background: deep navy-black (`oklch(0.098 0.018 262)`) — not the standard shadcn dark which reads as charcoal.
- Primary green: cooled and deepened from the original JetBrains preset (`oklch(0.575 0.168 133)` / `oklch(0.762 0.188 133)` dark) and used sparingly — interactive affordances only, never ambient decoration.
- Border radius tightened to `0.25rem` for a more precise, technical feel.

**Typography**
JetBrains Mono is used for all structural text: headings, eyebrow labels, navigation, metadata, footer. Body copy (article excerpts) stays in Geist Sans. This inverts the conventional "humanist sans for approachability, mono for code only" advice — for this audience mono feels native, not clinical.

**Homepage layout and signature element**
The homepage hero shows a small SVG system-architecture diagram (Client → API Gateway → Service, with Database dependency) as ambient decoration opposite the headline. This element is specific to the subject matter and illustrates what architecture means in the site's own visual grammar. The article listing uses a featured + sidebar layout (2/3 + 1/3) rather than a uniform grid, giving editorial weight to the most recent article.

**Article view**
- The category field is promoted to an eyebrow label (mono, green, uppercase, above the `<h1>`) rather than a badge beside body text.
- Author, date, and tags are collapsed into a single meta strip below the summary: small avatar + name + position + date + `#tag` mono badges — one line rather than stacked blocks.
- `h2`/`h3`/`h4` inside article body use `font-heading` (JetBrains Mono) so section headings stay consistent with the site's structural typography.
- Blockquote left border uses `border-primary/60` (green) instead of muted gray.
- Code blocks use a terminal-style header bar (three muted dots + green language label) with the design system's own dark OKLCH palette values (`oklch(0.123 0.022 262)` bar / `oklch(0.098 0.018 262)` pre) — consistent with dark-mode surface tokens rather than hardcoded zinc.
- "Keep reading" replaces shadcn Card stacks with a divide-separated editorial list: title + one-line excerpt + `→` arrow. No card chrome.
- Section labels ("WRITTEN BY", "CONTINUE READING") use the same `text-[10px] uppercase tracking-widest text-muted-foreground font-mono` pattern established by the homepage eyebrows.

## Alternatives Considered
- **Keep shadcn defaults** — zero effort but indistinguishable from any other shadcn project.
- **Dark hero with acid-green accent** — thematically adjacent to "developer tool" but a well-worn AI-generated pattern; rejected.
- **Warm cream + high-contrast serif** — inappropriate for a technical audience; rejected.

## Consequences
### Positive
- Site has a recognisable identity without requiring custom component work beyond CSS tokens.
- Mono-for-structure is unconventional but defensible for this audience and reinforces the "precision" quality of architectural thinking.
- The SVG diagram signature element is content-derived, not decorative — it can evolve (e.g. become interactive) without a redesign.

### Negative
- Tighter radius (`0.25rem`) makes buttons and inputs feel less soft; acceptable trade-off for the target audience.
- All-mono structural text may need revisiting if the audience broadens beyond technical readers.
