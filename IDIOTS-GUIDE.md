# Idiot's guide

Assumes you've never touched the Discord developer site before. That's fine. Everyone starts there.

## 0) Before you start

You need:
- Tabletop Events Calendar already installed and active. This plugin refuses to do anything without it.
- Admin access to a Discord server you're allowed to add bots to.
- Ten minutes and a working mouse.

## 1) Make a Discord Application

Go to [discord.com/developers/applications](https://discord.com/developers/applications).

Click **New Application**. Name it whatever — "Events Bot" is fine, nobody's grading you on this.

## 2) Give it a Bot

Left sidebar → **Bot** → **Add Bot** (or it might already be there, Discord's UI moves about).

Under that same page, find **Reset Token** (or **Copy**, if it hasn't been reset before) and grab the token. This is the only time you'll easily see it — paste it somewhere temporary if you're not going straight into WordPress next.

Treat this token exactly like a password. Because it is one.

## 3) Invite the bot to your server

Left sidebar → **OAuth2** → **URL Generator**.

Tick **bot** under scopes. Under bot permissions, tick **Send Messages** (and **Embed Links** while you're there). Copy the generated URL at the bottom, paste it into a browser, pick your server, authorise it.

Your bot is now sat in the server doing absolutely nothing. That's expected.

## 4) Get the Public Key

Left sidebar → **General Information**. Copy the **Public Key** field. It's a long string of letters and numbers. Copy the whole thing.

## 5) Get the Channel ID

In Discord itself: **User Settings → Advanced → Developer Mode**, switch it on.

Right-click the channel you want announcements in → **Copy Channel ID**.

## 6) Install the plugins

Both of these, in this order:

1. `tabletop-gaming-events-calendar` (the core plugin) — activate it first.
2. `tabletop-events-discord-rsvp` (this one) — activate it second.

If you install this one first, it'll politely tell you off in wp-admin until the other one's active too.

## 7) Fill in the settings

wp-admin → **Events Calendar → Discord RSVP**.

Paste in, in order: the Bot Token, the Public Key, the Channel ID. Save.

The page shows you a URL that looks like `https://yoursite.com/wp-json/tecdr/v1/interactions`. Copy that.

## 8) Tell Discord about that URL

Back on the Discord developer site: **General Information → Interactions Endpoint URL**. Paste the URL from step 7. Save.

Discord checks it there and then. If your site's reachable and everything above was copied correctly, it saves without complaint. If it doesn't save, see "If it won't verify" below before you start doubting yourself.

## 9) Try it

Publish (or re-publish) an event with RSVP capacity switched on. Check the Discord channel — there should be a message with an **RSVP** button on it.

Click it. A little form should pop up asking for a name and email. Fill it in, submit it. You should get a private reply saying you're confirmed (or waitlisted, if you've set the capacity stupidly low to test this, which — fair enough, so did I).

## If it won't verify (step 8)

- Your site has to actually be reachable from the internet. `localhost` on your own laptop will never work for this — Discord's servers are calling out to you, not the other way round.
- Double-check the Public Key. It's long, it's easy to lose a character off the end when copying.
- Check the Bot Token isn't sat there blank or with old spacing pasted in around it.

## If the button doesn't do anything

- Check the Bot Token and Channel ID are both actually saved (Events Calendar → Discord RSVP).
- Check the event genuinely has RSVP capacity set — no capacity, no button, on purpose.
- Check your site's PHP error log for anything starting `[Tabletop Events Calendar — Discord RSVP]`. That'll tell you what actually went wrong rather than leaving you guessing.

## If you get two messages per event

You've probably also got the core plugin's own plain webhook switched on (Settings → Integrations, over in the core plugin). That one's a separate, simpler thing that doesn't do buttons. Turn one of them off — most people only want one.
