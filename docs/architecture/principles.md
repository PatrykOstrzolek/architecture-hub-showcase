# Architectural Principles

These principles guide our technical decisions and ensure consistency across the project. They prioritize simplicity, maintainability, and alignment with our vision.

## 1. Core Engineering Principles
*   **Simplicity over Complexity**: Prefer simple, readable solutions over clever or complex abstractions.
*   **Avoid Premature Abstractions**: Introduce abstractions only when the need becomes clear and recurring.
*   **Explicit over Implicit**: Prefer explicit code paths that are easy to reason about.
*   **Justified Dependencies**: Do not introduce new dependencies unless they provide significant value and reduce development effort or maintenance risk.

## 2. Sulu CMS Integration
*   **Source of Truth**: SULU CMS is the primary source of truth for content.
*   **Built-in Capabilities First**: Always leverage built-in SULU capabilities before resorting to custom solutions.
*   **Minimal Customization**: Avoid creating custom APIs unless explicitly required by unique business needs.

## 3. Frontend Principles
*   **TypeScript**: All frontend code must be written in TypeScript to ensure type safety.
*   **React Server Components (RSC)**: Prefer RSC by default for data fetching and rendering.
*   **Client Components for Interactivity**: Use Client Components sparingly, only where interactivity is strictly required.
*   **Styling**: Use Tailwind CSS and shadcn/ui for consistent design.
*   **Feature-based Organization**: Keep code organized around features rather than technical layers.

## 4. Documentation & Maintenance
*   **Specs as Source of Truth**: Implementation must strictly follow defined specifications.
*   **ADRs for Major Decisions**: Document significant architectural choices in Architecture Decision Records (ADRs).
*   **Small, Focused Documents**: Favor smaller, purpose-driven documents over large monolithic specifications.
