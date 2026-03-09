# Dice Goblins — Master Context Document

Status: reference  
Last Updated: 2026-03-08  
Owner: Product + Engineering  
Depends On: `documentation/00-overview/00-project-overview.md`, `documentation/01-architecture/00-tech-stack.md`

## Project Overview

Dice Goblins is a **web-hosted strategy game** where the player commands a goblin warband navigating dangerous exploration runs and resolving encounters through a **dice-driven tactical combat system**.

The player manages units, equips them with dice, and progresses through branching exploration paths. Each run involves a cycle of encounters that consume resources and reward progression.

The project is currently focused on building a **core gameplay prototype** demonstrating the main loop of exploration, combat, and warband management.

---

## Technology Stack

### Frontend
- TypeScript
- Phaser
- HTML5 Canvas

Responsibilities:
- scene rendering
- UI layout
- player input handling
- animation and feedback

---

### Backend
- PHP

Responsibilities:
- authoritative game logic
- combat resolution
- rules validation
- API endpoints

---

### Database
- SQL relational database

Responsibilities:
- player data
- unit ownership
- dice inventory
- run state persistence

---

## Architectural Principles

### Backend Authority
All authoritative game logic should live in the backend.

Examples:
- combat resolution
- encounter results
- progression and rewards

The frontend should **never be trusted to determine gameplay outcomes**.

---

### Frontend as Presentation Layer

The frontend is responsible for:

- rendering scenes
- presenting UI
- collecting player decisions
- sending actions to the backend

---

### Data-Driven Systems

Gameplay entities should be defined through **data structures rather than hardcoded logic** wherever possible.

Examples:
- UnitType definitions
- DiceType definitions
- encounter configurations

This allows content expansion without rewriting core logic.

---

## Core Gameplay Loop
Prepare Warband
↓
Start Exploration Run
↓
Traverse Node Map
↓
Encounter Event
↓
Resolve Combat or Interaction
↓
Gain Rewards
↓
Return to Warband Management


Each loop strengthens the player's warband and unlocks additional gameplay options.

---

## Major Systems

### Exploration System

Players navigate a **node-based map** representing the current run.

Nodes may contain:

- combat encounters
- rest points
- loot opportunities
- special events
- boss encounters

Exploration decisions determine risk, reward, and resource management.

---

### Combat System

Combat is resolved using **dice equipped by units**.

Key characteristics:

- dice represent abilities or actions
- rolling outcomes affect combat results
- dice may be consumed, exhausted, or modified

Combat should emphasize **tactical decision-making** rather than pure randomness.

---

### Warband Management

Between runs the player manages their warband.

Management includes:

- viewing units
- equipping dice
- reorganizing unit composition
- upgrading or improving units

This phase represents strategic preparation.

---

### Progression

Progression systems include:

- unit leveling
- unlocking new dice
- increasing warband capacity
- unlocking exploration regions

Progression should introduce new strategies over time.

---

## Core Data Entities

Key entities in the data model include:

Player  
Represents the user account.

Run  
Represents a single exploration attempt.

Node  
Represents a location on the exploration map.

Encounter  
Represents an event triggered at a node.

UnitType  
Defines a class of unit.

UnitInstance  
A specific owned unit belonging to a player.

DiceType  
Defines a category of dice.

DiceInstance  
A specific owned die.

Inventory  
Tracks owned resources and equipment.

---

## Visual Direction

Dice Goblins uses a **harsh handmade propaganda diorama aesthetic** for its UI and visual framing.

Visual influences include:

- authoritarian wartime bureaucracy
- constructed tabletop paper-craft
- distressed analog production marks (ink bleed, stamp spread, adhesive wear)
- stencil-forward command labeling and registry surfaces

The tone should communicate:

- militant organization
- tactical oppression
- improvised but severe goblin administration

---

## UI Design Philosophy

The interface should resemble a **command console for a goblin warband commander**.

UI characteristics:

- bold iconography
- strong framing elements
- clear hierarchy
- minimal clutter

The interface should feel **functional and militaristic**, not playful.

---

## Major Screens

### Home Scene

Purpose:
Main entry point to the game.

Contains:

- title presentation
- start run option
- warband management access

---

### Warband Management Screen

Purpose:
Manage units and equipment.

Contains:

- unit roster
- dice equipment slots
- dice inventory

---

### Exploration Map

Purpose:
Navigate through encounters during a run.

Contains:

- node grid
- branching paths
- encounter icons

---

### Combat Scene

Purpose:
Resolve combat encounters.

Contains:

- player unit row
- enemy units
- dice interaction interface
- action resolution feedback

---

## Design Principles

1. Mechanics should be **intuitive but strategically deep**.
2. Dice mechanics should enable meaningful tactical decisions.
3. UI must remain readable even with multiple units and dice in play.
4. Systems should scale easily as additional content is added.

---

## Current Milestone

**Milestone 1 — Core Gameplay Prototype**

Goals:

- exploration node traversal
- basic combat encounters
- dice consumption mechanics
- warband management interface

The focus is on **functional gameplay systems rather than final polish**.
