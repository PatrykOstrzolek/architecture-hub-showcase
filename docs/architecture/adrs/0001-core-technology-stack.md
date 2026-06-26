# ADR-001: Core Technology Stack

## Status
Proposed

## Context
We need a robust, maintainable technology stack that supports our vision of a headless architecture, high-performance content delivery, and rapid development of technical knowledge features.

## Decision
We have decided to adopt the following core stack:

*   **Backend**: PHP with Sulu CMS (leveraging its headless capabilities).
*   **Frontend**: TypeScript with React (using Next.js App Router for Server Components).
*   **Styling**: Tailwind CSS and shadcn/ui.
*   **Environment**: Mise for tool version management, Docker/Colima for containerization.

## Consequences
### Positive
*   Consistent development experience with typed frontend and robust backend.
*   Strong content management capabilities provided by Sulu.
*   Modern, performant frontend with React Server Components.
*   Reproducible environment with Mise and Docker.

### Negative
*   Initial learning curve for the specific stack combinations (Sulu + Next.js).
*   Need to maintain container infrastructure.

