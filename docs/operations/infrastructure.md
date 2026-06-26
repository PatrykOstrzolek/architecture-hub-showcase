# Infrastructure

This document outlines the infrastructure constraints and design for the Architecture Hub Showcase project.

## 1. Environment
*   **Version Management**: The project uses [mise](https://mise.jdx.dev/) for managing tool versions (PHP, Node.js, etc.).
*   **Containerization**: Docker is used for local development, with [Colima](https://github.com/abiosoft/colima) as the container runtime.
*   **OS/Environment**: The development environment is MacOS.

## 2. Dependencies
*   **PHP**: Sulu CMS requires a compatible PHP version (refer to `mise.toml`).
*   **CMS**: Sulu CMS (Symfony-based).
*   **Frontend**: Next.js (App Router).

## 3. Constraints
*   **Memory Management**: Due to PHP's default memory limits, heavy operations (composer install, cache clear/warmup) must be run with increased memory limits (`php -d memory_limit=1G`).
*   **Headless**: The infrastructure must support headless CMS patterns where the backend (Sulu) and frontend (Next.js) communicate via API.
