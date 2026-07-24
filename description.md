# What this is, in one breath

RSVP to a Tabletop Events Calendar event without leaving Discord. A button shows up on the event announcement, someone taps it, a small native Discord form asks for a name and an email, and that's it — they're on the list, waitlisted, or told the event's full, exactly as if they'd used the website.

No bot sat running in the background somewhere. No second database of who's coming. It's a button and a form, and behind them, one REST endpoint that talks to the core plugin's existing RSVP system rather than reinventing it.

Needs [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) installed first. This plugin is the Discord half of the conversation — the events, capacity and waitlisting all belong to the other repo, and stay there.

Setup takes about ten minutes and mostly involves clicking around Discord's own developer site. See [IDIOTS-GUIDE.md](IDIOTS-GUIDE.md) if that sentence made you nervous.
