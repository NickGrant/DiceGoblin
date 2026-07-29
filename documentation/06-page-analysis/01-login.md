# Login Page Analysis
----

Status: active  
Last Updated: 2026-07-29  
Owner: UX + Engineering  
Depends On: `documentation/06-page-analysis/00-index.md`, `documentation/03-ux/08-page-layout-zones.md`  


Route: `/login`  
Auth: guest-only  
Component: `LandingPageComponent`

## UX Pieces

- Global guest HUD with login action and limited navigation.
- Large landing hero frame with logo art.
- Eyebrow label for `Dice Goblins`.
- Main heading `Report for Duty`.
- Short value proposition copy about rallying the warband and preparing for raids.
- Local registration and sign-in form.
- Alternate CTA to sign in through Discord.
- Secondary CTA linking to the public guide.

## Data Displayed

- Local account form state.
- Discord login URL from `discordLoginUrl`.
- Static marketing copy and static logo image.
- No player profile, run, roster, or inventory data is shown in the page body.

## Notes

- This page is the public landing route.
- Authenticated users are redirected away by the guest guard.
- Local auth exists so restricted networks that block Discord can still enter the game.
