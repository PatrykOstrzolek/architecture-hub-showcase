# ADR-002: Sulu CMS as Primary Backend

## Status
Proposed

## Context
Our project requires a robust system to manage complex technical knowledge, including articles, categories, and tags. We need to avoid building a custom CMS from scratch and leverage existing, well-tested solutions.

## Decision
We will use Sulu CMS as the primary backend and source of truth for all content.

*   We will use Sulu's content management features to manage our article and taxonomy data.
*   We will use Sulu's headless capabilities to expose content via API (or direct data access if suitable).
*   We will prioritize built-in Sulu content types and features over developing custom content management logic.

## Consequences
### Positive
*   Leverages a proven, enterprise-ready CMS.
*   Simplifies content management for editors.
*   Reduces custom backend code for CRUD operations.

### Negative
*   Introduces dependency on the Sulu ecosystem.
*   Requires managing a PHP/Symfony-based backend in addition to the frontend stack.
