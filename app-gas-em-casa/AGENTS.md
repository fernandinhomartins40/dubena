# AGENTS.md

## Project Overview

Expo React Native mobile app using TypeScript.

## Stack

- Expo SDK: 53
- React Native: 0.79.5
- React: 19.0.0
- TypeScript
- Expo Router
- React Navigation
- TanStack React Query
- Zustand
- Axios
- React Hook Form
- Zod
- MMKV
- Firebase Messaging
- Expo Notifications
- iOS and Android support

---

### What Codex IS allowed to do

- Read and analyze all project files
- Reference existing code
- Suggest improvements based on real code
- Explain architecture using actual files
- Reuse existing patterns from the project
- Point to specific files and lines

---

## Expected Behavior

When responding:

1. ALWAYS analyze the existing codebase first
2. ALWAYS reference real project structure when possible
3. NEVER assume generic structure if code is available
4. NEVER ask user to paste code if it can be inferred

---

## Output Format (MANDATORY)

- Explanation (short)
- Files involved (real paths from project if known)
- What should be changed (clear instructions)
- Code snippet (final version only)

Use this format:

```txt
File: app/example.tsx
Action: Replace component
Reason: Adds the requested screen using existing project libraries.
```

## Atomic Design

This project uses Atomic Design.

Follow this structure when applicable:

```txt
components/
  atoms/              Smallest reusable UI pieces
  molecules/          Combinations of atoms
  organisms/          Larger composed sections
  templates/          Screen/layout-level structures
```
