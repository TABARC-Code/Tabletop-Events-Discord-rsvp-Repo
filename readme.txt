=== Tabletop Events Calendar — Discord RSVP ===
Contributors: tabarccode
Tags: events, calendar, tabletop, discord, rsvp
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

RSVP to Tabletop Events Calendar events straight from Discord — a button + native Discord form, no persistent bot process. Requires the Tabletop Events Calendar plugin.

== Description ==

When an event with RSVPs open is published, this plugin posts a richer Discord announcement than the core plugin's plain webhook: an embed plus an "RSVP" button. Click it, and Discord pops up a small native form (name + email) right there in the app — submit it, and you're RSVP'd on the website, capacity/waitlist and all, without ever leaving Discord.

This is the core plugin: https://github.com/TABARC-Code/tabletop-gaming-events-calendar — install and activate it first, this plugin does nothing without it.

= Why buttons, not reactions? =

A literal emoji reaction on a Discord message only fires over Discord's Gateway — a persistent, always-on WebSocket connection that a normal WordPress install can't hold open between page loads. Discord's buttons and forms ("message components" and "modals") work over plain HTTPS instead: Discord POSTs to a URL you register when someone clicks, no bot process to host anywhere. This plugin is that URL — one more REST route on your existing WordPress install, verified by Discord's own request signature rather than a WordPress login.

The trade-off: it's a button click, not a literal emoji reaction. In exchange, there's no bot process to keep running, no separate server to pay for or patch, and it lives and dies with the rest of your WordPress plugins.

== Installation ==

1. Install and activate **Tabletop Events Calendar** first.
2. Upload the `tabletop-events-discord-rsvp` folder to `/wp-content/plugins/` and activate it.
3. Go to **Events Calendar ▸ Discord RSVP** and follow the one-time Discord Developer Portal setup steps shown there (create an application + bot, invite it to your server, paste in the bot token / public key / channel ID, and paste the shown Interactions Endpoint URL into Discord's dashboard).
4. Publish an event with RSVP capacity set — the next time it goes live, the Discord announcement includes the button.

== Frequently Asked Questions ==

= Does this replace the core plugin's Discord/Slack webhook? =

Not automatically — they're independent. If both are configured you'll get two messages per event (a plain one from the core plugin, a richer one with an RSVP button from this plugin). Most sites will want one or the other.

= What does Discord see when someone RSVPs? =

Only what the person types into the form Discord shows them (name + email) — this plugin has no access to their Discord account's real email address, which Discord never exposes to any bot or application.

= Does this need a bot running somewhere? =

No. See "Why buttons, not reactions?" above — everything happens over plain HTTPS requests to this plugin's own REST route, handled by your normal WordPress hosting like any other page load.

== Changelog ==

= 1.0.0 =
* Initial release: Discord Bot API announcer with an RSVP button, an Interactions Endpoint verifying Discord's Ed25519 request signature, a native Discord modal for name/email capture, and an internal call into the core plugin's own RSVP REST route so capacity/waitlist/confirmation-email logic lives in exactly one place.
