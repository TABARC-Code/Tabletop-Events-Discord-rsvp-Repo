# Tabletop Events Calendar — Discord RSVP

Right, so. People RSVP to things on their phone, mid-conversation, without leaving Discord. That's the whole pitch. A button turns up under the event announcement, somebody taps it, a little form pops up, they type their name and email, and they're on the list. No new tab, no "hang on let me find the link", none of that.

This is a companion plugin. It does precisely nothing by itself — it needs [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) installed and active first, because that's where events, RSVP capacity, waitlists and the actual confirmation emails all live. This repo is just the Discord end of the wire.

Newbie? Skip straight to [IDIOTS-GUIDE.md](IDIOTS-GUIDE.md). It's short on purpose.

## What it actually does

1. You publish an event with RSVP capacity switched on.
2. This plugin posts a message to your Discord channel — an embed, and an RSVP button — via the bot you set up.
3. Someone clicks it. Discord opens a proper little form right there in the app: name, email.
4. They submit it, and this plugin quietly hands that off to the core plugin's own RSVP endpoint, in-process, no round trip. Capacity, waitlisting, the "you're confirmed" email — all of it runs exactly as it would if they'd filled the form in on the website, because as far as the core plugin's concerned, that's exactly what happened.
5. They get a private reply in Discord telling them where they stand. Nobody else in the channel sees it.

That's it. It's a doorbell, not a whole intercom system.

## Why a button and not an actual reaction

I'd love to tell you this posts an emoji and reads reactions back off the message, because that's the dream version of this feature. It doesn't, and here's the honest reason: a proper Discord reaction only exists as an event on Discord's Gateway, which is a permanent, always-open connection. WordPress doesn't do "always open" — every request starts cold, does its bit, and dies. Keeping a socket open between page loads on ordinary WordPress hosting is not a thing that happens, not without bolting on a separate process that has to live somewhere, forever, being looked after by someone.

Buttons and forms are different. They're plain HTTPS. Discord fires a POST at a URL you give it, gets an answer, done — same shape as literally every other webhook this ecosystem already uses. So that's what this is: one more REST endpoint on your existing WordPress install, verified by Discord's own signature rather than anything WordPress does natively.

Trade-off, stated plainly: it's a click, not a reaction. In return you get zero extra servers, zero bots to restart at 3am, and one less thing that can quietly stop working while nobody's looking.

## Setup, longer version

Full walkthrough's in [IDIOTS-GUIDE.md](IDIOTS-GUIDE.md). Short version:

- Create a Discord Application (free, discord.com/developers/applications), give it a Bot, invite the bot to your server.
- Copy the Bot Token and the Public Key into **Events Calendar → Discord RSVP** in wp-admin.
- Tell Discord where to send interactions — the settings page shows you the exact URL to paste in.
- Publish an event with RSVP on. Done.

## Things worth knowing before you rely on this

- **The bot token is not shown back to you once saved.** Same as any sensible plugin handling a credential — you replace it, or clear it, you don't get to look at it again through the UI. If you've lost it, generate a new one in Discord and paste that in instead.
- **Every interaction is signature-checked before anything else happens.** Discord signs the request; we verify it with the public key you gave us. If that check fails, we send back a 401 and stop right there — no partial processing, no "well it's probably fine".
- **Duplicate sends are guarded against.** WordPress is known to fire the publish transition more than once for what is, from your point of view, one publish click. A fingerprint stops that turning into two separate Discord messages.
- **Mentions are switched off on everything this plugin posts.** An event title is user input. User input that happens to contain the string "@everyone" is not something you want pinging your entire server by accident, so it can't.
- **There is no second copy of your RSVP list hiding in here.** This plugin holds no attendee data of its own. It calls the core plugin's real RSVP endpoint and that's where the record lives, same as it always did. One list, one source of truth, regardless of which door someone walked in through.

## What this deliberately doesn't do

- No live-updating the Discord message with a running headcount. Nice idea, more moving parts than it's worth for a v1.
- No slash commands, no bot presence, no "the bot is typing…" theatre. It's not trying to be a personality.
- No re-implementing anything the core plugin already does properly. If you want to know how RSVP capacity or waitlisting actually works, that logic lives over there, not here, and it's staying there.

If none of that's enough and you want the actual emoji-reaction, always-on-bot version — that's a genuinely different project, with genuinely different hosting requirements, and it isn't this one.

## Related repos

- [`tabletop-gaming-events-calendar`](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) — the core plugin. Required, not optional.
- [`WP-Discord-Autoposter-V2`](https://github.com/TABARC-Code/WP-Discord-Autoposter-V2) — the simpler one-way version of "post to Discord when something's published", for when you don't need any of this and just want a quiet announcement. A few of the harder-won lessons in that one (mentions, duplicate sends, logging failures instead of eating them) got carried over here and into the core plugin's own webhook code, because there wasn't any good reason to learn them twice.

## Other pieces of the set

Every plugin here stands alone and only ever needs the [core calendar](https://github.com/TABARC-Code/Tabletop-Gaming-Events-Calendar) — none depend on each other. Take what's useful:

- [Tabletop-Events-Reviews](https://github.com/TABARC-Code/Tabletop-Events-Reviews) — RSVP-gated event and organiser reviews
- [Tabletop-Events-Carpool](https://github.com/TABARC-Code/Tabletop-Events-Carpool) — Lift-share board per event
- [tabletop-events-resources](https://github.com/TABARC-Code/tabletop-events-resources) — Terrain, tables and dice to borrow
- [tabletop-events-tournaments](https://github.com/TABARC-Code/tabletop-events-tournaments) — Swiss pairings and standings

## Licence

GPL v2 or later. Do what you like with it, same as everything else here.
