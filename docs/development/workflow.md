# Development Workflow

This document defines the spec-driven development workflow to ensure consistency, clarity, and effective AI-assisted development.

## 1. Principles of Spec-Driven Development
*   **Documentation First**: Every significant change must be preceded by an update to the relevant specification or architectural documentation (ADR, feature spec).
*   **Source of Truth**: The `docs/` folder is the primary source of truth. Implementation should be a direct reflection of these documents.

## 2. Iterative Workflow
1.  **Requirement Definition**: Update or create a feature specification in `docs/product/features/` or an ADR in `docs/architecture/adrs/`.
2.  **Implementation**:
    *   Review the specification to ensure understanding of the requirements and constraints.
    *   Develop the code, adhering to established architectural principles.
    *   Follow existing patterns and idioms in the codebase.
3.  **Verification**:
    *   Verify the implementation against the acceptance criteria defined in the feature specification.
    *   Run relevant tests.
4.  **Documentation Update**: If the implementation details evolve during development, update the relevant specification to reflect the final state.

## 3. Pull Request Conventions
*   **Context**: Include a reference to the relevant specification document in the PR description.
*   **Atomic Changes**: Keep PRs small and focused on a single feature or fix.
*   **Review**: Ensure changes align with the documented principles.

## 4. AI-Assisted Development
*   **Provide Context**: When starting a task, ensure the AI has access to the relevant specification documents.
*   **Verify Accuracy**: AI-generated code should be reviewed to ensure it follows project principles and guidelines.
*   **Consistency**: Always ask the AI to adhere to existing code patterns.
