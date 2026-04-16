# Vraag het aan de Community

Code for NL heeft een Mastodon account @praatmee op hun instance.

Vragen aan dit account worden automatisch doorgestuurd naar het kanaal #vragen-vanuit-mastodon in de Code for NL Slack omgeving. Hier kunnen mensen in de community antwoord geven op deze vragen, die vervolgens als reply op de vraag uit naam van de community op Mastodon worden geplaatst.

## Hoe werkt het?

1. Stel een vraag aan @praatmee op Mastodon.
2. De vraag wordt automatisch doorgestuurd naar het #vragen-vanuit-mastodon kanaal in de Code for NL Slack omgeving.
3. Mensen in de community kunnen antwoorden geven op de vraag in het Slack kanaal.
4. De antwoorden worden als reply/thread op de oorspronkelijke vraag geplaatst op Mastodon, namens de community.

## Installatie en configuratie

### Benodigde tokens en permissies

#### Mastodon API Token

Maak een applicatie aan in Mastodon:
1. Ga naar **Instellingen → Ontwikkeling → Nieuwe applicatie**
2. Geef de applicatie een naam (bijv. "Code for NL Bridge")
3. Vink bij **Scopes** de volgende opties aan:
   - `read:notifications` - Om mentions/notificaties te kunnen lezen
   - `write:statuses` - Om replies te kunnen posten
4. Sla op en kopieer de gegenereerde access token

#### Slack Bot Token

Maak een Slack App aan:
1. Ga naar https://api.slack.com/apps en klik op **Create New App**
2. Kies **From scratch** en geef de app een naam
3. Ga naar **OAuth & Permissions** en voeg de volgende **Bot Token Scopes** toe:
   - `channels:history` - Om berichten in publieke kanalen te lezen
   - `channels:read` - Om kanaalinformatie te lezen
   - `chat:write` - Om berichten te posten
4. Installeer de app in je workspace
5. Kopieer de **Bot User OAuth Token** (begint met `xoxb-`)

#### Environment variabelen

Configureer de volgende environment variabelen:

```bash
export MASTO_INSTANCE="https://social.codefor.nl"
export MASTO_TOKEN="je-mastodon-access-token"
export SLACK_TOKEN="xoxb-je-slack-bot-token"
export SLACK_CHANNEL="#vragen-vanuit-mastodon"
```

### Scripts

#### checkNewMessages.php
Checkt Mastodon voor nieuwe mentions/vragen en stuurt deze naar Slack:

```bash
php checkNewMessages.php
```

#### checkSlackReplies.php
Checkt Slack threads voor nieuwe replies en post deze terug naar Mastodon:

```bash
php checkSlackReplies.php
```

### Automatisch draaien met cron

Voeg de scripts toe aan je crontab om ze periodiek te draaien (bijvoorbeeld elke 5 minuten):

```bash
*/5 * * * * cd /pad/naar/bridge.codefor.nl && php checkNewMessages.php >> /var/log/mastodon-bridge.log 2>&1
*/5 * * * * cd /pad/naar/bridge.codefor.nl && php checkSlackReplies.php >> /var/log/mastodon-bridge.log 2>&1
```
