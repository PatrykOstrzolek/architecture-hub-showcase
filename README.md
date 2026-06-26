# Architecture Hub Showcase

This project is a headless knowledge platform focused on software architecture, system design, and DevOps.

## Documentation
The project is spec-driven. Please refer to the `docs/` directory for detailed specifications:
- `docs/business/`: Project vision and strategy.
- `docs/product/`: Product specifications and feature details.
- `docs/architecture/`: Architectural principles and ADRs.
- `docs/development/`: Workflow and coding standards.
- `docs/operations/`: Infrastructure and deployment information.

## Setup
This project uses `mise` for tool version management.

### Prerequisites
- [mise](https://mise.jdx.dev/)

### Initial Setup
Ensure your environment is set up by running:
```bash
mise install
```

### Infrastructure
Local development uses Docker and Colima. Ensure they are running before starting the application:
```bash
colima start
```

### Command Execution Rule
Every `sulu`, `composer`, `php`, `npm`, and `npx` command will be run by the user. The assistant will receive the available log.
