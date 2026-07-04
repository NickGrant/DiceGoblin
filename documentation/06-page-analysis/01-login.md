# Login Page Analysis

Route: `/login`  
Auth: guest-only  
Component: `LandingPageComponent`

## UX Pieces

- Global guest HUD with login action and limited navigation.
- Large landing hero frame with logo art.
- Eyebrow label for `Dice Goblins`.
- Main heading `Report for Duty`.
- Short value proposition copy about rallying the warband and preparing for raids.
- Primary CTA to sign in through Discord.
- Secondary CTA linking to the public guide.

## Data Displayed

- Discord login URL from `loginUrl`.
- Static marketing copy and static logo image.
- No player profile, run, roster, or inventory data is shown in the page body.

## Notes

- This page is the public landing route.
- Authenticated users are redirected away by the guest guard.
