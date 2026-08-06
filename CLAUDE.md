# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quality gate

**All gates MUST pass before any task is marked complete. No exceptions.**

- `composer format` — auto-fixes PHPCS violations (must run before lint)
- `composer lint` — must exit with zero errors; fix all errors and re-run until clean

If a step fails: fix the issue, then re-run from that step.
