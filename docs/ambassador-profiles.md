# Ambassador profiles and visual assets

## Release scope

Preserve the original PHP/Bootstrap UI at commit 57d6e82, including login markup, labels, controls and layout. Inter is used for body content with self-hosted normal/italic variable fonts and Vietnamese characters. The eAmbassador wordmark retains its original Syne font. Phosphor replaces interface glyphs; CMC brand artwork is unchanged. No layout redesign or theme switch. Detailed profile content is added only within the existing profile surface, with its linked admin editor.

## Deployment

Run from the web root after pulling this release:

```sh
git pull origin main
php tools/seed-ambassadors.php --apply
```

The CLI checks portrait files, backs up SQLite under tmp, and inserts 12 profiles once. It does not overwrite existing people, edited biographies, chats, points or ratings. Apache and the local router deny direct HTTP access to data/tmp. Keep the backup private. New fictional accounts use reserved example.invalid email addresses and random passwords; they are not staffed accounts. Do not enable them on a live admissions service without replacing them with authorized ambassadors.

No sample badges are rendered, as requested. Internal sample_key records support idempotent seeding. Details are editable under Admin > Đội ngũ đại sứ > Sửa hồ sơ. The public directory, widget, inbox avatars and AI recommendation context use linked user IDs. Existing accounts without profile details keep their current biography and initials.

## Portrait provenance

Generated with the built-in ImageGen tool, generation mode, 12 independent requests, no reference photographs. Final assets: assets/img/ambassadors/portrait-01.png through portrait-12.png. These are fictional adult portraits, not photographs of real students. Full-size PNG originals are retained, with lazy loading and fixed render dimensions; no production network-performance score is claimed.

Shared final prompt (subject inserted below):

> Use case: photorealistic-natural. Asset type: individual square avatar for a fictional Vietnamese university student ambassador profile. Subject: [subject]. Exactly ONE fictional adult person, chest-up, head centered with generous room around hair for circular crop, camera eye level. Natural daylight, real skin texture and small imperfections, everyday student look, believable candid portrait taken by a friend with a 50mm lens. No glamour retouching, no stock-photo business posing, no brand logos, no text, no watermark, no collage. Create a distinct individual, not a real person. Square image.

- portrait-01.png: Vietnamese woman age 21, shoulder-length straight black hair, light blue cotton shirt, soft genuine smile, campus library background
- portrait-02.png: Vietnamese man age 22, short textured black hair, thin round glasses, navy polo, relaxed smile, leafy campus courtyard
- portrait-03.png: Vietnamese woman age 20, black hair in a low ponytail, cream cardigan, candid cheerful expression, bright studio classroom
- portrait-04.png: Vietnamese man age 21, wavy black hair, white shirt over gray tee, warm tan complexion, open smile, campus walkway
- portrait-05.png: Vietnamese woman age 22, short black bob, oval glasses, muted green blouse, thoughtful friendly expression, library window
- portrait-06.png: Vietnamese man age 20, close-cropped black hair, beige overshirt, broad smile, outdoor university garden
- portrait-07.png: Vietnamese woman age 21, long slightly wavy dark hair, navy blouse, natural smile, shaded campus trees
- portrait-08.png: Vietnamese man age 23, side-parted black hair, rectangular glasses, blue linen shirt, calm expression, modern study room
- portrait-09.png: Vietnamese woman age 20, chin-length layered hair, white T-shirt and denim jacket, bright smile, campus cafe background
- portrait-10.png: Vietnamese man age 22, gently curly short hair, muted green polo, tan skin, friendly expression, library books out of focus
- portrait-11.png: Vietnamese woman age 23, long black hair tucked behind one ear, pale blue blouse, subtle smile, sunny campus corridor
- portrait-12.png: Vietnamese man age 21, medium-length black hair parted naturally, charcoal crewneck, easy smile, tree-lined campus

## Verification

- Profile unit suite: 23 checks, including idempotency, eligibility, privacy and preservation of edits.
- API integration: 43 checks, including admin profile save reaching widget and student edit denial (isolated QA database).
- Icon coverage: 120 aliases and 186 local SVG assets.
- Chat privacy: 11 checks.
- Local visual review: profile at desktop and 390px, Inter computed family, loaded portrait, no horizontal overflow.
- All 12 generated portraits visually inspected. Production Apache behavior and real-device Windows rendering require hosting/device verification.
