# Inception Deck

This document defines the project vision, elevator pitch, and scope boundaries.
It ensures all team members and AI agents align on "why we are here" and "what we are NOT doing".

## 1. Why are we here?

To provide a high-quality, pre-configured development environment that reduces setup time and enforces best practices for Laravel + React (Inertia) applications.

## 2. Elevator Pitch

For developers who want to build modern web applications,
This template is a **starting point**
That provides a **fully integrated Laravel + React stack**
Unlike starting from scratch or using separate repositories,
Our product offers a **cohesive, dockerized, and opinionated setup**.

## 3. The NOT List (Out of Scope)

Defining what we will **NOT** do is crucial for focus.

- **NOT a CMS**: We focus on custom application logic, not generic content management.
- **NOT a Microservices Architecture**: Monolithic by default (easier to maintain).
- **NOT a Static Site Generator**: Dynamic application logic is the core.
- **NO Complex Authorization (Initially)**: Simple roles (User/Admin) suffice for MVP.
- **NO Third-party OAuth (Initially)**: Stick to email/password for simplicity.

## 4. Technical Constraints

- **Stack**: Laravel 12 (PHP 8.2+), React 19 (Inertia v2), Tailwind CSS v4.
- **Database**: MariaDB 10.11+ (MySQL compatible).
- **Environment**: Docker (Compose) based.

## Verification Checklist

- [ ] **Alignment**: Does the proposed feature align with the "Why"?
- [ ] **Scope Creep**: Is the feature on the "NOT List"? (If yes, reject or re-evaluate).
- [ ] **Simplicity**: Are we over-engineering for problems we don't have yet?