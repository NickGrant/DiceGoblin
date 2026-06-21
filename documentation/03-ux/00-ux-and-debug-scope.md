# UX & Debug Scope - Current Alpha

Status: active  
Last Updated: 2026-06-21  
Owner: UX + Frontend  
Depends On: `documentation/00-overview/00-project-overview.md`, `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `documentation/03-ux/03-encounter-flow-transition-matrix.md`

## Purpose

- Define the player-facing screen surface that exists today.
- Keep the current alpha UX grounded in real routes and real game actions.
- Separate current product behavior from older speculative UX plans.

## Current Screen Surface

Public:

- login
- guide

Authenticated:

- home
- field guide
- regions
- warband
- squad details
- unit details
- dice inventory
- shop
- academy
- run map
- run node
- run rest
- run summary
- debug when runtime-enabled

## Current UX Priorities

- the game should feel like a persistent game shell instead of separate website pages
- the player should always know whether they are preparing, traversing a run, resolving a node, or reviewing results
- primary actions should stay obvious on mobile and desktop
- battle outcomes should be readable even though the player is not manually controlling combat
- progression decisions such as promotions and capstones should be understandable without external docs

## Current Navigation Rules

- home is the main between-run hub
- starting a new run goes through regions
- continuing an active run goes straight back to the run map
- academy is feature-gated
- rest uses a dedicated route instead of the general node page
- all terminal run outcomes resolve through the shared run summary route

## Current Information Requirements

- home must surface energy, currency, and start-or-continue run intent
- warband must surface squads and units clearly enough to choose the next run lineup
- unit details must explain loadout, dice slotting, progression, and promotion state
- academy must explain unlocks, promotion destinations, and capstone requirements
- run map must show node availability and current squad condition
- run node must explain what happened, not just whether the player won
- run summary must show rewards and progression clearly enough to motivate the next run

## Debug Scope

The debug route may expose:

- currency grants
- unit grants
- dice grants
- region item grants
- direct unit level changes
- account reset

These tools exist to support testing and balancing, not normal player progression.

## Current Non-Goals

- direct manual battle control
- public-facing multiplayer features
- hidden nested management flows that bypass the main shell
- production cheat surfaces
