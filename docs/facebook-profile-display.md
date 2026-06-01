# Facebook Messenger Profile Display

## How Messenger Sender IDs Work

When a user messages your Facebook Page, the webhook delivers a `sender.id` value called the **PSID (Page-Scoped ID)**. This is **not** the user's real Facebook profile ID and cannot be used to link to their public profile.

Each PSID is unique per Page — the same real user has different PSIDs on different Pages.

## Fetching Name and Profile Picture

The Messenger User Profile API lets you look up a sender's name and profile picture using the PSID and your **Page Access Token**:

```
GET https://graph.facebook.com/v20.0/{PSID}
    ?fields=first_name,last_name,profile_pic,locale,timezone
    &access_token={PAGE_ACCESS_TOKEN}
```

This is implemented in `App\Services\Facebook\FacebookUserProfileService`.

## Data Stored Per Customer

| Column | Source |
|---|---|
| `external_customer_id` | PSID from webhook `sender.id` |
| `name` | Combined `first_name + last_name` from Graph API |
| `facebook_first_name` | `first_name` from Graph API |
| `facebook_last_name` | `last_name` from Graph API |
| `profile_picture_url` | `profile_pic` from Graph API |
| `facebook_locale` | `locale` from Graph API |
| `facebook_timezone` | `timezone` (UTC offset hours) from Graph API |
| `profile_synced_at` | Timestamp of last successful API sync |

## Display Logic

- `display_name` = `name` if set, otherwise `"Unknown Customer"`
- `avatar_url` = `profile_picture_url` if set, otherwise default avatar (letter)

## Caching

Profiles are cached in Laravel's cache store for **24 hours** per PSID. The webhook job skips the API call if `profile_synced_at` is within the last 24 hours. Use the **Refresh Profile** button in the inbox to force a re-fetch.

## Email is Not Available

The Messenger PSID does not expose the user's email address. To collect email you need:
- Facebook Login with `email` permission
- Account Linking flow
- Manual customer input inside the conversation

## Common Failure Reasons

| Reason | Effect |
|---|---|
| App in Development Mode | Profile API returns data only for app roles (admins/testers). Public users get an error. |
| Missing `pages_messaging` permission | API may return limited or no data. |
| Expired Page Access Token | All API calls for that Page will fail. |
| Meta API rate limit | Temporary failure — retry will succeed. |
| Profile pic URL expired | Image CDN link becomes invalid over time. Run `facebook:sync-profiles` to refresh. |

When the profile fetch fails, the system stores `"Unknown Customer"` as the display name and shows a letter avatar. Processing continues normally.

## Refreshing Old Conversations

Run this command to backfill profiles for existing conversations:

```bash
php artisan facebook:sync-profiles

# Force re-sync even for recently synced customers
php artisan facebook:sync-profiles --force

# Limit batch size
php artisan facebook:sync-profiles --limit=500
```

## Meta Developer Console Checklist

1. Your app must have the **`pages_messaging`** permission approved.
2. If your app is in **Development Mode**, only users with a role (Admin, Developer, Tester) on the app can have their profiles fetched.
3. To fetch profiles of **public users**, your app must be in **Live Mode** with approved permissions.
4. Verify your **Page Access Token** has not expired (check `token_expires_at` in `facebook_pages` table).
