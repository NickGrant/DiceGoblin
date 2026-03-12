# UX Scene Review Loop

Use this loop for each requested scene.

## Per-Scene Sequence

1. Capture the current scene screenshot.
2. Review the screenshot using the user's criteria.
3. Apply a `Senior Developer` implementation pass.
4. Capture the updated scene again.
5. Review the updated screenshot using the `QA Lead` lens.
6. If issues remain, repeat the cycle.

## Default Limit

- Maximum 5 iterations per scene unless the user gives a different limit.

## Typical Criteria

- Text remains readable over textured or illustrated backgrounds.
- Titles, subtitles, panels, and buttons do not collide unintentionally.
- Containers have enough internal space for the content they present.
- Sparse scenes use available vertical and horizontal space intentionally instead of leaving awkward dead zones.
- Images and panel art keep their aspect ratio and do not look stretched.

## Reporting

At minimum, report:

- which screenshot iteration was accepted for each scene
- which issues were fixed
- whether any ambiguity remains that requires user input
