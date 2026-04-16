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
6. Nodig de bot uit in het kanaal met `/invite @botname`

### Configuratie

Maak een `.env` bestand aan op basis van het `.env.example` bestand:

```bash
cp .env.example .env
```

Vul de tokens in het `.env` bestand in:

```env
MASTO_INSTANCE=https://social.codefor.nl
MASTO_TOKEN=je-mastodon-access-token
SLACK_TOKEN=xoxb-je-slack-bot-token
SLACK_CHANNEL=C0ATJLU6DED
```

**Let op:** `SLACK_CHANNEL` kan zowel een channel ID (bijv. `C0ATJLU6DED`) als een channel naam (bijv. `#vragen-vanuit-mastodon`) zijn.

### Scripts

De bridge bestaat uit twee scripts die samen de synchronisatie verzorgen:

#### checkNewMessages.php
Controleert Mastodon voor nieuwe mentions/vragen en stuurt deze naar Slack:
- Haalt nieuwe mentions op van Mastodon
- Controleert of het een nieuwe vraag of een reactie is
- Nieuwe vragen worden naar Slack gestuurd
- Reacties op Mastodon worden in de juiste Slack thread geplaatst

```bash
php checkNewMessages.php
```

#### checkNewReplies.php
Controleert Slack threads voor nieuwe replies van community members en post deze naar Mastodon:
- Haalt nieuwe berichten op uit het Slack kanaal (alleen sinds laatste check)
- Filtert bot-berichten en eigen berichten uit (voorkomt loops)
- Post community antwoorden als replies op Mastodon

```bash
php checkNewReplies.php
```

### State management

Alle state wordt opgeslagen in één JSON bestand: `bridge_state.json`. Dit bestand bevat:
- `last_mastodon_id` - Laatste verwerkte Mastodon notification ID
- `last_slack_check` - Timestamp van laatste Slack check
- `thread_mapping` - Mapping tussen Slack threads en Mastodon statuses
- `processed_replies` - Welke Slack replies al verwerkt zijn

### Automatisch draaien met cron

Voeg de scripts toe aan je crontab om ze periodiek te draaien (bijvoorbeeld elke 5 minuten):

```bash
*/5 * * * * cd /pad/naar/bridge.codefor.nl && php checkNewMessages.php >> /var/log/mastodon-bridge.log 2>&1
*/5 * * * * cd /pad/naar/bridge.codefor.nl && php checkNewReplies.php >> /var/log/mastodon-bridge.log 2>&1
```

## Architectuur

### Bestandsstructuur

```
bridge.codefor.nl/
├── .env.example          # Template voor configuratie
├── .env                  # Configuratie (niet in git)
├── .gitignore           # Git ignore regels
├── BridgeState.php      # State management class
├── Mastodon.php         # Mastodon API wrapper
├── Slack.php            # Slack API wrapper
├── loadEnv.php          # .env file loader
├── checkNewMessages.php # Script voor Mastodon → Slack
├── checkNewReplies.php  # Script voor Slack → Mastodon
└── bridge_state.json    # Runtime state (niet in git)
```

### Veiligheid

- **Credentials**: Tokens worden opgeslagen in `.env` (niet in git)
- **Bot detection**: Bot berichten worden gefilterd om loops te voorkomen
- **Rate limiting**: Gebruikt timestamps om alleen nieuwe berichten op te halen
- **Minimal API calls**: 1 API call per run in plaats van N calls per thread

### Anti-loop mechanisme

De bridge voorkomt oneindige loops op twee manieren:
1. **Bot user ID check**: Berichten van de bot zelf worden geskipt
2. **Processed replies tracking**: Verwerkte berichten worden bijgehouden in state
