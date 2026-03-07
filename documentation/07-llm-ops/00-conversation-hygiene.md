# Managing Long Conversations Without Idea Bleed

Status: active  
Last Updated: 2026-03-07  
Owner: Product + LLM Ops  
Depends On: `AGENTS.md`, `documentation/ACTIVE_CONTEXT.md`

When using a single long-running conversation for development work, it is important to prevent ideas from mixing together unintentionally. The following structure helps maintain clarity.

---

## 1. Use Explicit Topic Headers

Every new discussion should begin with a clearly labeled topic block.

Example:

TOPIC: Home Button Design

Goal:
Design the main navigation button for the home scene.

Constraints:
- propaganda style
- stencil aesthetic
- readable at small sizes

Topic headers help the model understand that a new subject is starting.

---

## 2. Close Topics Explicitly

When a discussion is finished, mark it as closed.

Example:

TOPIC CLOSED: Home Button Design

Final decision:
Stencil icon without text label.

Closing a topic signals that future responses should not modify that decision unless explicitly reopened.

---

## 3. Compare Alternatives Clearly

When discussing competing ideas, isolate them.

Example:

Comparison: Button Style A vs Button Style B

Style A:
- circular
- stencil icon
- red background

Style B:
- square
- framed metal border
- darker palette

Separating alternatives prevents the model from unintentionally merging them.

---

## 4. Record Final Decisions

Add a short decision record after each design decision.

Example:

DECISION RECORD

Feature: Home Button  
Chosen approach: stencil icon  
Reason: matches propaganda aesthetic and remains readable at small sizes

Decision records act as anchors that stabilize later discussion.

---

## 5. Maintain a Session Index

At the top of the conversation, keep an index of topics.

Example:

SESSION INDEX

1 — Home Button Design  
2 — Unit Frame Style  
3 — Exploration Node Icons  
4 — Combat UI Layout

This allows quick reference and helps avoid reopening old topics accidentally.

---

# Managing Image Generation in Long Conversations

Image generation can degrade when a conversation accumulates too many prior prompts and conflicting style descriptions.

---

## Use Image Reset Blocks

Before starting a new image task, reset the image context.

Example:

IMAGE GENERATION RESET

Ignore previous image prompts.

Generate images using only the following instructions:

Goal:
Design encounter icons.

Icons required:
- combat
- rest
- loot
- boss

This prevents older prompts from influencing new outputs.

---

## Use a Consistent Style Template

Define a reusable visual style template.

Example:

STYLE TEMPLATE

theme: goblin propaganda  
style: stencil poster  
texture: worn paint  
palette: muted military tones  

Using the same template across prompts improves visual consistency.

---

## Treat Each Image Request as a New Brief

Even in a long conversation, structure image prompts as standalone design briefs.

Example:

IMAGE TASK

Goal:
Create six encounter icons.

Icons:
- combat
- rest
- loot
- boss
- locked
- unlocked

Style:
Goblin propaganda aesthetic.

This ensures the model focuses on the current task rather than previous prompts.

---

## General Guideline

Use conversations as **temporary working spaces**, while recording important outcomes in project documentation. This prevents long discussions from becoming the only source of project knowledge.
